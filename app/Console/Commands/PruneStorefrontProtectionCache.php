<?php

namespace App\Console\Commands;

use DirectoryIterator;
use Illuminate\Console\Command;
use RuntimeException;

final class PruneStorefrontProtectionCache extends Command
{
    protected $signature = 'security:prune-storefront-cache {--dry-run : Count stale files without deleting them}';

    protected $description = 'Remove only isolated storefront cache files last modified more than two hours ago';

    public function handle(): int
    {
        $counts = ['examined' => 0, 'stale' => 0, 'deleted' => 0, 'skipped' => 0, 'failed' => 0];
        try {
            // Fixed destinations only: never follow a configurable cache path or touch global caches.
            $base = storage_path('framework/cache/storefront-protection');
            $directories = [
                storage_path(),
                storage_path('framework'),
                storage_path('framework/cache'),
                $base,
                $base.'/data',
                $base.'/locks',
            ];
            $this->assertDirectories($directories);
            $cutoff = time() - 7200;
            foreach ([$base.'/data', $base.'/locks'] as $root) {
                if (! is_dir($root)) {
                    continue;
                }
                // FileStore hashes cache names into exactly two hexadecimal subdirectories.
                foreach ($this->hashDirectories($root) as $first) {
                    foreach ($this->hashDirectories($first) as $second) {
                        foreach (new DirectoryIterator($second) as $file) {
                            if ($file->isDot()) {
                                continue;
                            }
                            $path = $file->getPathname();
                            if ($file->isLink() || ! $file->isFile()
                                || ! preg_match('/\A[a-f0-9]{40}\z/', $file->getFilename())) {
                                $counts['skipped']++;

                                continue;
                            }
                            $counts['examined']++;
                            clearstatcache(true, $path);
                            $stat = @lstat($path);
                            if (! is_array($stat) || ($stat['mode'] & 0170000) !== 0100000
                                || $stat['mtime'] >= $cutoff) {
                                continue;
                            }
                            $counts['stale']++;
                            if ($this->option('dry-run')) {
                                continue;
                            }
                            // Recheck immediately before unlinking; never remove a replaced file or symlink.
                            $this->assertDirectories($directories);
                            clearstatcache(true, $path);
                            $current = @lstat($path);
                            if (! is_array($current) || ($current['mode'] & 0170000) !== 0100000
                                || $current['ino'] !== $stat['ino'] || $current['mtime'] >= $cutoff
                                || ! $this->unchangedDirectory($root) || ! $this->unchangedDirectory($first)
                                || ! $this->unchangedDirectory($second)) {
                                $counts['skipped']++;

                                continue;
                            }
                            if (@unlink($path)) {
                                $counts['deleted']++;
                            } else {
                                $counts['failed']++;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable) {
            $this->error('Purge interrompue : un emplacement du cache isolé est indisponible ou non sûr.');
            $counts['failed']++;
        }

        // No file paths, cache identities or visitor information in console output.
        $this->line(json_encode($counts, JSON_THROW_ON_ERROR));

        return $counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function assertDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            if (is_link($directory) || (file_exists($directory) && ! $this->unchangedDirectory($directory))) {
                throw new RuntimeException('Unsafe cache directory.');
            }
        }
    }

    private function unchangedDirectory(string $path): bool
    {
        clearstatcache(true, $path);

        return ! is_link($path) && is_dir($path)
            && realpath($path) === realpath(dirname($path)).'/'.basename($path);
    }

    /** @return list<string> */
    private function hashDirectories(string $parent): array
    {
        if (! $this->unchangedDirectory($parent)) {
            throw new RuntimeException('Unsafe cache directory.');
        }
        $directories = [];
        foreach (new DirectoryIterator($parent) as $entry) {
            if (! $entry->isDot() && ! $entry->isLink() && $entry->isDir()
                && preg_match('/\A[a-f0-9]{2}\z/', $entry->getFilename())
                && $this->unchangedDirectory($entry->getPathname())) {
                $directories[] = $entry->getPathname();
            }
        }

        return $directories;
    }
}
