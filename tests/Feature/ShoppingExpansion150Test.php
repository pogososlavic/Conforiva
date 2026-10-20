<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CatalogueOfficialFacts;
use App\Services\FileCatalogueSource;
use App\Services\MerchantProducts;
use App\Services\ProductEnergyLabels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShoppingExpansion150Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        Http::preventStrayRequests();
        config(['catalogue.source' => 'files', 'dashboard.enabled' => false,
            'commerce.inventory_enabled' => false, 'merchant.enabled' => false, 'seo.indexing_enabled' => false]);
    }

    private function rules(): array
    {
        return array_filter(json_decode(file_get_contents(resource_path('data/official-product-facts.json')), true, flags: JSON_THROW_ON_ERROR)['products'],
            static fn (array $rule): bool => ($rule['shopping_preparation_batch'] ?? null) === 'shopping150-20261020');
    }

    private function source(string $slug): array
    {
        return json_decode(file_get_contents(storage_path('app/catalogue/products/'.$slug.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_all_150_offers_keep_guarded_identity_whole_offer_prices_and_matching_localized_cards(): void
    {
        $rules = $this->rules();
        $this->assertCount(150, $rules);
        $this->assertArrayNotHasKey('lg-gbbs726amb-combine', $rules);
        $this->assertArrayHasKey('lg-gbbs323cpy-combine', $rules);
        $merchant = app(MerchantProducts::class);
        $reviews = $merchant->reviews();
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $catalogue = new Catalogue(new FileCatalogueSource);
            $cards = $catalogue->products()->keyBy('slug');
            foreach ($rules as $slug => $rule) {
                $source = $this->source($slug);
                $product = $catalogue->product($slug);
                $this->assertNotNull($product, $slug);
                $this->assertSame($rule['source_sha256'], CatalogueOfficialFacts::sourceHash($source), $slug);
                $this->assertSame($source['reference'], $product['reference']);
                $this->assertSame($source['currency'], $product['currency']);
                $this->assertSame($source['price_cents'], $product['promotion']['base_price_cents'] ?? $product['price_cents']);
                $this->assertSame($rule['names'][$locale], $product['name']);
                $this->assertSame($rule['descriptions'][$locale], $product['description']);
                $this->assertSame('trained_algorithmic_media', $product['title_source']);
                $this->assertSame('trained_algorithmic_media', $product['description_source']);
                $this->assertFalse($product['description_hidden']);
                $this->assertLessThanOrEqual(150, mb_strlen($product['name']));
                $this->assertLessThanOrEqual(5000, mb_strlen($product['description']));
                $this->assertNotEmpty($product['description']);
                $this->assertFalse($product['has_variants']);
                $this->assertSame([], $product['variants']);
                $this->assertSame([], $product['issues'], $slug);
                $this->assertNotEmpty($product['images']);
                $this->assertSame(['merchant_review_required'], $merchant->problems($product, $reviews[$slug], $locale), $slug);
                $this->assertNotEmpty($merchant->reviewedImages($product, $reviews[$slug]), $slug);
                $card = $cards->get($slug);
                $this->assertNotNull($card, $slug);
                foreach (['reference', 'name', 'category', 'price_cents', 'currency'] as $field) {
                    $this->assertSame($product[$field], $card[$field], $slug.' '.$field);
                }
                $this->assertArrayNotHasKey('description', $card);
                $this->assertArrayNotHasKey('description_source', $card);
                $this->assertSame($product['sales_pack'] ?? null, $card['sales_pack'] ?? null);
                foreach (['quantity', 'stock', 'reserved'] as $privateStock) {
                    $this->assertArrayNotHasKey($privateStock, $product);
                    $this->assertArrayNotHasKey($privateStock, $card);
                }
            }
            $this->assertFalse($catalogue->sourceUnavailable());
            $this->assertFalse($catalogue->translationUnavailable());
        }
        foreach (['fr' => ['Type de construction', 'Pose libre'], 'en' => ['Design type', 'Freestanding'], 'de' => ['Bauart', 'Freistehend']] as $locale => [$label, $value]) {
            app()->setLocale($locale);
            $catalogue = new Catalogue(new FileCatalogueSource);
            foreach (['lg-gbbs312aev-combine', 'lg-gbbs323cpy-combine'] as $slug) {
                $this->assertContains(['label' => $label, 'value' => $value], $catalogue->product($slug)['specifications']);
            }
        }
        $this->assertFalse(app(MerchantProducts::class)->ready());
    }

    public function test_two_exact_washer_dryers_are_classified_consistently_without_mutating_the_archives(): void
    {
        foreach (['fr' => 'Lave-linge séchants', 'en' => 'Washer-dryers', 'de' => 'Waschtrockner'] as $locale => $label) {
            app()->setLocale($locale);
            $catalogue = new Catalogue(new FileCatalogueSource);
            $cards = $catalogue->products()->keyBy('slug');
            foreach (['lg-f164x71wsta-lave-linge', 'lg-f164x76bsta-lave-linge'] as $slug) {
                $this->assertArrayHasKey($slug, $this->rules());
                $expected = ['slug' => 'lavage-lave-linge-sechant', 'name' => $label];
                $this->assertSame($expected, $catalogue->product($slug)['category']);
                $this->assertSame($expected, $cards->get($slug)['category']);
                $this->assertSame('lavage-lave-linge', $this->source($slug)['category']['slug']);
            }
        }
    }

    public function test_energy_documents_and_verified_identifiers_belong_to_the_selected_exact_products(): void
    {
        app()->setLocale('fr');
        $catalogue = new Catalogue(new FileCatalogueSource);
        foreach ($this->rules() as $slug => $rule) {
            $product = $catalogue->product($slug);
            if (isset($rule['sales_pack'])) {
                $this->assertNull(app(ProductEnergyLabels::class)->forProduct($product, 'fr'));

                continue;
            }
            $this->assertTrue($product['manufacturer']['verified']);
            $this->assertNotEmpty($product['manufacturer']['source_url']);
            $this->assertSame('https', parse_url($product['manufacturer']['source_url'], PHP_URL_SCHEME));
            if (isset($product['manufacturer']['gtin'])) {
                $this->assertTrue(MerchantProducts::validGtin($product['manufacturer']['gtin']), $slug);
            } else {
                $this->assertNotEmpty($product['manufacturer']['mpn']);
            }
            $entry = config('product-energy.'.$slug);
            $this->assertSame($product['reference'], $entry['reference']);
            $this->assertNotNull(MerchantProducts::eprelCode($entry['eprel'] ?? null), $slug);
            foreach (['fr', 'en', 'de'] as $locale) {
                $this->assertNotEmpty(app(ProductEnergyLabels::class)->forProductLabels($product, $locale), $slug.' '.$locale);
            }
        }
    }

    public function test_retailer_lots_keep_their_scope_and_only_qualified_lots_remain_selected(): void
    {
        $expected = [
            'granules-de-bois-piveteau-hp-72-sacs-de-15kg' => ['count' => 72, 'mpn' => 'G00003249'],
            'granules-de-bois-crepito-palette-72-sacs-de-15-kg' => ['count' => 72, 'mpn' => 'CG15'],
            'pellet-moulin-bois-energie-palette-de-65-sacs-de-15-kg' => ['count' => 65, 'gtin' => '3760219800033'],
            'pellet-badger-palette-de-65-sacs-de-15-kg' => ['count' => 65],
        ];
        app()->setLocale('fr');
        $catalogue = new Catalogue(new FileCatalogueSource);
        foreach ($expected as $slug => $facts) {
            if (in_array($slug, ['granules-de-bois-piveteau-hp-72-sacs-de-15kg', 'pellet-badger-palette-de-65-sacs-de-15-kg'], true)) {
                $this->assertArrayNotHasKey($slug, $this->rules());
            } else {
                $this->assertArrayHasKey($slug, $this->rules());
            }
            $product = $catalogue->product($slug);
            $this->assertSame(['type' => 'merchant_multipack', 'count' => $facts['count'], 'unit_weight_kg' => 15,
                'identifier_scope' => 'unit_bag', 'owner_confirmed_on' => '2026-10-20'], $product['sales_pack']);
            $this->assertSame(['multipack' => $facts['count'], 'unit_pricing_measure' => ($facts['count'] * 15).'kg',
                'unit_pricing_base_measure' => '1kg'], MerchantProducts::salesPack($product));
            $review = ['approved' => false, 'fingerprints' => [], 'energy_label_required' => false,
                'multipack' => $facts['count'], 'identifier_scope' => 'unit_bag',
                'title_source' => 'trained_algorithmic_media', 'description_source' => 'trained_algorithmic_media'];
            if (isset($facts['mpn']) || isset($facts['gtin'])) {
                $this->assertTrue($product['manufacturer']['verified']);
                $this->assertSame('unit_bag', $product['manufacturer']['identifier_scope']);
                foreach (['mpn', 'gtin'] as $field) {
                    if (isset($facts[$field])) {
                        $this->assertSame($facts[$field], $product['manufacturer'][$field]);
                    }
                    if (isset($product['manufacturer'][$field])) {
                        $review[$field] = $product['manufacturer'][$field];
                    }
                }
            } else {
                $this->assertFalse($product['manufacturer']['verified'] ?? false);
                $this->assertEmpty($product['manufacturer']['gtin'] ?? null);
                $this->assertEmpty($product['manufacturer']['mpn'] ?? null);
            }
            $this->assertArrayNotHasKey('identifier_exists', $review);
            $problems = app(MerchantProducts::class)->problems($product, $review, 'fr');
            $this->assertContains('merchant_review_required', $problems);
            $this->assertNotContains('multipack_review_required', $problems);
            $this->assertNotContains('multipack_identifier_mismatch', $problems);
            if (! isset($facts['mpn']) && ! isset($facts['gtin'])) {
                $this->assertContains('manufacturer_identifiers_required', $problems);
            } else {
                $this->assertNotContains('manufacturer_identifiers_required', $problems);
            }
        }
        $this->assertFalse(app(MerchantProducts::class)->ready());
    }

    public function test_active_manifest_has_current_official_evidence_and_excludes_unconfirmed_models_without_claiming_they_are_discontinued(): void
    {
        $manifest = json_decode(file_get_contents(storage_path('app/private/google/shopping150-selection.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(4, $manifest['version']);
        $this->assertSame('shopping150-lifecycle-20261020', $manifest['revision']);
        $this->assertFalse($manifest['ready_to_submit']);
        $this->assertFalse($manifest['google_approved']);
        $this->assertCount(150, $manifest['products']);
        $slugs = array_column($manifest['products'], 'slug');
        $this->assertCount(150, array_unique($slugs));
        $ruleSlugs = array_keys($this->rules());
        sort($slugs);
        sort($ruleSlugs);
        $this->assertSame($ruleSlugs, $slugs);
        $verifiedFiles = [];
        $merchant = app(MerchantProducts::class);
        $reviews = $merchant->reviews();
        app()->setLocale('fr');
        $catalogue = new Catalogue(new FileCatalogueSource);
        foreach ($manifest['products'] as $row) {
            $slug = $row['slug'];
            $product = $catalogue->product($slug);
            $this->assertSame($row['reference'], $product['reference'], $slug);
            $this->assertSame($row['name'], $product['name'], $slug);
            $this->assertSame($row['price_cents'], $product['price_cents'], $slug);
            $this->assertSame($row['currency'], $product['currency'], $slug);
            $this->assertFalse($reviews[$slug]['approved']);
            $this->assertSame([], $reviews[$slug]['fingerprints']);
            $evidence = $row['current_commercialisation_evidence'];
            $this->assertSame('current_commercialisation_confirmed', $evidence['status'], $slug);
            $this->assertSame($slug, $evidence['audit_slug']);
            $this->assertSame($manifest['checked_on'], $evidence['checked_on']);
            $this->assertFalse($evidence['manufacturer_production_continuity_guaranteed']);
            $this->assertNotEmpty($evidence['sources'], $slug);
            $files = [['archive_path' => $evidence['audit_path'], 'sha256' => $evidence['audit_sha256'], 'compression' => 'none']];
            foreach ($evidence['sources'] as $officialSource) {
                $this->assertSame('https', parse_url($officialSource['url'], PHP_URL_SCHEME));
                if (($officialSource['evidence_method'] ?? null) === 'official_web_reader_not_raw_html') {
                    $this->assertSame('froid-rtg785nhd-haier-multi-portes', $slug);
                    $this->assertNull($officialSource['http_status']);
                    $this->assertSame($manifest['checked_on'], $officialSource['checked_on']);
                } else {
                    $this->assertSame(200, $officialSource['http_status']);
                    $this->assertNotEmpty($officialSource['retrieved_at_utc']);
                }
                $files[] = $officialSource;
            }
            $files = array_merge($files, $evidence['supporting_artifacts'] ?? []);
            foreach ($files as $file) {
                $relative = $file['archive_path'];
                $this->assertStringStartsWith('storage/app/private/lifecycle150-20261020/', $relative);
                $this->assertStringNotContainsString('..', $relative);
                $key = $relative.':'.$file['sha256'];
                if (isset($verifiedFiles[$key])) {
                    continue;
                }
                $path = base_path($relative);
                $this->assertFileExists($path, $slug);
                $contents = file_get_contents($path);
                if ($file['compression'] === 'gzip') {
                    $this->assertSame($file['gzip_sha256'], hash('sha256', $contents), $relative);
                    $contents = gzdecode($contents);
                    $this->assertIsString($contents, $relative);
                }
                $this->assertSame($file['sha256'], hash('sha256', $contents), $relative);
                $verifiedFiles[$key] = true;
            }
        }
        $this->assertCount(14, $manifest['lifecycle_exclusions']);
        $confirmedStopped = [];
        foreach ($manifest['lifecycle_exclusions'] as $excluded) {
            $slug = $excluded['slug'];
            $this->assertNotContains($slug, $slugs);
            $this->assertContains($excluded['replacement_slug'], $slugs);
            $this->assertFileExists(storage_path('app/catalogue/products/'.$slug.'.json'));
            $this->assertTrue($excluded['catalogue_product_retained']);
            $this->assertFalse($reviews[$slug]['shopping_preparation']['selection_active']);
            $this->assertFalse($reviews[$slug]['approved']);
            $this->assertSame([], $reviews[$slug]['fingerprints']);
            if ($excluded['manufacturer_discontinued_confirmed']) {
                $this->assertSame('discontinued', $excluded['observed_status']);
                $confirmedStopped[] = $slug;
            } else {
                $this->assertSame('current_manufacturer_range_not_confirmed_replaced_preventively', $excluded['reason']);
                $this->assertNotSame('discontinued', $excluded['observed_status']);
            }
        }
        $this->assertCount(4, $confirmedStopped);
        $this->assertContains('froid-kge36alca-bosch-combines-congelateur-en-bas', $slugs);
        $this->assertContains('froid-fsp164ew-fagor-une-porte', $slugs);
    }
}
