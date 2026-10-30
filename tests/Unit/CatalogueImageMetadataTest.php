<?php

namespace Tests\Unit;

use App\Services\CatalogueImageMetadata;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogueImageMetadataTest extends TestCase
{
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Cache::flush and locks are confined to this test application's in-memory store.
        config(['cache.default' => 'array', 'cache.stores.array' => ['driver' => 'array', 'serialize' => false]]);
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function image(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catalogue-metadata-');
        $this->paths[] = $path;
        // 1 × 1 transparent GIF; no GD or external service required.
        file_put_contents($path, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

        return $path;
    }

    public function test_dimensions_follow_replacement_and_deletion(): void
    {
        $path = $this->image();
        $service = new CatalogueImageMetadata(fn () => time() + 3);
        $before = $service->inspect($path);
        $this->assertSame(1, $before['width']);
        $this->assertSame(IMAGETYPE_GIF, $before['type']);
        // A file-size change invalidates even inside the same timestamp second.
        file_put_contents($path, 'trailer', FILE_APPEND);
        $this->assertGreaterThan($before['size'], $service->inspect($path)['size']);
        unlink($path);
        $this->assertNull($service->inspect($path));
    }

    public function test_a_new_instance_reads_one_manifest_without_rewriting_warm_metadata(): void
    {
        $path = $this->image();
        Cache::flush();
        $first = new CatalogueImageMetadata(fn () => time() + 3);
        $metadata = $first->inspect($path);
        $first->flush();
        $this->assertSame($metadata, (new CatalogueImageMetadata(fn () => time() + 3))->inspect($path));
    }

    public function test_warm_manifest_is_loaded_once_and_does_not_write(): void
    {
        $path = $this->image();
        $stat = stat($path);
        $metadata = ['width' => 1, 'height' => 1, 'type' => IMAGETYPE_GIF, 'size' => $stat['size']];
        $manifest = [hash('sha256', $path) => [
            'fingerprint' => implode('|', [$stat['dev'], $stat['ino'], $stat['size'], $stat['mtime'], $stat['ctime']]),
            'value' => $metadata,
        ]];
        Cache::shouldReceive('get')->once()->andReturn($manifest);
        Cache::shouldReceive('put')->never();
        Cache::shouldReceive('lock')->never();
        $service = new CatalogueImageMetadata(fn () => time() + 3);
        $this->assertSame($metadata, $service->inspect($path));
        $this->assertSame($metadata, $service->inspect($path));
        $service->flush();
    }

    public function test_recent_same_size_rewrite_bypasses_all_caches(): void
    {
        $path = $this->image();
        Cache::shouldReceive('get')->never();
        Cache::shouldReceive('put')->never();
        $service = new CatalogueImageMetadata;
        $this->assertSame(1, $service->inspect($path)['width']);
        $bytes = file_get_contents($path);
        $bytes[6] = chr(2);
        file_put_contents($path, $bytes);
        $this->assertSame(2, $service->inspect($path)['width']);
        $service->flush();
    }

    public function test_concurrent_cold_manifests_merge_without_losing_other_entries(): void
    {
        Cache::flush();
        $one = $this->image();
        $two = $this->image();
        $first = new CatalogueImageMetadata(fn () => time() + 3);
        $second = new CatalogueImageMetadata(fn () => time() + 3);
        $first->inspect($one);
        $second->inspect($two);
        $first->flush();
        $second->flush();
        $manifest = Cache::get('catalogue-images-v1:'.hash('sha256', dirname($one)));
        $this->assertArrayHasKey(hash('sha256', $one), $manifest);
        $this->assertArrayHasKey(hash('sha256', $two), $manifest);
    }

    public function test_busy_manifest_lock_skips_write_without_waiting(): void
    {
        $path = $this->image();
        $key = 'catalogue-images-v1:'.hash('sha256', dirname($path));
        $lock = Cache::lock($key.':write', 10);
        $this->assertTrue($lock->get());
        try {
            $service = new CatalogueImageMetadata(fn () => time() + 3);
            $this->assertSame(1, $service->inspect($path)['width']);
            $service->flush();
            $this->assertNull(Cache::get($key));
        } finally {
            $lock->release();
        }
    }

    public function test_broken_cache_and_invalid_files_do_not_break_the_storefront(): void
    {
        $path = $this->image();
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('offline'));
        Cache::shouldReceive('lock')->andThrow(new \RuntimeException('offline'));
        Cache::shouldReceive('put')->andThrow(new \RuntimeException('offline'));
        $service = new CatalogueImageMetadata(fn () => time() + 3);
        $this->assertSame(1, $service->inspect($path)['height']);
        file_put_contents($path, 'not an image');
        $this->assertNull($service->inspect($path));
        $service->flush();
    }
}
