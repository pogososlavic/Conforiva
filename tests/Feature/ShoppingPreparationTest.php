<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CatalogueOfficialFacts;
use App\Services\FileCatalogueSource;
use App\Services\MerchantProducts;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShoppingPreparationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        config(['catalogue.source' => 'files', 'dashboard.enabled' => false, 'commerce.inventory_enabled' => false]);
    }

    private function rules(): array
    {
        return array_filter(json_decode(file_get_contents(resource_path('data/official-product-facts.json')), true, flags: JSON_THROW_ON_ERROR)['products'],
            static fn (array $rule): bool => ($rule['last_verified_batch'] ?? null) === 'dashboard-shopping-20261020');
    }

    private function source(string $slug): array
    {
        return json_decode(file_get_contents(storage_path('app/catalogue/products/'.$slug.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_original_copy_is_visible_in_each_locale_and_keeps_exact_source_identity_and_base_prices(): void
    {
        $this->assertCount(12, $this->rules());
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $catalogue = new Catalogue(new FileCatalogueSource);
            foreach ($this->rules() as $slug => $rule) {
                $source = $this->source($slug);
                $product = $catalogue->product($slug);
                $this->assertSame($rule['source_sha256'], CatalogueOfficialFacts::sourceHash($source));
                $this->assertSame($rule['names'][$locale], $product['name']);
                $this->assertSame($rule['descriptions'][$locale], $product['description']);
                $this->assertSame('trained_algorithmic_media', $product['title_source']);
                $this->assertSame('trained_algorithmic_media', $product['description_source']);
                $this->assertFalse($product['description_hidden']);
                $this->assertSame($source['reference'], $product['reference']);
                $this->assertSame($source['price_cents'], $product['promotion']['base_price_cents'] ?? $product['price_cents']);
                $this->assertNotEmpty($product['images']);
            }
        }
    }

    public function test_compact_cards_get_the_reviewed_title_without_inheriting_a_full_description(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $catalogue = new Catalogue(new FileCatalogueSource);
            foreach ($this->rules() as $slug => $rule) {
                $card = $catalogue->products()->firstWhere('slug', $slug);
                $this->assertSame($rule['names'][$locale], $card['name']);
                $this->assertArrayNotHasKey('description', $card);
                $this->assertArrayNotHasKey('description_source', $card);
            }
        }
    }

    public function test_modified_sources_variants_and_unsupported_locales_cannot_reuse_the_copy(): void
    {
        foreach ($this->rules() as $slug => $rule) {
            $source = $this->source($slug);
            foreach ([['reference' => 'OTHER'], ['name' => 'Other model'], ['price_cents' => $source['price_cents'] + 1],
                ['images' => []], ['has_variants' => true], ['variants' => [['sku' => 'OTHER']]]] as $change) {
                $changed = array_replace($source, $change);
                $this->assertSame($changed, CatalogueOfficialFacts::apply($changed, $changed, 'fr'));
            }
            $this->assertSame($source, CatalogueOfficialFacts::apply($source, $source, 'es'));
        }
    }

    public function test_regulated_corrections_do_not_invent_a_pallet_identifier_or_open_the_feed(): void
    {
        $catalogue = new Catalogue(new FileCatalogueSource);
        $lg = $catalogue->product('lg-gft61pzcse-une-porte');
        $this->assertNotContains('1932', array_column($lg['specifications'], 'value'));
        $this->assertContains('1932', array_column($this->source($lg['slug'])['specifications'], 'value'));
        $indesit = $catalogue->product('froid-ink25402s4e-indesit-combines-congelateur-en-bas');
        $this->assertContains(['label' => 'Classe de perception sonore (2021)', 'value' => 'C'], $indesit['specifications']);
        $this->assertContains(['label' => 'Classe de perception sonore (2021)', 'value' => 'B'], $this->source($indesit['slug'])['specifications']);
        foreach (['granules-de-bois-piveteau-hp-72-sacs-de-15kg', 'pellet-badger-palette-de-65-sacs-de-15-kg',
            'granules-de-bois-crepito-palette-72-sacs-de-15-kg', 'pellet-moulin-bois-energie-palette-de-65-sacs-de-15-kg'] as $slug) {
            $this->assertArrayHasKey($slug, $this->rules());
            $product = $catalogue->product($slug);
            // The owner now assembles these lots. Documented bag identifiers retain their unit scope.
            $pack = MerchantProducts::salesPack($product);
            $this->assertNotNull($pack);
            $this->assertSame('unit_bag', $product['sales_pack']['identifier_scope']);
            $review = ['approved' => false, 'fingerprints' => [], 'energy_label_required' => false,
                'multipack' => $pack['multipack'], 'identifier_scope' => 'unit_bag',
                'title_source' => 'trained_algorithmic_media', 'description_source' => 'trained_algorithmic_media'];
            foreach (['gtin', 'mpn'] as $field) {
                if (isset($product['manufacturer'][$field])) {
                    $review[$field] = $product['manufacturer'][$field];
                }
            }
            $problems = app(MerchantProducts::class)->problems($product, $review, 'fr');
            if ($slug === 'pellet-badger-palette-de-65-sacs-de-15-kg') {
                $this->assertFalse($product['manufacturer']['verified'] ?? false);
                $this->assertContains('manufacturer_identifiers_required', $problems);
            } else {
                $this->assertTrue($product['manufacturer']['verified']);
                $this->assertSame('unit_bag', $product['manufacturer']['identifier_scope']);
                $this->assertNotContains('manufacturer_identifiers_required', $problems);
            }
            $this->assertArrayNotHasKey('identifier_exists', $review);
            $this->assertNotContains('multipack_review_required', $problems);
            $this->assertNotContains('multipack_identifier_mismatch', $problems);
            $this->assertContains('merchant_review_required', $problems);
            $this->assertNotContains('energy_label_review_required', $problems);
        }
        $this->assertFalse(app(MerchantProducts::class)->ready());
    }
}
