<?php

namespace Tests\Feature;

use App\Services\Admin\BankDetailsSettings;
use App\Services\Catalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class CartCheckoutNavigationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['commerce.enabled' => true, 'commerce.deferred_enabled' => true,
            'commerce.inventory_enabled' => false, 'commerce.bank.enabled' => false,
            'app.url' => 'http://127.0.0.1:8000']);
        $products = [
            'journey-unit' => ['slug' => 'journey-unit', 'reference' => 'JOURNEY-UNIT', 'name' => 'Unit fixture',
                'price_cents' => 12345, 'currency' => 'EUR', 'has_variants' => false, 'variants' => [], 'image' => null, 'issues' => []],
            'journey-options' => ['slug' => 'journey-options', 'reference' => 'JOURNEY-OPTIONS', 'name' => 'Options fixture',
                'price_cents' => 23456, 'currency' => 'EUR', 'has_variants' => true,
                'variants' => [['title' => 'First fixture'], ['title' => 'Second fixture', 'sku' => 'JOURNEY-SECOND']], 'image' => null, 'issues' => []],
        ];
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('product')->andReturnUsing(fn ($slug) => $products[$slug] ?? null);
        $catalogue->shouldReceive('products')->andReturn(collect($products));
        $catalogue->shouldReceive('sourceUnavailable', 'translationUnavailable')->andReturn(false);
        $this->app->instance(Catalogue::class, $catalogue);
        $bank = Mockery::mock();
        $bank->shouldReceive('current')->andReturnUsing(fn () => config('commerce.bank'));
        $this->app->instance(BankDetailsSettings::class, $bank);
        DB::shouldReceive('connection')->never();
        Http::preventStrayRequests();
    }

    public function test_cart_product_links_keep_the_exact_variant_in_each_language(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->withSession(['commerce.cart' => ['journey-options~v2' => 1, 'journey-unit' => 2]])
                ->get('/'.$locale.'/panier')->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $links = $xpath->query('//article[contains(@class,"cart-line")]//h2/a');
            $this->assertCount(2, $links);
            $this->assertSame(route('product', ['locale' => $locale, 'slug' => 'journey-options', 'variant' => 'v2']), $links->item(0)->getAttribute('href'));
            $this->assertSame(route('product', ['locale' => $locale, 'slug' => 'journey-unit']), $links->item(1)->getAttribute('href'));
            $this->assertSame(['journey-options~v2' => 1, 'journey-unit' => 2], session('commerce.cart'));
        }
    }

    public function test_checkout_language_links_keep_the_customer_in_checkout_with_their_cart(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->withSession(['commerce.cart' => ['journey-options~v2' => 2]])
                ->get('/'.$locale.'/commande')->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $links = $xpath->query('//nav[contains(@class,"languages")]/a');
            $this->assertCount(3, $links);
            foreach ($links as $link) {
                $language = $link->getAttribute('lang');
                $this->assertSame(route('checkout.show', ['locale' => $language]), $link->getAttribute('href'));
            }
            $this->assertSame(['journey-options~v2' => 2], session('commerce.cart'));
            $response->assertSee('Second fixture')->assertSee(Catalogue::money(46912, $locale));
        }
    }
}
