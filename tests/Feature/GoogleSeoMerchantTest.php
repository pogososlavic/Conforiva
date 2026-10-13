<?php

namespace Tests\Feature;

use App\Http\Controllers\InformationController;
use App\Http\Middleware\ProductionGuard;
use App\Http\Middleware\StorefrontActivity;
use App\Services\Catalogue;
use App\Services\MerchantProducts;
use App\Services\Seo;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class GoogleSeoMerchantTest extends TestCase
{
    private string $reviewsPath;

    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        Http::preventStrayRequests();
        $this->withoutMiddleware([ProductionGuard::class, StorefrontActivity::class]);
        $this->reviewsPath = sys_get_temp_dir().'/homya-google-reviews-'.bin2hex(random_bytes(8)).'.json';
        config(['merchant.reviews_path' => $this->reviewsPath, 'dashboard.enabled' => false,
            'commerce.enabled' => false, 'commerce.inventory_enabled' => false, 'merchant.enabled' => false,
            'seo.indexing_enabled' => true, 'seo.public_url' => 'https://homya-store.com',
            'app.url' => 'https://homya-store.com', 'app.debug' => false]);
    }

    protected function tearDown(): void
    {
        File::delete($this->reviewsPath);
        parent::tearDown();
    }

    private function production(): void
    {
        $this->app['env'] = 'production';
    }

    private function product(): array
    {
        return ['slug' => 'fixture-product', 'reference' => 'HOMYA-FIXTURE', 'name' => 'Fixture refrigerator',
            'description' => 'A factual fixture description.', 'brand' => 'Fixture manufacturer',
            'universe' => 'appliances', 'category' => ['slug' => 'refrigerateurs', 'name' => 'Réfrigérateurs'],
            'condition' => 'new', 'price_cents' => 12345, 'currency' => 'EUR', 'has_variants' => false,
            'variants' => [], 'issues' => [], 'specifications' => [],
            'images' => [['local_url' => '/media/catalogue/'.str_repeat('a', 24).'.jpg']]];
    }

    private function review(array $product): array
    {
        return ['approved' => true, 'fingerprints' => ['fr' => MerchantProducts::fingerprint($product)],
            'gtin' => '4006381333931', 'mpn' => 'MANUFACTURER-FIXTURE',
            'title_source' => 'default', 'description_source' => 'default'];
    }

    private function request(string $name, array $parameters = [], string $query = ''): Request
    {
        $path = match ($name) {
            'information' => '/{locale}/{page}',
            'product' => '/{locale}/produit/{slug}',
            default => '/{locale}/catalogue',
        };
        $route = (new Route(['GET'], $path, fn () => null))->name($name);
        $uri = strtr($path, array_combine(array_map(fn ($key) => '{'.$key.'}', array_keys($parameters)), array_values($parameters)) ?: []);
        $request = Request::create('https://homya-store.com'.$uri.$query);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function catalogue(array $products, bool $sourceUnavailable = false, bool $translationUnavailable = false): void
    {
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('products')->andReturn(collect($products));
        $catalogue->shouldReceive('sourceUnavailable')->andReturn($sourceUnavailable);
        $catalogue->shouldReceive('translationUnavailable')->andReturn($translationUnavailable);
        $this->app->instance(Catalogue::class, $catalogue);
    }

    private function sitemap(): DOMXPath
    {
        $this->catalogue([$this->product() + ['promotion' => null]]);
        $response = $this->get('https://homya-store.com/sitemaps/fr/pages.xml')->assertOk();
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($response->getContent()));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $xpath;
    }

    public function test_production_sitemaps_can_be_inspected_before_indexing_is_enabled(): void
    {
        $this->production();
        config(['seo.indexing_enabled' => false, 'merchant.enabled' => true]);
        $this->catalogue([$this->product() + ['promotion' => null]]);
        $origin = 'https://homya-store.com';
        $index = $this->get($origin.'/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($index->getContent()));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $locations = array_map(fn ($node) => $node->textContent, iterator_to_array($xpath->query('/s:sitemapindex/s:sitemap/s:loc')));
        $this->assertCount(count(config('homya.locales')) * 2, $locations);
        foreach (config('homya.locales') as $locale) {
            foreach (['pages.xml', 'products-1.xml'] as $file) {
                $url = $origin.'/sitemaps/'.$locale.'/'.$file;
                $this->assertContains($url, $locations);
                $response = $this->get($url)->assertOk()
                    ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
                    ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
                $child = new DOMDocument;
                $this->assertTrue($child->loadXML($response->getContent()));
                $this->assertSame('urlset', $child->documentElement->localName);
                $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $child->documentElement->namespaceURI);
            }
        }
        $this->get($origin.'/robots.txt')->assertOk()->assertSee('Disallow: /', false)->assertDontSee('Sitemap:', false);
        $this->get($origin.'/google/merchant/fr.xml')->assertNotFound();
        $this->assertFalse(app(Seo::class)->enabled());
        $metadata = app(Seo::class)->page($this->request('product', ['locale' => 'fr', 'slug' => 'fixture-product']),
            'Fixture title', 'Fixture description', ['available' => true]);
        $this->assertSame('noindex, nofollow', $metadata['robots']);
    }

    public function test_sitemaps_stay_closed_outside_the_configured_safe_public_origin(): void
    {
        $this->catalogue([$this->product() + ['promotion' => null]]);
        $publicOrigin = 'https://homya-store.com';
        $cases = [
            ['testing', false, $publicOrigin, $publicOrigin],
            ['local', false, $publicOrigin, $publicOrigin],
            ['production', true, $publicOrigin, $publicOrigin],
            ['production', false, $publicOrigin, 'http://homya-store.com'],
            ['production', false, $publicOrigin, 'https://www.homya-store.com'],
            ['production', false, $publicOrigin, 'https://other-store.com'],
            ['production', false, '', $publicOrigin],
            ['production', false, 'http://homya-store.com', 'http://homya-store.com'],
            ['production', false, 'https://homya-store.com/store', $publicOrigin],
            ['production', false, 'https://homya-store.test', 'https://homya-store.test'],
        ];
        foreach ($cases as [$environment, $debug, $configuredOrigin, $requestOrigin]) {
            foreach (['/sitemap.xml', '/sitemaps/fr/pages.xml', '/sitemaps/fr/products-1.xml'] as $path) {
                $this->app['env'] = $environment;
                config(['app.debug' => $debug, 'seo.public_url' => $configuredOrigin, 'seo.indexing_enabled' => false]);
                $this->get($requestOrigin.$path)->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            }
        }
    }

    public function test_public_sitemaps_report_unavailable_catalogues_and_missing_chunks(): void
    {
        $this->production();
        config(['seo.indexing_enabled' => false]);
        $product = $this->product() + ['promotion' => null];
        foreach ([[[$product], true, false], [[$product], false, true], [[], false, false]] as [$products, $sourceUnavailable, $translationUnavailable]) {
            $this->catalogue($products, $sourceUnavailable, $translationUnavailable);
            foreach (['/sitemap.xml', '/sitemaps/fr/pages.xml', '/sitemaps/fr/products-1.xml'] as $path) {
                $this->get('https://homya-store.com'.$path)->assertStatus(503)
                    ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            }
        }
        $this->catalogue([$product]);
        $this->get('https://homya-store.com/sitemaps/fr/products-2.xml')->assertNotFound();
    }

    public function test_sitemap_and_page_metadata_agree_for_all_completed_information_pages(): void
    {
        $this->production();
        app()->setLocale('fr');
        $seo = app(Seo::class);
        $xpath = $this->sitemap();
        $locations = array_map(fn ($node) => $node->textContent, iterator_to_array($xpath->query('//s:loc')));
        foreach (InformationController::PAGES as $page) {
            $metadata = $seo->page($this->request('information', ['locale' => 'fr', 'page' => $page]),
                'Fixture title', 'Fixture description', ['available' => true, 'pending' => __('pages.entries.'.$page)['pending']]);
            $url = 'https://homya-store.com/fr/'.$page;
            if ($metadata['canonical'] !== null) {
                $this->assertContains($url, $locations, $page);
                $this->assertSame('index, follow, max-image-preview:large', $metadata['robots']);
            } else {
                $this->assertNotContains($url, $locations, $page);
            }
        }
        foreach (['contact', 'livraison', 'paiement'] as $page) {
            $this->assertContains('https://homya-store.com/fr/'.$page, $locations);
        }
        $this->assertNotContains('https://homya-store.com/fr/suivi', $locations);
        $this->assertContains('https://homya-store.com/fr/catalogue?category=refrigerateurs', $locations);
    }

    public function test_contact_readiness_and_pending_content_are_resolved_consistently(): void
    {
        $this->production();
        app()->setLocale('fr');
        __('pages.entries.contact');
        app('translator')->addLines(['pages.entries.contact.pending' => true], 'fr');
        config(['contact.phone' => null, 'contact.email' => null]);
        $seo = app(Seo::class);
        $this->assertFalse($seo->informationIndexable('contact'));
        config(['contact.email' => 'fixture@example.com']);
        $this->assertTrue($seo->informationIndexable('contact'));
        $this->assertTrue($seo->informationIndexable('contact', true));
        $this->assertFalse($seo->informationIndexable('suivi', false));
        $this->assertFalse($seo->informationIndexable('unknown-page', false));
        $this->assertFalse($seo->informationIndexable('confidentialite', true));
        $this->assertTrue($seo->informationIndexable('confidentialite', false));
    }

    public function test_simple_product_identifiers_are_published_only_after_a_current_valid_review(): void
    {
        $this->production();
        app()->setLocale('fr');
        $product = $this->product();
        $valid = $this->review($product);
        $invalid = [[], array_replace($valid, ['approved' => false]),
            array_replace($valid, ['fingerprints' => ['fr' => str_repeat('0', 64)]]),
            array_replace($valid, ['gtin' => '4006381333932']),
            array_replace($valid, ['identifier_exists' => false])];
        foreach (array_merge([$valid], $invalid) as $index => $review) {
            File::put($this->reviewsPath, json_encode([$product['slug'] => $review]));
            $metadata = app(Seo::class)->page($this->request('product', ['locale' => 'fr', 'slug' => $product['slug']]),
                $product['name'], $product['description'], ['available' => true, 'product' => $product]);
            $graph = json_decode($metadata['json'], true)['@graph'];
            $node = array_values(array_filter($graph, fn ($entry) => $entry['@type'] === 'Product'))[0];
            $this->assertSame(MerchantProducts::id($product), $node['sku']);
            if ($index === 0) {
                $this->assertSame($valid['gtin'], $node['gtin']);
                $this->assertSame($valid['mpn'], $node['mpn']);
            } else {
                $this->assertArrayNotHasKey('gtin', $node);
                $this->assertArrayNotHasKey('mpn', $node);
            }
            // The factual review cannot invent actionable offers in a closed checkout.
            $this->assertArrayNotHasKey('offers', $node);
        }
    }

    public function test_feed_uses_the_localized_store_hierarchy_and_only_reviewed_taxonomy_ids(): void
    {
        $merchant = app(MerchantProducts::class);
        foreach (['fr' => ['Réfrigérateurs & congélateurs', 'Électroménagers'],
            'en' => ['Fridges & freezers', 'Home appliances'],
            'de' => ['Kühlgeräte & Gefriergeräte', 'Haushaltsgeräte']] as $locale => [$category, $universe]) {
            $product = $this->product();
            $product['category']['name'] = $category;
            $review = $this->review($product) + ['google_product_category' => '443'];
            $document = new DOMDocument;
            $this->assertTrue($document->loadXML($merchant->xml([[$product, $review, true]], $locale)));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('g', 'http://base.google.com/ns/1.0');
            $this->assertSame($universe.' > '.$category, $xpath->evaluate('string(//g:product_type)'));
            $this->assertSame('443', $xpath->evaluate('string(//g:google_product_category)'));
            unset($review['google_product_category']);
            $this->assertStringNotContainsString('google_product_category', $merchant->xml([[$product, $review, true]], $locale));
        }
        $product = $this->product();
        $review = $this->review($product);
        foreach ([0, -1, '0', '00443', 'Fixtures > Invalid', [], '443<script>'] as $category) {
            $this->assertContains('google_product_category_invalid', $merchant->problems($product,
                $review + ['google_product_category' => $category], 'fr'));
        }
        $this->assertNull(MerchantProducts::productType(array_replace($product, ['universe' => 'unknown']), 'fr'));
        $changed = $product;
        $changed['category']['name'] = 'A changed category';
        $this->assertContains('merchant_review_required', $merchant->problems($changed, $review, 'fr'));
    }

    public function test_local_indexing_and_merchant_feed_stay_closed_even_when_flags_are_enabled(): void
    {
        config(['merchant.enabled' => true]);
        $this->get('http://localhost/robots.txt')->assertOk()->assertSee('Disallow: /', false);
        $this->get('http://localhost/sitemap.xml')->assertNotFound();
        $this->get('http://localhost/google/merchant/fr.xml')->assertNotFound();
        $this->assertFalse(app(Seo::class)->enabled());
        $this->assertFalse(app(MerchantProducts::class)->ready());
        $this->production();
        $this->get('https://homya-store.com/robots.txt')->assertOk()->assertSee('Sitemap: https://homya-store.com/sitemap.xml', false);
        $this->get('https://homya-store.com/google/merchant/fr.xml')->assertNotFound();
    }

    public function test_eprel_certification_is_optional_and_accepts_only_a_reviewed_registry_id(): void
    {
        $merchant = app(MerchantProducts::class);
        $product = $this->product();
        $review = $this->review($product);
        foreach ([$review, $review + ['eprel_code' => null]] as $withoutCertification) {
            $this->assertSame([], $merchant->problems($product, $withoutCertification, 'fr'));
            $this->assertStringNotContainsString('g:certification', $merchant->xml([[$product, $withoutCertification, true]], 'fr'));
        }
        foreach (['123456', str_repeat('9', 20)] as $code) {
            $withCertification = $review + ['eprel_code' => $code];
            $this->assertSame([], $merchant->problems($product, $withCertification, 'fr'));
            $document = new DOMDocument;
            $this->assertTrue($document->loadXML($merchant->xml([[$product, $withCertification, true]], 'fr')));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('g', 'http://base.google.com/ns/1.0');
            $this->assertSame(1, $xpath->query('//g:certification')->length);
            $this->assertSame('EC', $xpath->evaluate('string(//g:certification/g:certification_authority)'));
            $this->assertSame('EPREL', $xpath->evaluate('string(//g:certification/g:certification_name)'));
            $this->assertSame($code, $xpath->evaluate('string(//g:certification/g:certification_code)'));
        }
        foreach (['', '0', '000123', '-123', '123.4', '1e6', '123<script>', str_repeat('9', 21), 123456, false, []] as $code) {
            $invalidCertification = $review + ['eprel_code' => $code];
            $this->assertContains('eprel_code_invalid', $merchant->problems($product, $invalidCertification, 'fr'));
            $this->assertStringNotContainsString('g:certification', $merchant->xml([[$product, $invalidCertification, true]], 'fr'));
        }
    }
}
