<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class CheckoutValidationRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        Http::preventStrayRequests();
        config(['dashboard.enabled' => false, 'commerce.enabled' => true, 'commerce.deferred_enabled' => true,
            'commerce.legal_ready' => false, 'commerce.inventory_enabled' => false, 'commerce.bank.enabled' => false,
            'catalogue.source' => 'files', 'app.url' => 'http://127.0.0.1:8000']);
        $product = ['slug' => 'checkout-fixture', 'reference' => 'TEST-CHECKOUT', 'name' => 'Fixture device',
            'description' => 'Synthetic fixture only.', 'brand' => 'Fixture', 'universe' => 'appliances',
            'category' => ['slug' => 'fixture-category', 'name' => 'Fixture category'], 'condition' => 'new',
            'price_cents' => 12345, 'currency' => 'EUR', 'has_variants' => false, 'variants' => [],
            'image' => null, 'images' => [], 'issues' => [], 'specifications' => []];
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('product')->with('checkout-fixture')->andReturn($product);
        $catalogue->shouldReceive('products')->andReturn(collect([$product]));
        $catalogue->shouldReceive('sourceUnavailable', 'translationUnavailable')->andReturn(false);
        $this->app->instance(Catalogue::class, $catalogue);
    }

    private function invalidInput(array $overrides = []): array
    {
        return array_replace(['checkout_key' => 'ef42fa62-0b7f-4ec6-838f-7aebff694d9b', 'quote_hash' => str_repeat('a', 64),
            'terms_hash' => str_repeat('b', 64), 'name' => ['invalid' => 'array'], 'email' => 'fixture@example.test',
            'address' => 'Fixture address', 'postal_code' => '75001', 'city' => 'Paris', 'country' => 'FR',
            'payment_method' => 'deferred', 'terms' => '1', 'privacy_acknowledged' => '1', 'shipping_acknowledged' => '1',
            'billing_different' => '1', 'billing_name' => 'Billing fixture', 'billing_address' => 'Billing street',
            'billing_postal_code' => '1000', 'billing_city' => 'Brussels', 'billing_country' => 'BE',
            'take_back' => ['checkout-fixture' => '1']], $overrides);
    }

    public function test_invalid_field_shapes_cannot_poison_the_following_checkout_render_or_flash_tokens(): void
    {
        $this->withSession(['commerce.cart' => ['checkout-fixture' => 1]])
            ->from('/fr/commande')->post('/fr/commande', $this->invalidInput())->assertSessionHasErrors('name');
        $this->assertNull(session('_old_input.name'));
        foreach (['checkout_key', 'quote_hash', 'terms_hash', 'terms', 'privacy_acknowledged', 'shipping_acknowledged'] as $key) {
            $this->assertNull(session('_old_input.'.$key), $key);
        }
        $this->assertSame('Billing fixture', session('_old_input.billing_name'));
        $this->assertSame('1', session('_old_input.billing_different'));
        $this->assertSame(['checkout-fixture' => '1'], session('_old_input.take_back'));
        $this->get('/fr/commande')->assertOk()->assertSee('Billing fixture')->assertSee('Billing street');
    }

    public function test_flashing_is_bounded_and_nested_take_back_payloads_are_not_retained(): void
    {
        $this->from('/fr/commande')->post('/fr/commande', $this->invalidInput([
            'billing_name' => str_repeat('B', 2000), 'phone' => ['invalid'],
            'take_back' => ['checkout-fixture' => ['nested' => str_repeat('x', 5000)], 'unsafe.key' => 1],
        ]))->assertSessionHasErrors(['name', 'billing_name', 'phone', 'take_back.checkout-fixture']);
        $this->assertSame(str_repeat('B', 150), session('_old_input.billing_name'));
        $this->assertNull(session('_old_input.phone'));
        $this->assertSame([], session('_old_input.take_back', []));
        $this->assertLessThan(4000, strlen(json_encode(session('_old_input'), JSON_THROW_ON_ERROR)));
    }

    public function test_return_form_validation_uses_the_same_safe_recovery_without_creating_a_record(): void
    {
        $this->from('/fr/retours/declaration')->post('/fr/retours/verifier', [
            'name' => ['invalid'], 'contract' => 'Synthetic reference only', 'method' => 'download', 'notes' => ['invalid'],
        ])->assertSessionHasErrors(['name', 'notes']);
        $this->assertNull(session('_old_input.name'));
        $this->assertNull(session('_old_input.notes'));
        $this->assertSame('Synthetic reference only', session('_old_input.contract'));
        $this->get('/fr/retours/declaration')->assertOk()->assertSee('Synthetic reference only');
    }
}
