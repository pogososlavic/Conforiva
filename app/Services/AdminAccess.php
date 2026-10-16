<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AdminAccess
{
    public function configured(): bool
    {
        return is_string(config('admin.username')) && config('admin.username') !== ''
            && is_string(config('admin.password_hash')) && password_get_info(config('admin.password_hash'))['algo'] !== null;
    }

    public function fingerprint(): string
    {
        return hash('sha256', config('admin.username').'|'.config('admin.password_hash'));
    }

    public function accounts(): array
    {
        $path = config('admin.accounts_path');
        if (is_link(dirname(dirname(dirname($path)))) || is_link(dirname(dirname($path))) || is_link(dirname($path)) || is_link($path) || ! is_file($path)) {
            return [];
        }
        try {
            $accounts = json_decode(Crypt::decryptString(File::get($path)), true, 16, JSON_THROW_ON_ERROR);

            if (! is_array($accounts)) {
                return [];
            }

            $valid = array_filter($accounts, fn ($account) => is_array($account) && isset($account['id'], $account['username'], $account['password_hash']) && ($account['active'] ?? false) === true);

            return array_values($valid);
        } catch (\Throwable) {
            return [];
        }
    }

    public function authenticate(string $username, string $password): ?array
    {
        if ($this->configured() && hash_equals(config('admin.username'), $username) && Hash::check($password, config('admin.password_hash'))) {
            return ['id' => 'owner', 'username' => config('admin.username'), 'role' => 'owner', 'password_hash' => config('admin.password_hash')];
        }
        foreach ($this->accounts() as $account) {
            if (hash_equals($account['username'], $username) && Hash::check($password, $account['password_hash'])) {
                return $account + ['role' => 'manager'];
            }
        }

        return null;
    }

    public function createManager(string $username, string $password): void
    {
        if ($username === config('admin.username') || collect($this->readAll())->contains(fn ($account) => $account['username'] === $username)) {
            throw ValidationException::withMessages(['username' => 'Cet identifiant est déjà utilisé.']);
        }
        $this->mutateAll(function (array $accounts) use ($username, $password): array {
            if ($username === config('admin.username') || collect($accounts)->contains(fn ($account) => $account['username'] === $username)) {
                throw ValidationException::withMessages(['username' => 'Cet identifiant est déjà utilisé.']);
            }
            $accounts[] = ['id' => (string) Str::uuid(), 'username' => $username, 'password_hash' => Hash::make($password), 'role' => 'manager', 'active' => true, 'created_at' => now()->toIso8601String()];

            return $accounts;
        });
    }

    public function changePassword(Request $request, string $password): void
    {
        $state = $request->session()->get('admin.auth');
        abort_unless(is_array($state), 401);
        if (($state['role'] ?? '') === 'owner') {
            throw ValidationException::withMessages(['password' => 'Le mot de passe principal reste géré depuis la configuration privée du projet.']);
        }
        $this->mutateAll(function (array $accounts) use ($state, $password): array {
            $changed = false;
            foreach ($accounts as &$account) {
                if (($account['id'] ?? null) === ($state['id'] ?? null) && ($account['active'] ?? false)) {
                    $account['password_hash'] = Hash::make($password);
                    $account['password_changed_at'] = now()->toIso8601String();
                    $changed = true;
                }
            }
            unset($account);
            abort_unless($changed, 401);

            return $accounts;
        });
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function isOwner(Request $request): bool
    {
        return ($request->session()->get('admin.auth.role') ?? null) === 'owner';
    }

    private function readAll(): array
    {
        $path = config('admin.accounts_path');
        abort_if(is_link(dirname($path)) || is_link($path), 503);
        if (! is_file($path)) {
            return [];
        }
        try {
            $accounts = json_decode(Crypt::decryptString(File::get($path)), true, 16, JSON_THROW_ON_ERROR);

            return is_array($accounts) ? $accounts : throw new \RuntimeException;
        } catch (\Throwable) {
            abort(503);
        }
    }

    private function writeUnlocked(array $accounts, string $path): void
    {
        $directory = dirname($path);
        abort_if(is_link(dirname($directory)) || is_link($directory) || is_link($path), 503);
        File::ensureDirectoryExists($directory, 0700, true);
        $temporary = $path.'.'.Str::uuid().'.pending';
        $handle = fopen($temporary, 'x');
        abort_unless($handle, 503);
        try {
            chmod($temporary, 0600);
            $payload = Crypt::encryptString(json_encode(array_values($accounts), JSON_THROW_ON_ERROR));
            abort_unless(fwrite($handle, $payload) === strlen($payload) && fflush($handle), 503);
        } finally {
            fclose($handle);
        }
        abort_unless(rename($temporary, $path), 503);
    }

    private function mutateAll(callable $callback): void
    {
        $path = config('admin.accounts_path');
        $directory = dirname($path);
        abort_if(is_link(dirname(dirname($directory))) || is_link(dirname($directory)) || is_link($directory) || is_link($path), 503);
        File::ensureDirectoryExists($directory, 0700, true);
        $lockPath = $path.'.lock';
        abort_if(is_link($lockPath), 503);
        $lock = fopen($lockPath, 'c');
        abort_unless($lock && flock($lock, LOCK_EX), 503);
        chmod($lockPath, 0600);
        try {
            $accounts = $this->readAll();
            $this->writeUnlocked($callback($accounts), $path);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function valid(Request $request): bool
    {
        $state = $request->session()->get('admin.auth');
        $account = is_array($state) && ($state['role'] ?? null) === 'manager' ? $this->findById($state['id'] ?? '') : null;
        $ownerValid = is_array($state) && (! isset($state['role']) || ($state['role'] ?? null) === 'owner') && $this->configured()
            && hash_equals($this->fingerprint(), (string) ($state['fingerprint'] ?? ''));
        $valid = ($this->configured() || $this->accounts() !== []) && is_array($state)
            && ($ownerValid || ($account !== null && ($account['active'] ?? false) && hash_equals((string) ($account['password_hash'] ?? ''), (string) ($state['fingerprint'] ?? ''))))
            && now()->timestamp - ($state['started'] ?? 0) < config('admin.absolute_seconds')
            && now()->timestamp - ($state['seen'] ?? 0) < config('admin.idle_seconds');
        if ($valid) {
            $request->session()->put('admin.auth.seen', now()->timestamp);
        } else {
            $request->session()->forget('admin.auth');
        }

        return $valid;
    }

    public function login(Request $request, string $username, string $password): bool
    {
        $account = $this->authenticate($username, $password);
        if (! $account) {
            return false;
        }
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->put('admin.auth', ['id' => $account['id'], 'username' => $account['username'], 'role' => $account['role'], 'fingerprint' => $account['role'] === 'owner' ? $this->fingerprint() : $account['password_hash'], 'started' => now()->timestamp, 'seen' => now()->timestamp]);

        return true;
    }

    private function findById(string $id): ?array
    {
        foreach ($this->accounts() as $account) {
            if (($account['id'] ?? null) === $id) {
                return $account;
            }
        }

        return null;
    }
}
