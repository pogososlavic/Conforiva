<?php

namespace Tests\Feature;

use App\Services\MerchantProducts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MerchantMultipackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        Http::preventStrayRequests();
        config(['dashboard.enabled' => false, 'commerce.inventory_enabled' => false,
            'merchant.enabled' => false, 'seo.indexing_enabled' => false,
            'seo.public_url' => 'https://shop.example', 'app.url' => 'https://shop.example']);
    }

    private function product(): array
    {
        return ['slug' => 'fixture-seller-lot', 'reference' => 'FIXTURE-LOT', 'name' => 'Lot of 65 bags',
            'description' => '65 identical bags, 15 kg each, assembled by the retailer.', 'brand' => 'Fixture brand',
            'universe' => 'firewood', 'category' => ['slug' => 'pellets', 'name' => 'Pellets'],
            'condition' => 'new', 'price_cents' => 32500, 'currency' => 'EUR', 'has_variants' => false,
            'variants' => [], 'issues' => [], 'specifications' => [],
            'manufacturer' => ['verified' => true, 'identifier_scope' => 'unit_bag', 'gtin' => '4006381333931', 'mpn' => 'BAG-FIXTURE'],
            'sales_pack' => ['type' => 'merchant_multipack', 'count' => 65, 'unit_weight_kg' => 15,
                'identifier_scope' => 'unit_bag', 'owner_confirmed_on' => '2026-10-20'],
            'images' => [['local_url' => '/media/catalogue/'.str_repeat('a', 24).'.jpg'],
                ['local_url' => '/media/catalogue/'.str_repeat('b', 24).'.jpg']]];
    }

    private function review(array $product): array
    {
        return ['approved' => false, 'fingerprints' => [], 'gtin' => '4006381333931', 'mpn' => 'BAG-FIXTURE',
            'multipack' => 65, 'identifier_scope' => 'unit_bag', 'energy_label_required' => false,
            'title_source' => 'default', 'description_source' => 'default',
            'approved_google_image_urls' => [$product['images'][0]['local_url']]];
    }

    public function test_unit_identifiers_are_exported_only_with_count_and_whole_lot_price(): void
    {
        $product = $this->product();
        $review = $this->review($product);
        $merchant = app(MerchantProducts::class);
        $this->assertSame(['merchant_review_required'], $merchant->problems($product, $review, 'fr'));
        $xml = $merchant->xml([[$product, $review, true]], 'fr');
        $this->assertStringContainsString('<g:multipack>65</g:multipack>', $xml);
        $this->assertStringContainsString('<g:unit_pricing_measure>975kg</g:unit_pricing_measure>', $xml);
        $this->assertStringContainsString('<g:unit_pricing_base_measure>1kg</g:unit_pricing_base_measure>', $xml);
        $this->assertStringContainsString('<g:price>325.00 EUR</g:price>', $xml);
        $this->assertStringContainsString('<g:gtin>4006381333931</g:gtin>', $xml);
        $this->assertStringNotContainsString('additional_image_link', $xml);
        $this->assertStringNotContainsString(str_repeat('b', 24), $xml);
        $this->assertFalse($merchant->ready());
    }

    public function test_missing_count_mismatched_unit_identifiers_and_unconfirmed_scopes_stay_blocked(): void
    {
        $product = $this->product();
        $merchant = app(MerchantProducts::class);
        foreach ([['multipack' => 72], ['multipack' => '65'], ['identifier_scope' => 'whole_pallet']] as $change) {
            $review = array_replace($this->review($product), $change);
            $this->assertContains('multipack_review_required', $merchant->problems($product, $review, 'fr'));
        }
        $review = array_replace($this->review($product), ['mpn' => 'OTHER-BAG']);
        $this->assertContains('multipack_identifier_mismatch', $merchant->problems($product, $review, 'fr'));
        $review = $this->review($product);
        unset($review['gtin'], $review['mpn']);
        $review['identifier_exists'] = false;
        $this->assertContains('multipack_identifiers_required', $merchant->problems($product, $review, 'fr'));
        $originalHash = MerchantProducts::fingerprint($product);
        $product['sales_pack']['count'] = 72;
        $this->assertNotSame($originalHash, MerchantProducts::fingerprint($product));
        $product['sales_pack']['count'] = 1;
        $this->assertNull(MerchantProducts::salesPack($product));
        $product['sales_pack']['count'] = 65;
        unset($product['sales_pack']['owner_confirmed_on']);
        $this->assertNull(MerchantProducts::salesPack($product));
    }

    public function test_image_whitelist_rejects_unknown_duplicate_and_non_primary_urls(): void
    {
        $product = $this->product();
        $merchant = app(MerchantProducts::class);
        foreach ([['https://example.invalid/image.jpg'], [$product['images'][1]['local_url']],
            [$product['images'][0]['local_url'], $product['images'][0]['local_url']], []] as $urls) {
            $review = array_replace($this->review($product), ['approved_google_image_urls' => $urls]);
            $this->assertSame([], $merchant->reviewedImages($product, $review));
            $this->assertContains('image_review_required', $merchant->problems($product, $review, 'fr'));
        }
        $this->assertSame([$product['images'][0]], $merchant->reviewedImages($product, $this->review($product)));
        $review = $this->review($product);
        unset($review['approved_google_image_urls']);
        $this->assertSame($product['images'], $merchant->reviewedImages($product, $review));
    }

    public function test_a_native_site_photo_below_target_cannot_become_a_google_image(): void
    {
        $product = $this->product();
        $product['images'][0] += ['width' => 473, 'height' => 473];
        $review = $this->review($product);
        $merchant = app(MerchantProducts::class);
        $this->assertContains('image_resolution_below_preparation_target', $merchant->problems($product, $review, 'fr'));
        $this->expectException(\RuntimeException::class);
        $merchant->xml([[$product, $review, true]], 'fr');
    }

    public function test_serializer_refuses_an_inconsistent_retailer_lot(): void
    {
        $product = $this->product();
        $review = array_replace($this->review($product), ['multipack' => 1]);
        $this->expectException(\RuntimeException::class);
        app(MerchantProducts::class)->xml([[$product, $review, true]], 'fr');
    }

    public function test_serializer_cannot_fall_back_to_unreviewed_images(): void
    {
        $product = $this->product();
        $review = array_replace($this->review($product), ['approved_google_image_urls' => ['https://example.invalid/image.jpg']]);
        $this->expectException(\RuntimeException::class);
        app(MerchantProducts::class)->xml([[$product, $review, true]], 'fr');
    }
}
