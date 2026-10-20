<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\Commerce\OrderTerms;
use App\Services\LegalInformation;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

final class ShippingAddressesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['dashboard.enabled' => false, 'commerce.inventory_enabled' => false]);
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('products')->andReturn(collect());
        $catalogue->shouldReceive('sourceUnavailable')->andReturn(true);
        $this->app->instance(Catalogue::class, $catalogue);
    }

    public function test_dispatch_and_returns_use_the_confirmed_lille_address_in_every_language(): void
    {
        $legal = app(LegalInformation::class);
        $this->assertFalse(config('legal.returns_at_registered_office'));
        $this->assertSame('GF', config('legal.address_structured.country'));
        $this->assertSame('FR', config('shipping.dispatch.address_structured.country'));
        $this->assertSame('FR', config('shipping.returns.address_structured.country'));
        $this->assertSame(config('shipping.dispatch.address'), $legal->dispatchAddress());
        $this->assertSame(config('shipping.returns.address'), $legal->returnAddress());
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['livraison', 'cgv', 'contact', 'retractation'] as $page) {
                $response = $this->get('/'.$locale.'/'.$page)->assertOk()
                    ->assertSee('Rue Aristote, Mont de Terre, Fives, 59000 Lille, France')
                    ->assertDontSee(':dispatch_address')->assertDontSee(':return_address');
                if ($page === 'retractation') {
                    $document = new DOMDocument;
                    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
                    $xpath = new DOMXPath($document);
                    foreach ($xpath->query('//dl[contains(@class,"return-details")]//dd') as $destination) {
                        $this->assertStringContainsString('59000 Lille', $destination->textContent);
                        $this->assertStringNotContainsString('97354', $destination->textContent);
                    }
                }
            }
            $this->get('/'.$locale.'/mentions-legales')->assertOk()->assertSee(config('legal.company.address'));
        }
    }

    public function test_unverified_or_incomplete_operational_addresses_are_not_substituted_with_the_registered_office(): void
    {
        $legal = app(LegalInformation::class);
        foreach ([['verified' => false], ['address' => ''], ['address_structured.city' => '']] as $change) {
            $original = config('shipping.returns');
            foreach ($change as $key => $value) {
                config(['shipping.returns.'.$key => $value]);
            }
            $this->assertNull($legal->returnAddress());
            foreach (['fr', 'en', 'de'] as $locale) {
                $this->get('/'.$locale.'/retractation')->assertOk()
                    ->assertSee(__('legal.return_destination_pending', [], $locale))
                    ->assertDontSee('Rue Aristote');
            }
            config(['shipping.returns' => $original]);
        }
        config(['shipping.dispatch.verified' => false]);
        $this->assertNull($legal->dispatchAddress());
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/livraison')->assertOk()
                ->assertSee(__('legal.dispatch_destination_pending', [], $locale))->assertDontSee('Rue Aristote');
        }
    }

    public function test_terms_fingerprint_changes_with_each_operational_address_without_changing_the_seller(): void
    {
        app()->setLocale('fr');
        $terms = app(OrderTerms::class);
        $registeredOffice = config('legal.company.address');
        $before = $terms->hash();
        config(['shipping.dispatch.address' => 'Different verified dispatch address']);
        $this->assertNotSame($before, $terms->hash());
        $before = $terms->hash();
        config(['shipping.returns.address' => 'Different verified return address']);
        $this->assertNotSame($before, $terms->hash());
        $before = $terms->hash();
        config(['shipping.returns.address_structured.postal_code' => '59100']);
        $this->assertNotSame($before, $terms->hash());
        $this->assertSame($registeredOffice, config('legal.company.address'));
    }
}
