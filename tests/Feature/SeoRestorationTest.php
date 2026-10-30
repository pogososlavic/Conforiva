<?php

namespace Tests\Feature;

use App\Http\Middleware\ProductionGuard;
use App\Http\Middleware\StorefrontActivity;
use App\Services\Catalogue;
use App\Services\MerchantPolicies;
use App\Services\ProductOfferEligibility;
use App\Services\Seo;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SeoRestorationTest extends TestCase
{
    private const ORIGIN = 'https://homya-store.com';

    private const PAGES = ['mentions-legales', 'cgv', 'retractation', 'confidentialite', 'cookies'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware([ProductionGuard::class, StorefrontActivity::class]);
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        Http::preventStrayRequests();
        $this->app['env'] = 'production';
        config(['app.url' => self::ORIGIN, 'app.debug' => false,
            'seo.public_url' => self::ORIGIN, 'seo.indexing_enabled' => true,
            'dashboard.enabled' => false, 'commerce.enabled' => false, 'commerce.inventory_enabled' => false,
            'merchant.enabled' => false, 'analytics.enabled' => false, 'storefront_protection.mode' => 'off']);

        // A synthetic catalogue makes the HTML and sitemap available without SQL or production files.
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('products')->andReturn(collect([[
            'slug' => 'fixture-product', 'name' => 'Fixture product', 'promotion' => null,
            'universe' => 'appliances', 'brand' => 'Fixture brand',
            'category' => ['slug' => 'refrigerateurs', 'name' => 'Fixture category'],
        ]]));
        $catalogue->shouldReceive('sourceUnavailable')->andReturn(false);
        $catalogue->shouldReceive('translationUnavailable')->andReturn(false);
        $this->app->instance(Catalogue::class, $catalogue);
    }

    private function html(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    private function sitemap(string $locale): array
    {
        $response = $this->get(self::ORIGIN.'/sitemaps/'.$locale.'/pages.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($response->getContent()));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $locations = array_map(fn ($node) => $node->textContent, iterator_to_array($xpath->query('/s:urlset/s:url/s:loc')));
        $this->assertSame(count($locations), count(array_unique($locations)));

        return $locations;
    }

    private function store(DOMXPath $xpath): array
    {
        $blocks = $xpath->query('//script[@type="application/ld+json"]');
        $this->assertCount(1, $blocks);
        $graph = json_decode($blocks->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR)['@graph'];
        $stores = array_values(array_filter($graph, fn ($node) => $node['@type'] === 'OnlineStore'));
        $this->assertCount(1, $stores);

        return $stores[0];
    }

    public function test_all_fifteen_legal_urls_have_matching_html_headers_canonicals_alternates_and_sitemaps(): void
    {
        $checked = 0;
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (self::PAGES as $page) {
                $this->assertTrue(config('seo.information_indexing.'.$page));
                __('pages.entries.'.$page, [], $locale);
                app('translator')->addLines(['pages.entries.'.$page.'.pending' => true], $locale);
            }
            $locations = $this->sitemap($locale);
            foreach (self::PAGES as $page) {
                $url = self::ORIGIN.'/'.$locale.'/'.$page;
                $response = $this->get($url)->assertOk()
                    ->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large');
                $xpath = $this->html($response->getContent());
                $this->assertSame('index, follow, max-image-preview:large',
                    $xpath->evaluate('string(//meta[@name="robots"]/@content)'), $url);
                $this->assertCount(1, $xpath->query('//link[@rel="canonical"]'), $url);
                $this->assertSame($url, $xpath->evaluate('string(//link[@rel="canonical"]/@href)'));
                $this->assertCount(4, $xpath->query('//link[@rel="alternate"][@hreflang]'), $url);
                foreach (['fr' => 'fr', 'en' => 'en', 'de' => 'de', 'x-default' => 'fr'] as $language => $target) {
                    $links = $xpath->query('//link[@rel="alternate"][@hreflang="'.$language.'"]');
                    $this->assertCount(1, $links, $url);
                    $this->assertSame(self::ORIGIN.'/'.$target.'/'.$page, $links->item(0)->getAttribute('href'));
                }
                $this->assertContains($url, $locations);
                $returnUrl = self::ORIGIN.'/'.$locale.'/retractation';
                $this->assertSame(['@type' => 'MerchantReturnPolicy', '@id' => $returnUrl.'#return-policy',
                    'merchantReturnLink' => $returnUrl], $this->store($xpath)['hasMerchantReturnPolicy']);
                $checked++;
            }
            $this->assertNotContains(self::ORIGIN.'/'.$locale.'/suivi', $locations);
        }
        $this->assertSame(15, $checked);
    }

    public function test_explicit_page_exclusion_and_private_or_unknown_pages_stay_out_of_discovery(): void
    {
        config(['seo.information_indexing.confidentialite' => false,
            'seo.information_indexing.suivi' => true, 'seo.information_indexing.unknown' => true]);
        $response = $this->get(self::ORIGIN.'/fr/confidentialite')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertCount(0, $this->html($response->getContent())->query('//link[@rel="canonical"]'));
        $this->assertNotContains(self::ORIGIN.'/fr/confidentialite', $this->sitemap('fr'));
        $seo = app(Seo::class);
        $this->assertFalse($seo->informationIndexable('suivi', false));
        $this->assertFalse($seo->informationIndexable('unknown', false));
        $this->get(self::ORIGIN.'/fr/unknown')->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_product_sitemap_uses_only_a_safe_local_primary_image(): void
    {
        $path = '/media/catalogue/'.str_repeat('b', 24).'.jpg';
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('products')->andReturn(collect([
            ['slug' => 'local-image', 'image' => ['local_url' => $path],
                'images' => [['local_url' => '/media/catalogue/unreviewed-gallery.jpg']]],
            ['slug' => 'remote-image', 'image' => ['local_url' => 'https://external-fixture.com/photo.jpg']],
            ['slug' => 'unsafe-image', 'image' => ['local_url' => '/media/../private/photo.jpg']],
        ]));
        $catalogue->shouldReceive('sourceUnavailable')->andReturn(false);
        $catalogue->shouldReceive('translationUnavailable')->andReturn(false);
        $this->app->instance(Catalogue::class, $catalogue);
        $response = $this->get(self::ORIGIN.'/sitemaps/fr/products-1.xml')->assertOk();
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($response->getContent()));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('image', 'http://www.google.com/schemas/sitemap-image/1.1');
        $this->assertCount(3, $xpath->query('/s:urlset/s:url'));
        $this->assertCount(1, $xpath->query('//image:image/image:loc'));
        $this->assertSame(self::ORIGIN.$path, $xpath->evaluate('string(//image:image/image:loc)'));
        $this->assertSame(self::ORIGIN.'/fr/produit/local-image',
            $xpath->evaluate('string(/s:urlset/s:url[image:image]/s:loc)'));
        $response->assertDontSee('external-fixture')->assertDontSee('unreviewed-gallery')->assertDontSee('../private');
    }

    public function test_publication_overrides_cannot_open_indexing_outside_the_safe_public_context(): void
    {
        foreach ([['local', true, false, self::ORIGIN], ['production', false, false, self::ORIGIN],
            ['production', true, true, self::ORIGIN], ['production', true, false, 'https://alias-fixture.com']] as [$environment, $indexing, $debug, $origin]) {
            $this->app['env'] = $environment;
            config(['seo.indexing_enabled' => $indexing, 'app.debug' => $debug]);
            $response = $this->get($origin.'/fr/cgv')->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertSame('noindex, nofollow', $this->html($response->getContent())
                ->evaluate('string(//meta[@name="robots"]/@content)'));
        }
    }

    public function test_unverified_return_commitments_are_absent_from_the_store_graph(): void
    {
        config(['merchant-policies.returns.verified' => false]);
        $response = $this->get(self::ORIGIN.'/fr/cgv')->assertOk();
        $this->assertArrayNotHasKey('hasMerchantReturnPolicy', $this->store($this->html($response->getContent())));
    }

    public function test_policy_markup_is_withheld_when_rates_destinations_or_operational_facts_do_not_match(): void
    {
        $baseline = [
            'commerce.countries' => ['FR', 'BE', 'DE'], 'commerce.currency' => 'EUR', 'commerce.shipping_cents' => 0,
            'shipping.dispatch.verified' => true, 'shipping.dispatch.address_structured.country' => 'FR',
            'shipping.returns.verified' => true, 'shipping.returns.address_structured.country' => 'FR',
            'merchant-policies.shipping.verified' => true,
            'merchant-policies.shipping.handling_days' => ['min' => 1, 'max' => 2],
            'merchant-policies.shipping.transit_days' => ['min' => 2, 'max' => 5],
            'merchant-policies.returns.verified' => true, 'merchant-policies.returns.days' => 14,
            'merchant-policies.returns.free' => true,
        ];
        $policies = app(MerchantPolicies::class);
        $url = self::ORIGIN.'/fr/retractation';
        foreach ([
            [['commerce.shipping_cents' => 500], false, true],
            [['shipping.dispatch.verified' => false], false, true],
            [['merchant-policies.shipping.handling_days' => ['min' => 2, 'max' => 1]], false, true],
            [['merchant-policies.returns.free' => false], true, false],
            [['shipping.returns.verified' => false], true, false],
            [['commerce.countries' => ['FR', 'US']], false, false],
            [['commerce.countries' => ['FR', 'FR']], false, false],
        ] as [$override, $shipping, $returns]) {
            config(array_replace($baseline, $override));
            $result = $policies->forOffer($url);
            $this->assertSame($shipping, array_key_exists('shippingDetails', $result));
            $this->assertSame($returns, array_key_exists('hasMerchantReturnPolicy', $result));
        }
        config($baseline);
        $this->assertNull($policies->returnPolicy('http://homya-store.com/fr/retractation'));
        $this->assertNull($policies->returnPolicy('/fr/retractation'));
    }

    public function test_restored_policies_do_not_make_a_closed_checkout_or_invalid_product_sellable(): void
    {
        $eligibility = app(ProductOfferEligibility::class);
        $product = ['has_variants' => false, 'price_cents' => 12345, 'currency' => 'EUR'];
        $images = ['/media/fixture.jpg'];
        $this->assertFalse($eligibility->eligible($product, true, $images));
        $checks = $eligibility->checks(array_replace($product, ['price_cents' => 0, 'has_variants' => true]), false, []);
        foreach (['public_indexable', 'checkout_payment_ready', 'simple_product', 'price_valid', 'images_present'] as $check) {
            $this->assertFalse($checks[$check], $check);
        }
    }
}
