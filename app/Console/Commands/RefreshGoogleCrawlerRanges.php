<?php

namespace App\Console\Commands;

use App\Services\GoogleCrawlerRanges;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

final class RefreshGoogleCrawlerRanges extends Command
{
    protected $signature = 'security:refresh-google-crawlers';

    protected $description = 'Refresh private, expiring Google crawler IP lists from their official HTTPS sources';

    public function handle(): int
    {
        $temporary = null;
        try {
            $sources = [];
            foreach (GoogleCrawlerRanges::SOURCES as $url) {
                $response = Http::acceptJson()->connectTimeout(5)->timeout(15)->withoutRedirecting()->get($url);
                if ($response->status() !== 200 || strlen($response->body()) > 1_000_000) {
                    throw new \RuntimeException('Invalid upstream response');
                }
                $data = json_decode($response->body(), true, 32, JSON_THROW_ON_ERROR);
                $created = is_string($data['creationTime'] ?? null)
                    ? (new \DateTimeImmutable($data['creationTime'], new \DateTimeZone('UTC')))->getTimestamp() : false;
                // An unchanged official list may have an old creationTime; freshness is the HTTPS fetch time.
                if ($created === false || $created < 0 || $created > time() + 300
                    || ! is_array($data['prefixes'] ?? null) || count($data['prefixes']) < 1
                    || count($data['prefixes']) > 10_000) {
                    throw new \RuntimeException('Invalid upstream list');
                }
                $prefixes = [];
                foreach ($data['prefixes'] as $entry) {
                    if (! is_array($entry) || count($entry) !== 1) {
                        throw new \RuntimeException('Invalid upstream prefix');
                    }
                    $prefix = $entry['ipv4Prefix'] ?? $entry['ipv6Prefix'] ?? null;
                    if (! GoogleCrawlerRanges::validPrefix($prefix)) {
                        throw new \RuntimeException('Invalid upstream prefix');
                    }
                    $prefixes[] = $prefix;
                }
                $sources[$url] = ['creation_time' => $data['creationTime'], 'prefixes' => array_values(array_unique($prefixes))];
            }

            $path = (string) config('storefront_protection.crawler_ranges_path');
            $directory = dirname($path);
            $privateRoot = realpath(storage_path('app/private'));
            if ($privateRoot === false || is_link(storage_path('app/private')) || is_link($directory) || is_link($path)
                || $directory !== storage_path('app/private/security')) {
                throw new \RuntimeException('Invalid private destination');
            }
            if (! is_dir($directory) && ! mkdir($directory, 0700)) {
                throw new \RuntimeException('Private directory unavailable');
            }
            if (! chmod($directory, 0700) || realpath($directory) !== $privateRoot.'/security') {
                throw new \RuntimeException('Private directory permissions unavailable');
            }
            $contents = json_encode(['schema' => 1, 'fetched_at' => time(), 'sources' => $sources], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (strlen($contents) > 2_000_000) {
                throw new \RuntimeException('Combined list too large');
            }
            // Publish only a complete set of three valid lists, atomically. Keep the previous file on failure.
            $temporary = tempnam($directory, '.google-crawlers-');
            if ($temporary === false || ! chmod($temporary, 0600)
                || file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)
                || ! rename($temporary, $path)) {
                throw new \RuntimeException('Atomic private write failed');
            }
            $temporary = null;
            $this->info('Three official Google-controlled lists refreshed. No customer data transmitted.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Refresh failed; the previous list was preserved. Rate enforcement requires a fresh valid list.');

            return self::FAILURE;
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
