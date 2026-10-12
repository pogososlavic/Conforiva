<?php

namespace App\Services\Admin;

use App\Services\Commerce\BankTransfer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Encrypted, versioned storage for the HOMYA bank account managed from the admin dashboard. */
final class BankDetailsSettings
{
    private function directory(): string
    {
        $directory = storage_path('app/private/admin');
        if (is_link(storage_path('app/private')) || is_link($directory)) {
            throw new \RuntimeException('Unsafe bank settings directory.');
        }
        File::ensureDirectoryExists($directory, 0700, true);
        if (! chmod($directory, 0700)) {
            throw new \RuntimeException('Cannot protect bank settings directory.');
        }

        return $directory;
    }

    private function path(): string
    {
        return $this->directory().'/bank-details.encrypted';
    }

    public function current(): array
    {
        $path = $this->path();
        if (is_link($path)) {
            throw new \RuntimeException('Unsafe bank settings file.');
        }
        if (! is_file($path)) {
            return $this->normalize(config('commerce.bank', []));
        }

        $stored = json_decode(Crypt::decryptString(File::get($path)), true, 16, JSON_THROW_ON_ERROR);
        if (! is_array($stored)) {
            throw new \RuntimeException('Invalid bank settings file.');
        }

        return $this->normalize($stored);
    }

    private function normalize(array $settings): array
    {
        return [
            'enabled' => filter_var($settings['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'beneficiary' => trim((string) ($settings['beneficiary'] ?? '')),
            'iban' => strtoupper(preg_replace('/\s+/', '', (string) ($settings['iban'] ?? ''))),
            'bic' => strtoupper(preg_replace('/\s+/', '', (string) ($settings['bic'] ?? ''))),
            'bank_name' => trim((string) ($settings['bank_name'] ?? '')),
        ];
    }

    public function version(): string
    {
        return hash_hmac('sha256', json_encode($this->current(), JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function save(array $settings, string $version): void
    {
        $settings = $this->normalize($settings);
        if (app(BankTransfer::class)->validatedDetails($settings) === null) {
            throw ValidationException::withMessages(['iban' => 'Vérifiez le bénéficiaire, la banque, l’IBAN et le BIC.']);
        }

        $this->writeVersioned($settings, $version);
    }

    public function delete(string $version): void
    {
        // Keep a tombstone so an older .env RIB cannot reappear after deletion.
        $this->writeVersioned(['enabled' => false, 'beneficiary' => '', 'iban' => '', 'bic' => '', 'bank_name' => ''], $version);
    }

    private function writeVersioned(array $settings, string $version): void
    {
        $directory = $this->directory();
        $path = $directory.'/bank-details.encrypted';
        $lockPath = $directory.'/.bank-details.lock';
        if (is_link($path) || is_link($lockPath)) {
            throw new \RuntimeException('Unsafe bank settings path.');
        }
        $lock = fopen($lockPath, 'c');
        if (! $lock || ! flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Cannot lock bank settings.');
        }
        try {
            if (! chmod($lockPath, 0600)) {
                throw new \RuntimeException('Cannot protect bank settings lock.');
            }
            if (! hash_equals($this->version(), $version)) {
                throw ValidationException::withMessages(['settings' => 'Les coordonnées ont changé. Rechargez la page avant de continuer.']);
            }
            $temporary = $directory.'/.bank-details-pending-'.Str::uuid();
            $handle = null;
            try {
                $handle = fopen($temporary, 'x');
                if (! $handle) {
                    throw new \RuntimeException('Cannot create bank settings file.');
                }
                if (! chmod($temporary, 0600)) {
                    throw new \RuntimeException('Cannot protect bank settings file.');
                }
                $encrypted = Crypt::encryptString(json_encode($settings, JSON_THROW_ON_ERROR));
                if (fwrite($handle, $encrypted) !== strlen($encrypted) || ! fflush($handle)) {
                    throw new \RuntimeException('Cannot write bank settings file.');
                }
                if (function_exists('fsync')) {
                    fsync($handle);
                }
                fclose($handle);
                $handle = null;
                if (! rename($temporary, $path)) {
                    throw new \RuntimeException('Cannot commit bank settings.');
                }
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
