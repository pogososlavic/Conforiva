<?php

namespace Tests\Feature;

use App\Services\InternationalPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use libphonenumber\PhoneNumberUtil;
use Tests\TestCase;

final class InternationalPhoneTest extends TestCase
{
    public function test_all_supported_regions_have_localized_names_and_prefixes(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $countries = app(InternationalPhone::class)->countries($locale);
            $this->assertCount(count(PhoneNumberUtil::getInstance()->getSupportedRegions()), $countries);
            foreach ($countries as $country) {
                $this->assertNotEmpty($country['name']);
                $this->assertMatchesRegularExpression('/^\+[0-9]{1,3}$/', $country['prefix']);
            }
            $this->assertSame('+33', $countries['FR']['prefix']);
            $this->assertSame('+225', $countries['CI']['prefix']);
        }
    }

    public function test_national_numbers_are_normalized_without_losing_significant_zeroes(): void
    {
        $phone = app(InternationalPhone::class);
        foreach ([['FR', '06 12 34 56 78', '+33612345678'], ['GB', '020 7946 0018', '+442079460018'], ['IT', '02 3661 8300', '+390236618300'], ['US', '202 555 0123', '+12025550123'], ['CI', '07 07 07 07 07', '+2250707070707']] as [$region, $input, $expected]) {
            $this->assertSame($expected, $phone->normalize($input, $region));
        }
        $this->assertNull($phone->normalize('', 'FR'));
        $this->assertSame('+33612345678', $phone->normalize('+33612345678', 'FR'));
    }

    public function test_a_conflicting_prefix_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(InternationalPhone::class)->normalize('+442079460018', 'FR');
    }

    public function test_an_invalid_region_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(InternationalPhone::class)->normalize('0612345678', 'ZZ');
    }

    public function test_field_renders_a_native_selector_without_changing_shipping_countries(): void
    {
        $countries = config('commerce.countries');
        $html = view('components.checkout-phone', ['errors' => new ViewErrorBag])->render();
        $this->assertStringContainsString('name="phone_region"', $html);
        $this->assertStringContainsString('value="CI"', $html);
        $this->assertStringContainsString('value="FR" selected', $html);
        $this->assertSame($countries, config('commerce.countries'));
    }

    public function test_invalid_country_selector_is_rejected_by_checkout_and_keeps_phone_input(): void
    {
        DB::shouldReceive('connection')->never();
        config(['commerce.enabled' => true, 'commerce.deferred_enabled' => true]);
        $this->post('/fr/commande', [
            'checkout_key' => (string) Str::uuid(), 'quote_hash' => str_repeat('a', 64), 'terms_hash' => str_repeat('b', 64),
            'email' => 'fixture@example.test', 'name' => 'Test customer', 'address' => '1 test street',
            'postal_code' => '59000', 'city' => 'Lille', 'country' => 'FR',
            'terms' => '1', 'privacy_acknowledged' => '1', 'shipping_acknowledged' => '1',
            'phone_region' => 'ZZ', 'phone' => '0612345678',
        ])->assertRedirect()->assertSessionHasErrors('phone_region')
            ->assertSessionHas('_old_input.phone', '0612345678')->assertSessionHas('_old_input.phone_region', 'ZZ');
    }
}
