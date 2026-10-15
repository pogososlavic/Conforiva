<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class StorefrontRequestLimiter
{
    /** @return array{limited: bool, retry_after: int}|null Null means unavailable; allow the visit. */
    public function attempt(string $ip, string $mode): ?array
    {
        try {
            $packedIp = @inet_pton($ip);
            $key = (string) config('app.key');
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7), true) ?: '';
            }
            $limits = [
                'minute' => ['seconds' => 60, 'limit' => (int) config('storefront_protection.requests_per_minute')],
                'hour' => ['seconds' => 3600, 'limit' => (int) config('storefront_protection.requests_per_hour')],
            ];
            if ($packedIp === false || strlen($key) < 16
                || $limits['minute']['limit'] < 1 || $limits['hour']['limit'] < 1
                || $limits['minute']['limit'] > 1000000 || $limits['hour']['limit'] > 10000000) {
                throw new RuntimeException('Storefront limiter configuration unavailable.');
            }

            $storeName = (string) config('storefront_protection.cache_store', 'storefront_protection');
            $driver = config('cache.stores.'.$storeName.'.driver');
            if (! in_array($driver, ['file', 'redis', 'database', 'memcached', 'dynamodb'], true)) {
                throw new RuntimeException('A persistent cache store is required.');
            }
            $cache = Cache::store($storeName);
            $store = $cache->getStore();
            if (! $store instanceof LockProvider) {
                throw new RuntimeException('A cache store with atomic locks is required.');
            }

            // IPs are never stored verbatim. The same short-lived budget covers every page.
            $identity = hash_hmac('sha256', 'storefront-v1|'.$packedIp, $key);
            $bucketKey = 'homya:storefront:budget:'.$identity;

            // FileStore::increment() alone is not atomic; serialize both window updates.
            // Never queue PHP workers behind a busy visitor budget.
            $result = $store->lock('homya:storefront:lock:'.$identity, 10)->get(function () use ($cache, $bucketKey, $limits): array {
                $now = time();
                $state = $cache->get($bucketKey, []);
                if (! is_array($state)) {
                    throw new RuntimeException('Storefront limiter state unavailable.');
                }
                $retryAfter = 0;
                foreach ($limits as $name => $window) {
                    $counter = $state[$name] ?? null;
                    if (! is_array($counter) || ! is_int($counter['reset_at'] ?? null)
                        || ! is_int($counter['count'] ?? null) || $counter['count'] < 0
                        || $counter['reset_at'] <= $now || $counter['reset_at'] > $now + $window['seconds']) {
                        $counter = ['count' => 0, 'reset_at' => $now + $window['seconds']];
                    }
                    $counter['count'] = min($counter['count'] + 1, $window['limit'] + 1);
                    $state[$name] = $counter;
                    if ($counter['count'] > $window['limit']) {
                        $retryAfter = max($retryAfter, $counter['reset_at'] - $now);
                    }
                }

                if (! $cache->put($bucketKey, $state, 3601)) {
                    throw new RuntimeException('Storefront limiter state could not be stored.');
                }

                return ['limited' => $retryAfter > 0, 'retry_after' => max(1, $retryAfter)];
            });
            if ($result === false) {
                throw new RuntimeException('Storefront limiter lock unavailable.');
            }

            return $result;
        } catch (\Throwable) {
            // Do not expose or log IPs, URLs, headers, exception messages or cache credentials.
            $this->signal('limiter_unavailable', $mode);

            return null;
        }
    }

    /** Aggregate signals globally, at most once per reason and mode every 15 minutes. */
    public function signal(string $reason, string $mode): void
    {
        if (! in_array($reason, ['invalid_configuration', 'client_ip_not_verified', 'crawler_ranges_not_ready', 'request_limit_reached', 'limiter_unavailable'], true)
            || ! in_array($mode, ['observe', 'enforce'], true)) {
            return;
        }

        // This small independent gate can still report a cache-store failure without log flooding.
        $path = storage_path('framework/cache/storefront-protection-signals.json');
        $handle = null;
        $context = null;
        try {
            if (is_link($path)) {
                return;
            }
            $handle = @fopen($path, 'c+');
            if ($handle === false || ! @flock($handle, LOCK_EX | LOCK_NB)) {
                return;
            }
            @chmod($path, 0600);
            $data = json_decode((string) stream_get_contents($handle, 8192), true);
            $data = is_array($data) ? $data : [];
            $key = $reason.':'.$mode;
            $entry = is_array($data[$key] ?? null) ? $data[$key] : [];
            $events = min(1000000000, max(0, (int) ($entry['events'] ?? 0)) + 1);
            $now = time();
            $lastLog = (int) ($entry['last_log'] ?? 0);
            $emit = $lastLog <= $now - 900 || $lastLog > $now;
            $data[$key] = ['events' => $emit ? 0 : $events, 'last_log' => $emit ? $now : $lastLog];
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            if (! rewind($handle) || ! ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json)) {
                return;
            }
            if ($emit) {
                $context = ['reason' => $reason, 'mode' => $mode, 'events_since_previous_signal' => $events];
            }
        } catch (\Throwable) {
            // A failed reporting gate must not affect shopping or produce an unbounded log stream.
        } finally {
            if (is_resource($handle)) {
                @flock($handle, LOCK_UN);
                fclose($handle);
            }
        }

        if ($context !== null) {
            try {
                Log::notice('HOMYA_STOREFRONT_PROTECTION_SIGNAL', $context);
            } catch (\Throwable) {
                // Logging never changes request availability.
            }
        }
    }
}
