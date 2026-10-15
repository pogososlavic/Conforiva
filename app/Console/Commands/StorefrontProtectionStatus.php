<?php

namespace App\Console\Commands;

use App\Services\GoogleCrawlerRanges;
use Illuminate\Console\Command;

final class StorefrontProtectionStatus extends Command
{
    protected $signature = 'security:storefront-status';

    protected $description = 'Read storefront protection readiness and aggregate signals without exposing visitor data';

    public function handle(GoogleCrawlerRanges $ranges): int
    {
        $mode = config('storefront_protection.mode');
        $validMode = in_array($mode, ['off', 'observe', 'enforce'], true);
        $verifiedIp = config('storefront_protection.client_ip_verified') === true;
        $freshRanges = $ranges->ready();
        $signals = [];
        $path = storage_path('framework/cache/storefront-protection-signals.json');
        try {
            if (is_file($path) && ! is_link($path) && filesize($path) <= 8192) {
                $data = json_decode(file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
                foreach (['invalid_configuration', 'client_ip_not_verified', 'crawler_ranges_not_ready', 'request_limit_reached', 'limiter_unavailable'] as $reason) {
                    foreach (['observe', 'enforce'] as $signalMode) {
                        $entry = $data[$reason.':'.$signalMode] ?? null;
                        if (is_array($entry) && is_int($entry['last_log'] ?? null) && is_int($entry['events'] ?? null)) {
                            $signals[$reason.':'.$signalMode] = [
                                'last_signal_at_utc' => gmdate('c', $entry['last_log']),
                                'events_since_previous_signal' => max(0, min(1000000000, $entry['events'])),
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Readiness remains visible even when the independent signal file is unavailable.
        }

        $this->line(json_encode([
            'configured_mode' => $validMode ? $mode : 'invalid',
            'client_ip_verified_flag' => $verifiedIp,
            'google_ranges_fresh' => $freshRanges,
            'eligible_mode' => $mode === 'off' ? 'off'
                : ($mode === 'enforce' && $verifiedIp && $freshRanges ? 'enforce' : 'observe'),
            'runtime_cache_availability_verified' => false,
            'aggregate_signals' => $signals,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
