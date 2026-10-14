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
            'promotions' => '/{locale}/promotions',
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

    private function sitemapLocations(string $url, string $root = 'urlset'): array
    {
        $response = $this->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($response->getContent()));
        $this->assertSame($root, $document->documentElement->localName);
        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $document->documentElement->namespaceURI);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $locations = array_map(fn ($node) => $node->textContent, iterator_to_array($xpath->query('//s:loc')));
        $this->assertSame(count($locations), count(array_unique($locations)), $url.' must not contain duplicate URLs.');

        return $locations;
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

    public function test_root_redirect_is_crawlable_only_when_public_production_indexing_is_open(): void
    {
        $this->production();
        config(['seo.indexing_enabled' => true]);
        $this->get('https://homya-store.com/')->assertStatus(301)->assertRedirect('/fr')
            ->assertHeaderMissing('X-Robots-Tag');

        config(['seo.indexing_enabled' => false]);
        $this->get('https://homya-store.com/')->assertStatus(301)->assertRedirect('/fr')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->app['env'] = 'local';
        config(['seo.indexing_enabled' => true]);
        $this->get('http://localhost/')->assertStatus(301)->assertRedirect('/fr')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
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

    public function test_pages_sitemap_contains_all_canonical_collection_pages_without_filters_or_private_urls(): void
    {
        $this->production();
        config(['homya.catalogue_page_size' => 2]);
        $products = [];
        foreach (range(1, 7) as $number) {
            $products[] = array_replace($this->product(), [
                'slug' => 'fixture-product-'.$number,
                'universe' => $number <= 5 ? 'appliances' : 'furniture',
                'category' => match (true) {
                    $number <= 3 => ['slug' => 'refrigerateurs', 'name' => 'Réfrigérateurs'],
                    $number <= 5 => ['slug' => 'lave-vaisselle', 'name' => 'Lave-vaisselle'],
                    default => ['slug' => 'canapes', 'name' => 'Canapés'],
                },
                'promotion' => $number <= 5 ? ['percent' => 15, 'price_cents' => 10000] : null,
            ]);
        }
        $this->catalogue($products);
        $origin = 'https://homya-store.com';
        $locations = $this->sitemapLocations($origin.'/sitemaps/fr/pages.xml');
        $expected = array_map(fn ($path) => $origin.'/fr/'.$path, [
            'catalogue', 'catalogue?page=2', 'catalogue?page=3', 'catalogue?page=4',
            'catalogue?universe=appliances', 'catalogue?universe=appliances&page=2', 'catalogue?universe=appliances&page=3',
            'catalogue?universe=furniture',
            'catalogue?category=refrigerateurs', 'catalogue?category=refrigerateurs&page=2',
            'catalogue?category=lave-vaisselle', 'catalogue?category=canapes',
            'promotions', 'promotions?page=2', 'promotions?page=3',
        ]);
        $collectionLocations = array_values(array_filter($locations,
            fn ($url) => in_array(parse_url($url, PHP_URL_PATH), ['/fr/catalogue', '/fr/promotions'], true)));
        $this->assertEqualsCanonicalizing($expected, $collectionLocations);
        $this->assertContains($origin.'/fr', $locations);

        app()->setLocale('fr');
        $seo = app(Seo::class);
        foreach ($expected as $url) {
            $query = parse_url($url, PHP_URL_QUERY) ?? '';
            parse_str($query, $filters);
            $name = str_ends_with(parse_url($url, PHP_URL_PATH), '/promotions') ? 'promotions' : 'catalogue';
            $metadata = $seo->page($this->request($name, ['locale' => 'fr'], $query === '' ? '' : '?'.$query),
                'Fixture title', 'Fixture description', ['available' => true, 'filters' => $filters]);
            $this->assertSame($url, $metadata['canonical'], $url);
            $this->assertSame('index, follow, max-image-preview:large', $metadata['robots'], $url);
        }
        foreach ($locations as $url) {
            $this->assertStringStartsWith($origin.'/fr', $url);
            $this->assertNull(parse_url($url, PHP_URL_FRAGMENT));
            $this->assertDoesNotMatchRegularExpression('~/(?:admin|panier|commande|suivi|assistance|retours)(?:/|$|\\?)~', $url);
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
            $this->assertSame([], array_diff(array_keys($query), ['universe', 'category', 'page']), $url);
            $this->assertFalse(isset($query['category'], $query['universe']), $url);
            if (isset($query['page'])) {
                $this->assertGreaterThan(1, (int) $query['page'], $url);
            }
        }
        foreach (['q' => 'fixture', 'brand' => 'Fixture manufacturer', 'condition' => 'new', 'min' => '10',
            'max' => '200', 'sort' => 'name', 'variant' => 'one'] as $key => $value) {
            $query = http_build_query([$key => $value]);
            $metadata = $seo->page($this->request('catalogue', ['locale' => 'fr'], '?'.$query),
                'Fixture title', 'Fixture description', ['available' => true, 'filters' => [$key => $value]]);
            $this->assertNull($metadata['canonical'], $key);
            $this->assertSame('noindex, follow', $metadata['robots'], $key);
            $this->assertNotContains($origin.'/fr/catalogue?'.$query, $locations);
        }
        foreach (['q' => 'fixture', 'discount' => '15'] as $key => $value) {
            $query = http_build_query([$key => $value]);
            $metadata = $seo->page($this->request('promotions', ['locale' => 'fr'], '?'.$query),
                'Fixture title', 'Fixture description', ['available' => true, 'filters' => [$key => $value]]);
            $this->assertNull($metadata['canonical'], $key);
            $this->assertNotContains($origin.'/fr/promotions?'.$query, $locations);
        }
        foreach (['catalogue', 'promotions'] as $name) {
            $metadata = $seo->page($this->request($name, ['locale' => 'fr'], '?page=1'),
                'Fixture title', 'Fixture description', ['available' => true, 'filters' => ['page' => 1]]);
            $this->assertSame($origin.'/fr/'.$name, $metadata['canonical']);
            $this->assertContains($metadata['canonical'], $locations);
        }
        $filters = ['universe' => 'appliances', 'category' => 'refrigerateurs', 'page' => 2];
        $metadata = $seo->page($this->request('catalogue', ['locale' => 'fr'], '?'.http_build_query($filters)),
            'Fixture title', 'Fixture description', ['available' => true, 'filters' => $filters]);
        $this->assertSame($origin.'/fr/catalogue?category=refrigerateurs&page=2', $metadata['canonical']);
        $this->assertContains($metadata['canonical'], $locations);
        $this->get($origin.'/sitemaps/fr/pages.xml')->assertSee('category=refrigerateurs&amp;page=2', false);
    }

    public function test_sitemap_index_counts_products_per_locale_and_links_only_to_existing_chunks(): void
    {
        $this->production();
        config(['seo.sitemap_chunk_size' => 2]);
        $localizedProducts = [];
        foreach (['fr' => 5, 'en' => 3, 'de' => 1] as $locale => $count) {
            foreach (range(1, $count) as $number) {
                $localizedProducts[$locale][] = array_replace($this->product(), [
                    'slug' => $locale.'-fixture-product-'.$number,
                    'promotion' => null,
                    'has_variants' => $number === 1,
                    'variants' => $number === 1 ? [['id' => 'one'], ['id' => 'two']] : [],
                ]);
            }
        }
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('products')->andReturnUsing(fn () => collect($localizedProducts[app()->getLocale()]));
        $catalogue->shouldReceive('sourceUnavailable')->andReturn(false);
        $catalogue->shouldReceive('translationUnavailable')->andReturn(false);
        $this->app->instance(Catalogue::class, $catalogue);
        $origin = 'https://homya-store.com';
        $locations = $this->sitemapLocations($origin.'/sitemap.xml', 'sitemapindex');
        $expected = [];
        foreach (['fr' => 3, 'en' => 2, 'de' => 1] as $locale => $chunks) {
            $pagesUrl = $origin.'/sitemaps/'.$locale.'/pages.xml';
            $expected[] = $pagesUrl;
            $this->sitemapLocations($pagesUrl);
            $productLocations = [];
            foreach (range(1, $chunks) as $chunk) {
                $url = $origin.'/sitemaps/'.$locale.'/products-'.$chunk.'.xml';
                $expected[] = $url;
                $child = $this->sitemapLocations($url);
                $this->assertNotEmpty($child, $url);
                $this->assertLessThanOrEqual(2, count($child), $url);
                $productLocations = array_merge($productLocations, $child);
            }
            $this->assertEqualsCanonicalizing(array_map(fn ($product) => $origin.'/'.$locale.'/produit/'.$product['slug'],
                $localizedProducts[$locale]), $productLocations);
            $this->assertSame(count($productLocations), count(array_unique($productLocations)));
            foreach ($productLocations as $url) {
                $this->assertNull(parse_url($url, PHP_URL_QUERY), $url);
            }
            $this->get($origin.'/sitemaps/'.$locale.'/products-'.($chunks + 1).'.xml')->assertNotFound();
        }
        $this->assertEqualsCanonicalizing($expected, $locations);
    }

    public function test_sitemap_index_fails_when_a_later_locale_has_no_publishable_catalogue(): void
    {
        $this->production();
        foreach (['empty', 'translation-unavailable'] as $failure) {
            $catalogue = Mockery::mock(Catalogue::class);
            $catalogue->shouldReceive('products')->andReturnUsing(fn () => app()->getLocale() === 'en' && $failure === 'empty' ? collect() : collect([$this->product() + ['promotion' => null]]));
            $catalogue->shouldReceive('sourceUnavailable')->andReturn(false);
            $catalogue->shouldReceive('translationUnavailable')->andReturnUsing(fn () => app()->getLocale() === 'en' && $failure === 'translation-unavailable');
            $this->app->instance(Catalogue::class, $catalogue);
            $this->get('https://homya-store.com/sitemap.xml')->assertStatus(503)
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
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
