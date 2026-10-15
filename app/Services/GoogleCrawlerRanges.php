<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\IpUtils;

/** Local, expiring Google-controlled crawler ranges; never a network call on a page request. */
final class GoogleCrawlerRanges
{
    public const SOURCES = [
        'https://developers.google.com/static/crawling/ipranges/common-crawlers.json',
        'https://developers.google.com/static/crawling/ipranges/special-crawlers.json',
        'https://developers.google.com/static/crawling/ipranges/user-triggered-fetchers-google.json',
    ];

    private bool $loaded = false;

    private ?array $prefixes = null;

    public function ready(): bool
    {
        if (! $this->loaded) {
            $this->loaded = true;
            $path = (string) config('storefront_protection.crawler_ranges_path');
            try {
                if (! is_file($path) || is_link($path) || filesize($path) > 2_000_000) {
                    return false;
                }
                $data = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
                $maxAge = max(1, min(72, (int) config('storefront_protection.crawler_ranges_max_age_hours', 48))) * 3600;
                if (($data['schema'] ?? null) !== 1 || ! is_int($data['fetched_at'] ?? null)
                    || $data['fetched_at'] > time() + 300 || time() - $data['fetched_at'] > $maxAge
                    || array_keys($data['sources'] ?? []) !== self::SOURCES) {
                    return false;
                }

                $prefixes = [];
                foreach ($data['sources'] as $source) {
                    if (! is_array($source) || ! is_array($source['prefixes'] ?? null)
                        || count($source['prefixes']) < 1 || count($source['prefixes']) > 10_000) {
                        return false;
                    }
                    foreach ($source['prefixes'] as $prefix) {
                        if (! self::validPrefix($prefix)) {
                            return false;
                        }
                        $prefixes[] = $prefix;
                    }
                }
                $this->prefixes = array_values(array_unique($prefixes));
            } catch (\Throwable) {
                // An absent/corrupt/stale list must not cause Google to be blocked.
                return false;
            }
        }

        return $this->prefixes !== null;
    }

    public function contains(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false && $this->ready()
            && IpUtils::checkIp($ip, $this->prefixes);
    }

    public static function validPrefix(mixed $prefix): bool
    {
        if (! is_string($prefix) || substr_count($prefix, '/') !== 1) {
            return false;
        }
        [$ip, $bits] = explode('/', $prefix);
        if (! ctype_digit($bits)) {
            return false;
        }

        // Reject accidentally broad ranges; these lists identify crawler subnets, not all Google Cloud.
        return (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && (int) $bits >= 16 && (int) $bits <= 32)
            || (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false && (int) $bits >= 32 && (int) $bits <= 128);
    }
}
