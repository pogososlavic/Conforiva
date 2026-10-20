<?php

namespace Tests\Unit;

use App\Services\CatalogueImageFraming;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CatalogueImageFramingTest extends TestCase
{
    private string $fixture;

    private string $url = '/media/catalogue/aaaaaaaaaaaaaaaaaaaaaaaa.webp';

    private array $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = sys_get_temp_dir().'/image-framing-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->fixture.'/media/catalogue', 0700, true);
        $bytes = file_get_contents(public_path('media/home/open-salon-960.webp'));
        $this->app->usePublicPath($this->fixture);
        File::put(public_path(ltrim($this->url, '/')), $bytes);
        $dimensions = getimagesize(public_path(ltrim($this->url, '/')));
        $this->entry = ['sha256' => hash('sha256', $bytes), 'width' => $dimensions[0], 'height' => $dimensions[1], 'bounds' => [100, 100, $dimensions[0] - 100, $dimensions[1] - 100]];
        config(['catalogue.image_framing_path' => $this->fixture.'/framing.json']);
        $this->manifest($this->entry);
    }

    private function manifest(array $entry): void
    {
        File::put($this->fixture.'/framing.json', json_encode(['version' => 1, 'images' => [$this->url => $entry]]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixture);
        parent::tearDown();
    }

    public function test_reviewed_coordinates_preserve_the_source_bytes(): void
    {
        $result = (new CatalogueImageFraming)->forImage($this->url);
        $this->assertSame($this->entry['bounds'], $result['bounds']);
        $this->assertSame($this->entry['sha256'], hash_file('sha256', public_path(ltrim($this->url, '/'))));
    }

    public function test_changed_or_missing_source_cannot_reuse_its_old_crop(): void
    {
        File::put(public_path(ltrim($this->url, '/')), 'changed');
        $this->assertNull((new CatalogueImageFraming)->forImage($this->url));
        $this->assertNull((new CatalogueImageFraming)->forImage('/media/catalogue/'.str_repeat('b', 24).'.webp'));
    }

    public function test_invalid_coordinates_dimensions_and_external_urls_fall_back_safely(): void
    {
        foreach ([[0, 0, $this->entry['width'] + 1, 500], [100, 100, 20, 500], [0, 0, '800', 500]] as $bounds) {
            $this->manifest([...$this->entry, 'bounds' => $bounds]);
            $this->assertNull((new CatalogueImageFraming)->forImage($this->url));
        }
        $this->manifest([...$this->entry, 'width' => 900]);
        $this->assertNull((new CatalogueImageFraming)->forImage($this->url));
        $this->assertNull((new CatalogueImageFraming)->forImage('https://example.com/image.webp'));
    }

    public function test_corrupt_manifest_is_a_whole_image_fallback(): void
    {
        File::put($this->fixture.'/framing.json', '{invalid');
        $this->assertNull((new CatalogueImageFraming)->forImage($this->url));
    }
}
