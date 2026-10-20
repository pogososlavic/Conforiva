<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CatalogueOfficialFacts;
use App\Services\FileCatalogueSource;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogueOtherBrandsTest extends TestCase
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
            static fn (array $rule): bool => ($rule['import_batch'] ?? null) === 'other-brands-20261020');
    }

    private function source(string $slug): array
    {
        return json_decode(file_get_contents(storage_path('app/catalogue/products/'.$slug.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_new_official_facts_are_visible_in_each_locale_without_altering_prices_or_source_archives(): void
    {
        $rules = $this->rules();
        $this->assertNotEmpty($rules);
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $catalogue = new Catalogue(new FileCatalogueSource);
            foreach ($rules as $slug => $rule) {
                $source = $this->source($slug);
                $this->assertSame($rule['source_sha256'], CatalogueOfficialFacts::sourceHash($source));
                $product = $catalogue->product($slug);
                $this->assertNotNull($product, $slug);
                foreach ($rule['specifications'][$locale] as $fact) {
                    $this->assertContains($fact, $product['specifications'], $slug.' '.$locale);
                }
                if ($rule['specification_mode'] === 'complete_empty') {
                    $this->assertNotContains('missing_specifications', $product['issues']);
                }
                $this->assertSame($source['currency'], $product['currency']);
                $this->assertSame($source['reference'], $product['reference']);
                $this->assertSame($rule['source_sha256'], CatalogueOfficialFacts::sourceHash($this->source($slug)));
            }
            $this->assertFalse($catalogue->sourceUnavailable());
            $this->assertFalse($catalogue->translationUnavailable());
        }
    }

    public function test_bag_collectors_and_bundle_components_do_not_complete_the_unverified_offer(): void
    {
        $partial = array_filter($this->rules(), static fn (array $rule): bool => $rule['specification_mode'] === 'component_facts');
        $this->assertNotEmpty($partial);
        foreach ($partial as $slug => $rule) {
            $source = $this->source($slug);
            $sourceProduct = $source;
            $sourceProduct['issues'] = array_values(array_unique(array_merge($source['issues'], ['missing_specifications', 'unrelated_review'])));
            foreach (['fr', 'en', 'de'] as $locale) {
                $product = CatalogueOfficialFacts::apply($source, $sourceProduct, $locale);
                $this->assertContains('missing_specifications', $product['issues']);
                $this->assertContains('manufacturer_scope_requires_review', $product['issues']);
                $this->assertContains('unrelated_review', $product['issues']);
                $this->assertArrayNotHasKey('manufacturer', $product, 'No bag GTIN may identify a pallet or kit.');
                $this->assertSame($source['price_cents'], $product['price_cents']);
                $this->assertSame($source['images'], $product['images']);
            }
        }
    }

    public function test_confirmed_brands_and_corrected_title_agree_between_cards_and_details_and_fail_closed_on_edits(): void
    {
        $editorial = array_filter($this->rules(), static fn (array $rule): bool => isset($rule['brand']) || isset($rule['names']));
        $this->assertNotEmpty($editorial);
        foreach (['granules-de-bois-piveteau-hp-72-sacs-de-15kg', 'palette-de-pellets-total-premium', 'froid-whk25364w4e-whirlpool-combines-congelateur-en-bas'] as $slug) {
            $this->assertArrayHasKey($slug, $editorial);
        }
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $catalogue = new Catalogue(new FileCatalogueSource);
            foreach ($editorial as $slug => $rule) {
                $source = $this->source($slug);
                $card = $catalogue->products()->firstWhere('slug', $slug);
                $detail = $catalogue->product($slug);
                $this->assertSame($detail['brand'], $card['brand']);
                $this->assertSame($detail['name'], $card['name']);
                if (isset($rule['brand'])) {
                    $this->assertSame($rule['brand'], $detail['brand']);
                }
                if (isset($rule['names'])) {
                    $this->assertSame($rule['names'][$locale], $detail['name']);
                    $this->assertSame('trained_algorithmic_media', $detail['title_source']);
                }
                foreach ([['price_cents' => $source['price_cents'] + 1], ['images' => []], ['name' => 'Edited source title'],
                    ['reference' => 'OTHER'], ['has_variants' => true]] as $change) {
                    $changed = array_replace($source, $change);
                    $this->assertSame($changed, CatalogueOfficialFacts::apply($changed, $changed, $locale));
                }
            }
        }
    }
}
