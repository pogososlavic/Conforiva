<?php

namespace Tests\Feature;

use App\Services\ProductEnergyLabels;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class ProductEnergyLabelsTest extends TestCase
{
    public function test_documents_match_exact_models_and_locales_and_do_not_leak_to_variants(): void
    {
        $service = new ProductEnergyLabels;
        foreach (config('product-energy') as $slug => $entry) {
            $product = ['slug' => $slug, 'reference' => $entry['reference'], 'has_variants' => false];
            foreach (['fr', 'en', 'de'] as $locale) {
                $label = $service->forProduct($product, $locale);
                $this->assertSame($entry['class'], $label['class']);
                $this->assertSame($entry['sheets'][$locale], $label['sheet']);
                foreach ([$label['label'], $label['sheet']] as $path) {
                    $this->assertStringStartsWith('%PDF-', file_get_contents(public_path(ltrim($path, '/'))));
                }
            }
            $this->assertNull($service->forProduct(array_replace($product, ['reference' => 'OTHER']), 'fr'));
            $this->assertNull($service->forProduct(array_replace($product, ['has_variants' => true]), 'fr'));
            $this->assertNull($service->forProduct($product + ['variant_id' => 'v1'], 'fr'));
            $this->assertNull($service->forProduct($product, 'es'));
        }
        $this->assertNull($service->forProduct([], 'fr'));
        $this->assertNull($service->forProduct(['slug' => '../private'], 'fr'));
    }

    public function test_missing_document_closes_label_instead_of_linking_to_wrong_model(): void
    {
        $slug = 'lg-f14r15whs-lave-linge';
        config(['product-energy.'.$slug.'.sheets.fr' => '/media/energy/missing.pdf']);
        $this->assertNull((new ProductEnergyLabels)->forProduct(['slug' => $slug, 'reference' => 'F14R15WHS'], 'fr'));
    }

    public function test_card_has_no_nested_links_and_full_label_works_without_javascript(): void
    {
        $product = ['slug' => 'lg-f14r15whs-lave-linge', 'reference' => 'F14R15WHS', 'name' => 'LG F14R15WHS',
            'brand' => 'LG', 'price_cents' => 48517, 'currency' => 'EUR', 'has_variants' => false, 'image' => null];
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $html = view('components.product-card', compact('product', 'locale'))->render();
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new DOMXPath($document);
            $this->assertSame(0, $xpath->query('//a//a')->length);
            $this->assertSame(1, $xpath->query('//details[@class="energy-disclosure"]/summary')->length);
            $this->assertSame(1, $xpath->query('//details//img[contains(@src,"lg-f14r15whs-label.png")]')->length);
            $this->assertStringContainsString('lg-f14r15whs-sheet-'.$locale.'.pdf', $html);
        }
    }
}
