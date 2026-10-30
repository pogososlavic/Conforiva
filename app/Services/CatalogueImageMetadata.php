<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Private metadata manifests, loaded once per directory and request. */
final class CatalogueImageMetadata
{
    private array $manifests = [];

    private array $dirty = [];

    public function __construct(private ?Closure $clock = null) {}

    public function inspect(string $path): ?array
    {
        clearstatcache(true, $path);
        $stat = @stat($path);
        if ($stat === false || ! is_file($path)) {
            return null;
        }
        // Stat times have one-second resolution: never reuse a freshly modified file.
        $now = $this->clock !== null ? ($this->clock)() : time();
        if (max($stat['mtime'], $stat['ctime']) >= $now - 1) {
            return $this->dimensions($path, $stat['size']);
        }
        $directory = 'catalogue-images-v1:'.hash('sha256', dirname($path));
        $key = hash('sha256', $path);
        $fingerprint = implode('|', [$stat['dev'], $stat['ino'], $stat['size'], $stat['mtime'], $stat['ctime']]);
        if (! array_key_exists($directory, $this->manifests)) {
            try {
                $cached = Cache::get($directory);
                $this->manifests[$directory] = is_array($cached) ? $cached : [];
            } catch (Throwable) {
                $this->manifests[$directory] = [];
            }
        }
        $entry = $this->manifests[$directory][$key] ?? null;
        if (is_array($entry) && ($entry['fingerprint'] ?? null) === $fingerprint && array_key_exists('value', $entry)) {
            return $entry['value'];
        }
        $value = $this->dimensions($path, $stat['size']);
        $entry = ['fingerprint' => $fingerprint, 'value' => $value];
        $this->manifests[$directory][$key] = $entry;
        $this->dirty[$directory][$key] = $entry;

        return $value;
    }

    private function dimensions(string $path, int $size): ?array
    {
        $dimensions = @getimagesize($path);

        return $dimensions ? ['width' => $dimensions[0], 'height' => $dimensions[1], 'type' => $dimensions[2], 'size' => $size] : null;
    }

    /** Call once after collecting catalogue images; warm requests do not write. */
    public function flush(): void
    {
        foreach ($this->dirty as $directory => $entries) {
            try {
                $lock = Cache::lock($directory.':write', 10);
                if (! $lock->get()) {
                    continue;
                }
                try {
                    $cached = Cache::get($directory);
                    $merged = array_replace(is_array($cached) ? $cached : [], $entries);
                    // Bound disk/memory use and expire unused deployment manifests.
                    Cache::put($directory, array_slice($merged, -10000, null, true), 3600);
                } finally {
                    $lock->release();
                }
            } catch (Throwable) {
                // Cache/lock failure must not affect catalogue availability.
            }
        }
        $this->dirty = [];
    }
}
