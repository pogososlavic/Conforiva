<?php

namespace Tests\Feature;

use App\Services\ProductContent;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductContentSeoTest extends TestCase
{
    private string $archive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        Http::preventStrayRequests();
        $this->archive = sys_get_temp_dir().'/home-confort-product-content-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->archive.'/products');
        File::ensureDirectoryExists($this->archive.'/localizations');
        $products = [];
        foreach (['model-one', 'model-two'] as $slug) {
            $products[] = $product = ['slug' => $slug, 'name' => 'Model '.$slug, 'brand' => 'Fixture', 'reference' => 'REF-'.$slug,
                'category' => ['slug' => 'fixture-category', 'name' => 'Fixture category'], 'universe' => 'appliances', 'condition' => 'new',
                'price_cents' => 12345, 'previous_price_cents' => null, 'currency' => 'EUR', 'has_variants' => false,
                'source_url' => 'https://example.com/'.$slug, 'captured_at' => '2026-10-21T00:00:00Z',
                'issues' => [], 'image' => null, 'images' => [], 'variants' => [], 'description' => 'Fixture description',
                'specifications' => [['label' => 'Width', 'value' => '60 cm']]];
            File::put($this->archive.'/products/'.$slug.'.json', json_encode($product));
        }
        File::put($this->archive.'/index.json', json_encode(['meta' => ['complete' => true], 'products' => $products]));
        foreach (['en', 'de'] as $locale) {
            File::put($this->archive.'/localizations/'.$locale.'.json', json_encode(['version' => 1, 'strings' => [
                'Model model-one' => 'Model model-one', 'Model model-two' => 'Model model-two', 'Fixture category' => 'Fixture category',
                'Fixture description' => 'Fixture description', 'Width' => 'Width', '60 cm' => '60 cm',
            ]]));
        }
        config(['catalogue.source' => 'files', 'commerce.enabled' => false, 'commerce.inventory_enabled' => false,
            'dashboard.enabled' => false, 'storefront_protection.mode' => 'off',
            'catalogue.archive_path' => $this->archive.'/index.json', 'catalogue.products_path' => $this->archive.'/products',
            'catalogue.localizations_path' => $this->archive.'/localizations', 'catalogue.media_overrides_path' => $this->archive.'/media-overrides.json']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->archive);
        parent::tearDown();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    public function test_product_links_leave_search_context_out_of_breadcrumbs_related_models_and_selection_guidance(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/produit/model-one?q=old-search&brand=Fixture&page=4')->assertOk();
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(1, $xpath->query('//h1')->length);
            $breadcrumb = $xpath->query('//nav[contains(@class,"breadcrumbs")]/a')->item(1)->getAttribute('href');
            $this->assertStringEndsWith('/'.$locale.'/catalogue', $breadcrumb);
            $related = $xpath->query('//section[@id="related-products"]//a[contains(@class,"product-card-link")]')->item(0)->getAttribute('href');
            $this->assertStringEndsWith('/'.$locale.'/produit/model-two', $related);
            $guideLinks = $xpath->query('//section[@aria-labelledby="selection-heading"]//a');
            $this->assertSame(3, $guideLinks->length);
            $this->assertStringEndsWith('/'.$locale.'/catalogue?category=fixture-category', $guideLinks->item(0)->getAttribute('href'));
            $this->assertStringEndsWith('/'.$locale.'/catalogue?universe=appliances', $guideLinks->item(1)->getAttribute('href'));
            $this->assertStringEndsWith('/'.$locale.'/contact', $guideLinks->item(2)->getAttribute('href'));
            $this->assertSame(__('product-content.selection_heading', [], $locale), $xpath->query('//h2[@id="selection-heading"]')->item(0)->textContent);
            $this->assertStringContainsString('Fixture category', $xpath->query('//h2[@id="related-heading"]')->item(0)->textContent);
            $this->assertSame('Fixture description. Model model-one', $xpath->query('//meta[@name="description"]')->item(0)->getAttribute('content'));
        }
    }

    public function test_missing_prose_meta_uses_only_existing_public_facts_and_never_invents_availability(): void
    {
        $content = app(ProductContent::class);
        $product = ['name' => 'Model A', 'category' => ['name' => 'Refrigerators'], 'reference' => 'MODEL-A'];
        $meta = $content->metaDescription($product, null, [['label' => 'Width', 'value' => '60 cm']]);
        $this->assertStringContainsString('Model A', $meta);
        $this->assertStringContainsString('Refrigerators', $meta);
        $this->assertStringContainsString('MODEL-A', $meta);
        $this->assertStringContainsString('60 cm', $meta);
        $this->assertStringNotContainsString('stock', $meta);
        $this->assertStringNotContainsString('livraison', $meta);
        $this->assertLessThanOrEqual(170, mb_strlen($meta));
        $this->assertSame('Description fiable. Model A', $content->metaDescription($product, '<b>Description fiable</b>'));
        $this->assertSame('Model A présenté avec sa description', $content->metaDescription($product, 'Model A présenté avec sa description'));
    }

    public function test_specialist_categories_receive_relevant_advice_without_changing_their_navigation_department(): void
    {
        $cases = [
            ['mobilier-cheminees-au-bioethanol', 'furniture', 'bioethanol'],
            ['mobilier-fours-a-pizza-barbecues', 'furniture', 'outdoor_cooking'],
            ['chauffage-pieces-detachees', 'heating', 'spare_parts'],
            ['chauffage-outils-entretien', 'heating', 'maintenance'],
        ];
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            foreach ($cases as [$category, $universe, $advice]) {
                $guide = app(ProductContent::class)->guide(['category' => ['slug' => $category], 'universe' => $universe]);
                $this->assertSame($universe, $guide['universe']);
                $this->assertSame(__('product-content.guides.'.$advice), $guide['advice']);
                $this->assertNotSame(__('product-content.guides.'.$universe), $guide['advice']);
                $this->assertStringNotContainsString('product-content.', $guide['advice']);
            }
            $guide = app(ProductContent::class)->guide(['category' => ['slug' => 'other-furniture'], 'universe' => 'furniture']);
            $this->assertSame(__('product-content.guides.furniture'), $guide['advice']);
        }
    }
}
