<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CatalogueOfficialFacts;
use App\Services\FileCatalogueSource;
use App\Services\MerchantProducts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class NativeSmallPhotoTest extends TestCase
{
    private const SLUG = 'granules-de-bois-piveteau-hp-72-sacs-de-15kg';

    private const URL = '/media/catalogue/1f865da264a3a61bb8c6e95f.jpg';

    private const SHA256 = '1f865da264a3a61bb8c6e95f2bece1aadd119eb788f81370dab2899161ffc088';

    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        Http::preventStrayRequests();
        config(['catalogue.source' => 'files', 'dashboard.enabled' => false,
            'commerce.inventory_enabled' => false, 'merchant.enabled' => false, 'seo.indexing_enabled' => false]);
        app()->setLocale('fr');
    }

    private function source(): array
    {
        return json_decode(file_get_contents(storage_path('app/catalogue/products/'.self::SLUG.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function image(): array
    {
        return ['local_url' => self::URL, 'width' => 473, 'height' => 473];
    }

    public function test_exact_native_bag_photo_is_shown_without_altering_the_archive_or_whole_lot_price(): void
    {
        $source = $this->source();
        $this->assertSame('d022f74fc3f79ebc00180f57cbc67870b9fdffa21782c91cdcd9353ddb966120', CatalogueOfficialFacts::sourceHash($source));
        $this->assertSame(self::SHA256, hash_file('sha256', public_path(ltrim(self::URL, '/'))));
        $this->assertSame(68570, filesize(public_path(ltrim(self::URL, '/'))));
        $catalogue = new Catalogue(new FileCatalogueSource);
        $product = $catalogue->product(self::SLUG);
        $this->assertSame($this->image(), $product['image']);
        $this->assertSame($this->image(), $catalogue->products()->firstWhere('slug', self::SLUG)['image']);
        $this->assertSame($source['price_cents'], $product['promotion']['base_price_cents'] ?? $product['price_cents']);
        $this->assertSame(72, $product['sales_pack']['count']);
        $this->assertSame(15, $product['sales_pack']['unit_weight_kg']);
        $this->assertSame('unit_bag', $product['sales_pack']['identifier_scope']);
        $this->assertSame('G00003249', $product['manufacturer']['mpn']);
        $this->assertSame('unit_bag', $product['manufacturer']['identifier_scope']);
        $this->assertNotContains(self::URL, array_column($source['images'], 'local_url'));
        $this->assertFalse(app(MerchantProducts::class)->ready());
        $review = ['approved' => false, 'fingerprints' => [], 'energy_label_required' => false,
            'multipack' => 72, 'identifier_scope' => 'unit_bag', 'mpn' => 'G00003249',
            'title_source' => 'trained_algorithmic_media', 'description_source' => 'trained_algorithmic_media',
            'approved_google_image_urls' => [self::URL]];
        $this->assertContains('image_resolution_below_preparation_target', app(MerchantProducts::class)->problems($product, $review, 'fr'));
        // Even an erroneous image allowlist cannot export this site-only native photo.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Product image below preparation target.');
        app(MerchantProducts::class)->xml([[$product, $review, true]], 'fr');
    }

    public function test_image_cache_keeps_the_strict_threshold_separate_from_the_native_site_exception(): void
    {
        $method = new ReflectionMethod(Catalogue::class, 'image');
        $strictFirst = new Catalogue(new FileCatalogueSource);
        $this->assertNull($method->invoke($strictFirst, $this->image()));
        $this->assertSame($this->image(), $method->invoke($strictFirst, $this->image(), 100));
        $this->assertNull($method->invoke($strictFirst, $this->image(), 500));
        $nativeFirst = new Catalogue(new FileCatalogueSource);
        $this->assertSame($this->image(), $method->invoke($nativeFirst, $this->image(), 100));
        $this->assertNull($method->invoke($nativeFirst, $this->image(), 500));
    }

    public function test_native_replacement_requires_the_exact_reference_review_quality_path_and_image_digest(): void
    {
        $entry = (new FileCatalogueSource)->mediaOverrides()[self::SLUG];
        $this->assertSame('verified_native_below_preparation_target', $entry['quality_status']);
        $this->assertSame(self::SHA256, $entry['image']['sha256']);
        $source = $this->source();
        $method = new ReflectionMethod(Catalogue::class, 'replacement');
        $property = new ReflectionProperty(Catalogue::class, 'mediaOverrides');
        foreach ([[], ['reference' => 'OTHER'], ['visual_review' => 'pending'], ['quality_status' => 'unverified'],
            ['image' => ['sha256' => str_repeat('0', 64)]], ['image' => ['sha256' => null]],
            ['image' => ['local_url' => '/media/catalogue/../catalogue/1f865da264a3a61bb8c6e95f.jpg']]] as $change) {
            $catalogue = new Catalogue(new FileCatalogueSource);
            $property->setValue($catalogue, [self::SLUG => array_replace_recursive($entry, $change)]);
            if ($change === []) {
                $this->assertSame($this->image(), $method->invoke($catalogue, $source));
            } else {
                $this->assertNull($method->invoke($catalogue, $source), json_encode($change, JSON_THROW_ON_ERROR));
            }
        }
    }
}
