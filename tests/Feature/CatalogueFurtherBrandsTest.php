<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CatalogueOfficialFacts;
use App\Services\FileCatalogueSource;
use App\Services\MerchantProducts;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogueFurtherBrandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        config(['catalogue.source' => 'files', 'dashboard.enabled' => false, 'commerce.inventory_enabled' => false]);
    }

    private function source(string $slug): array
    {
        return json_decode(file_get_contents(storage_path('app/catalogue/products/'.$slug.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_verified_identifiers_are_visible_in_all_locales_and_do_not_approve_merchant_offers(): void
    {
        $rules = json_decode(file_get_contents(resource_path('data/official-product-facts.json')), true, flags: JSON_THROW_ON_ERROR)['products'];
        $rules = array_filter($rules, static fn (array $rule): bool => ($rule['import_batch'] ?? null) === 'catalogue-next-20261020' && isset($rule['manufacturer']));
        $this->assertNotEmpty($rules);
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $catalogue = new Catalogue(new FileCatalogueSource);
            foreach ($rules as $slug => $rule) {
                $source = $this->source($slug);
                $product = $catalogue->product($slug);
                $this->assertNotNull($product, $slug);
                $this->assertSame($rule['source_sha256'], CatalogueOfficialFacts::sourceHash($source));
                $this->assertSame(['verified' => true] + $rule['manufacturer'], $product['manufacturer']);
                $this->assertSame($source['price_cents'], $product['promotion']['base_price_cents'] ?? $product['price_cents']);
                $this->assertSame($source['currency'], $product['currency']);
                $this->assertSame($source['reference'], $product['reference']);
                foreach ($rule['specifications'][$locale] as $fact) {
                    $this->assertContains($fact, $product['specifications']);
                }
                if (isset($rule['manufacturer']['gtin'])) {
                    $this->assertTrue(MerchantProducts::validGtin($rule['manufacturer']['gtin']));
                } else {
                    $this->assertArrayNotHasKey('gtin', $product['manufacturer']);
                }
            }
            $this->assertFalse($catalogue->sourceUnavailable());
            $this->assertFalse($catalogue->translationUnavailable());
        }
        $this->assertFalse(app(MerchantProducts::class)->ready());
    }

    public function test_complete_article_codes_keep_their_leading_zeros_and_distinct_finishes(): void
    {
        $catalogue = new Catalogue(new FileCatalogueSource);
        $expected = [
            '1293400' => ['mpn' => '001293400'],
            '05 419 08 19' => ['mpn' => '0541908190000', 'gtin' => '9002742460778'],
            '06 018 70 01' => ['mpn' => '0601870010000', 'gtin' => '9002742437886'],
            '06 018 70 10' => ['mpn' => '0601870100000', 'gtin' => '9002742436711'],
            'S01-574' => ['mpn' => 'S01-574'],
            'S01-560' => ['mpn' => 'S01-560'],
            '18310' => ['mpn' => '18310', 'gtin' => '8426964183108'],
        ];
        foreach ($expected as $reference => $facts) {
            $summary = $catalogue->products()->firstWhere('reference', (string) $reference);
            $this->assertNotNull($summary, (string) $reference);
            $product = $catalogue->product($summary['slug']);
            foreach ($facts as $field => $value) {
                $this->assertSame($value, $product['manufacturer'][$field]);
            }
        }
    }

    public function test_verified_pallets_clear_their_packaging_reserve_but_the_marina_bundle_stays_partial(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $catalogue = new Catalogue(new FileCatalogueSource);
            foreach (['granules-de-bois-piveteau-hp-72-sacs-de-15kg', 'pellet-badger-palette-de-65-sacs-de-15-kg'] as $slug) {
                $product = $catalogue->product($slug);
                $this->assertNotContains('missing_specifications', $product['issues']);
                $this->assertNotContains('manufacturer_scope_requires_review', $product['issues']);
                $pack = MerchantProducts::salesPack($product);
                $this->assertNotNull($pack, 'These owner-confirmed lots are assembled by the retailer.');
                if ($slug === 'granules-de-bois-piveteau-hp-72-sacs-de-15kg') {
                    $this->assertSame(72, $pack['multipack']);
                    $this->assertTrue($product['manufacturer']['verified']);
                    $this->assertSame('G00003249', $product['manufacturer']['mpn']);
                    $this->assertSame('unit_bag', $product['manufacturer']['identifier_scope']);
                    $this->assertEmpty($product['manufacturer']['gtin'] ?? null);
                } else {
                    $this->assertSame(65, $pack['multipack']);
                    $this->assertFalse($product['manufacturer']['verified'] ?? false, 'The Badger bag identifier remains unknown.');
                    $this->assertEmpty($product['manufacturer']['gtin'] ?? null);
                    $this->assertEmpty($product['manufacturer']['mpn'] ?? null);
                }
                $this->assertSame([], $this->source($slug)['specifications']);
            }
            $slug = 'pack-poele-a-granules-etanche-kit-ventouse-interstoves-marina-14-kw';
            $product = $catalogue->product($slug);
            $this->assertContains('missing_specifications', $product['issues']);
            $this->assertContains('manufacturer_scope_requires_review', $product['issues']);
            $this->assertFalse($product['manufacturer']['verified'] ?? false);
            $this->assertCount(4, $product['specifications']);
            $this->assertStringNotContainsString('304', json_encode($product['specifications'], JSON_THROW_ON_ERROR));
        }
    }

    public function test_unconfirmed_generations_and_changed_archives_never_inherit_reviewed_identifiers(): void
    {
        $catalogue = new Catalogue(new FileCatalogueSource);
        foreach (['haas-sohn-montegrotto-iii-poele-a-bois-noir-woodstone', 'haas-sohn-montegrotto-iii-poele-a-bois-noir-serpentino',
            'haas-sohn-solido-acier-poele-a-bois-avec-plaque-de-cuisson-avec-compartiment-ran'] as $slug) {
            $this->assertFalse($catalogue->product($slug)['manufacturer']['verified'] ?? false);
        }
        $slug = 'chauffage-18310-poeles-a-bois';
        $source = $this->source($slug);
        foreach ([['reference' => '18300'], ['price_cents' => $source['price_cents'] + 1], ['has_variants' => true],
            ['variants' => [['sku' => 'OTHER']]], ['name' => 'Another version'], ['images' => []]] as $change) {
            $changed = array_replace($source, $change);
            $this->assertSame($changed, CatalogueOfficialFacts::apply($changed, $changed, 'fr'));
        }
        $hybrid = $catalogue->product('poele-mixte-hybride-ventile-bois-granules-bronpi-hybrid-10-kw');
        $this->assertContains('missing_specifications', $hybrid['issues']);
        $this->assertSame([], $hybrid['specifications']);
    }
}
