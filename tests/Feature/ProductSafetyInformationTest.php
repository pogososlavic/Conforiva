<?php

namespace Tests\Feature;

use App\Services\ProductSafetyInformation;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProductSafetyInformationTest extends TestCase
{
    public function test_verified_contacts_stay_with_the_exact_model_and_do_not_leak_to_variants(): void
    {
        $service = app(ProductSafetyInformation::class);
        foreach (config('product-safety') as $slug => $entry) {
            $product = ['slug' => $slug, 'reference' => $entry['reference'], 'has_variants' => false];
            $this->assertSame($entry, $service->forProduct($product));
            $this->assertNull($service->forProduct(array_replace($product, ['reference' => 'OTHER'])));
            $this->assertNull($service->forProduct(array_replace($product, ['has_variants' => true])));
            $this->assertNull($service->forProduct($product + ['variant_id' => 'v1']));
        }
        $this->assertNull($service->forProduct([]));
    }

    public function test_lg_contact_and_safety_documents_are_accessible_in_all_storefront_languages(): void
    {
        $product = ['slug' => 'lg-f14r15whs-lave-linge', 'reference' => 'F14R15WHS', 'has_variants' => false];
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $html = Blade::render('<x-product-safety :product="$product" />', compact('product'));
            $this->assertStringContainsString('eprelpublic@lge.com', $html);
            $this->assertStringContainsString('65760 Eschborn', $html);
            $this->assertStringContainsString('/legal/safety/lg-f14r15whs-safety.pdf', $html);
            $this->assertStringNotContainsString('safety.title', $html);
        }
        $this->assertStringStartsWith('%PDF-', file_get_contents(public_path('legal/safety/lg-f14r15whs-safety.pdf')));
    }
}
