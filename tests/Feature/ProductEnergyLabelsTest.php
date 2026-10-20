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
                $labels = $service->forProductLabels($product, $locale);
                $expected = $entry['components'] ?? [$entry];
                $localeComplete = collect($expected)->every(fn ($component) => isset($component['sheets'][$locale])
                    && ($component['price_display'] ?? true) !== false);
                if (! $localeComplete) {
                    $this->assertSame([], $labels);
                    $this->assertNull($service->forProduct($product, $locale));
                } else {
                    $this->assertCount(count($expected), $labels);
                }
                // A suppressed price arrow must never skip validation of the originals.
                $documents = $service->documentsForProduct($product, $locale);
                $this->assertCount(count($expected), $documents);
                foreach ($expected as $index => $component) {
                    $label = $documents[$index];
                    $this->assertSame($component['class'], $label['class']);
                    $this->assertSame($component['reference'] ?? $entry['reference'], $label['reference']);
                    $this->assertSame($component['label'], $label['label']);
                    $this->assertSame($component['image'], $label['image']);
                    $this->assertSame($component['sheets'][$locale] ?? null, $label['sheet']);
                    $this->assertEquals($component['sheets'], $label['sheets']);
                    foreach ($label['sheets'] as $sheet) {
                        $this->assertStringStartsWith('%PDF-', file_get_contents(public_path(ltrim($sheet, '/'))));
                    }
                    $original = public_path(ltrim($label['label'], '/'));
                    if (str_ends_with($original, '.pdf')) {
                        $this->assertStringStartsWith('%PDF-', file_get_contents($original));
                    } else {
                        $this->assertSame(str_ends_with($original, '.jpg') ? IMAGETYPE_JPEG : IMAGETYPE_PNG, exif_imagetype($original));
                    }
                }
                if (! $localeComplete) {
                    continue;
                }
                if (isset($entry['components'])) {
                    $this->assertNull($service->forProduct($product, $locale));
                    $this->assertSame(['washing', 'drying'], array_column($labels, 'scope'));
                } else {
                    $this->assertSame($labels[0], $service->forProduct($product, $locale));
                }
            }
            foreach ([['reference' => 'OTHER'], ['has_variants' => true], ['variant_id' => 'v1']] as $change) {
                $this->assertSame([], $service->forProductLabels(array_replace($product, $change), 'fr'));
            }
            $this->assertSame([], $service->forProductLabels($product, 'es'));
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
        $product = ['slug' => $slug, 'reference' => 'F14R15WHS'];
        $this->assertNull((new ProductEnergyLabels)->forProduct($product, 'fr'));
        $this->assertSame([], (new ProductEnergyLabels)->forProductLabels($product, 'fr'));
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
            $energy = (new ProductEnergyLabels)->forProduct($product, $locale);
            $images = $xpath->query('//details//img');
            $this->assertSame(1, $images->length);
            $this->assertSame($energy['image'], $images->item(0)->getAttribute('src'));
            $this->assertStringContainsString('href="'.$energy['sheet'].'"', $html);
            $this->assertStringContainsString('href="'.$energy['label'].'"', $html);
        }
    }

    public function test_original_jpeg_and_locale_specific_pdf_are_preserved_without_fallback(): void
    {
        $originalPublic = public_path();
        $temporaryPublic = sys_get_temp_dir().'/energy-documents-'.bin2hex(random_bytes(8));
        mkdir($temporaryPublic.'/media/energy', 0700, true);
        // Real 1 × 1 JPEG fixture; never installed in the public catalogue.
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q==', true);
        file_put_contents($temporaryPublic.'/media/energy/test-label.jpg', $jpeg);
        file_put_contents($temporaryPublic.'/media/energy/test-sheet.pdf', "%PDF-1.4\n% isolated fixture\n");
        $entry = config('product-energy.lg-f14r15whs-lave-linge');
        $entry['label'] = $entry['image'] = '/media/energy/test-label.jpg';
        $entry['sheets'] = ['de' => '/media/energy/test-sheet.pdf', 'en' => '/media/energy/test-sheet.pdf'];
        config(['product-energy.test-jpeg' => $entry]);
        $product = ['slug' => 'test-jpeg', 'reference' => $entry['reference']];
        app()->usePublicPath($temporaryPublic);
        try {
            $service = new ProductEnergyLabels;
            $label = $service->forProduct($product, 'de');
            $this->assertSame($entry['label'], $label['label']);
            $this->assertSame($entry['sheets']['de'], $label['sheet']);
            $this->assertSame(IMAGETYPE_JPEG, exif_imagetype(public_path(ltrim($label['label'], '/'))));
            $this->assertSame(hash('sha256', $jpeg), hash_file('sha256', public_path(ltrim($label['label'], '/'))));
            $this->assertNull($service->forProduct($product, 'fr'));
            $this->assertSame([], $service->forProductLabels($product, 'fr'));
            $documents = $service->documentsForProduct($product, 'fr');
            $this->assertCount(1, $documents);
            $this->assertSame(['en', 'de'], array_keys($documents[0]['sheets']));
            $this->assertNull($documents[0]['sheet']);
            $profile = ['metrics' => [], 'scale' => null, 'status' => 'verified', 'sources' => []];
            app()->setLocale('fr');
            $partialHtml = view('components.product-energy-characteristics', compact('profile', 'documents'))->render();
            $this->assertStringContainsString(__('energy.original'), $partialHtml);
            $this->assertStringContainsString('hreflang="en"', $partialHtml);
            $this->assertStringContainsString('hreflang="de"', $partialHtml);
            $this->assertStringContainsString(__('energy.sheet').' (EN)', $partialHtml);
            $this->assertStringNotContainsString('hreflang="fr"', $partialHtml);
            $this->assertStringNotContainsString('energy-arrow', $partialHtml);
            $this->assertSame([], $service->documentsForProduct(array_replace($product, ['reference' => 'OTHER']), 'fr'));
            $this->assertSame([], $service->documentsForProduct($product + ['variant_id' => 'v1'], 'fr'));
            $this->assertSame([], $service->documentsForProduct($product + ['has_variants' => true], 'fr'));
            $this->assertNull($service->forProduct(array_replace($product, ['reference' => 'OTHER']), 'de'));
            app()->setLocale('de');
            $html = view('components.product-energy', ['product' => $product, 'locale' => 'de'])->render();
            $this->assertStringContainsString(__('energy.original'), $html);
            $this->assertStringNotContainsString(__('energy.pdf'), $html);
            $this->assertStringContainsString('href="/media/energy/test-label.jpg"', $html);
            $this->assertStringNotContainsString('energy-arrow', view('components.product-energy', ['product' => $product, 'locale' => 'fr'])->render());
            foreach ([
                ['label', '/media/energy/test-sheet.pdf/../test-label.jpg'],
                ['label', '/media/energy/test-label.gif'],
                ['image', '/media/energy/test-sheet.pdf'],
                ['sheets.de', '/media/energy/test-label.jpg'],
            ] as [$key, $path]) {
                config(['product-energy.test-jpeg' => $entry]);
                config(['product-energy.test-jpeg.'.$key => $path]);
                $this->assertNull($service->forProduct($product, 'de'));
            }
            config(['product-energy.test-jpeg' => $entry]);
            config(['product-energy.test-jpeg.label_page' => 2]);
            $this->assertNull($service->forProduct($product, 'de'));
            $this->assertSame([], $service->documentsForProduct($product, 'fr'));
            config(['product-energy.test-jpeg' => $entry]);
            symlink($temporaryPublic.'/media/energy/test-label.jpg', $temporaryPublic.'/media/energy/test-link.jpg');
            config(['product-energy.test-jpeg.label' => '/media/energy/test-link.jpg']);
            $this->assertNull($service->forProduct($product, 'de'));
            // A tower must have both modules in the requested language.
            config(['product-energy.test-tower-locales' => [
                'reference' => 'PARENT-EXACT',
                'components' => [
                    $entry + ['scope' => 'washing'],
                    array_replace($entry, ['sheets' => ['de' => '/media/energy/test-sheet.pdf']]) + ['scope' => 'drying'],
                ],
            ]]);
            $tower = ['slug' => 'test-tower-locales', 'reference' => 'PARENT-EXACT'];
            $this->assertCount(2, $service->forProductLabels($tower, 'de'));
            $this->assertSame([], $service->forProductLabels($tower, 'en'));
            $this->assertCount(2, $service->documentsForProduct($tower, 'en'));
            config(['product-energy.test-tower-locales.components.1.label' => '/media/energy/missing.jpg']);
            $this->assertSame([], $service->documentsForProduct($tower, 'en'));
            $this->assertSame([], $service->forProductLabels($tower, 'fr'));
        } finally {
            app()->usePublicPath($originalPublic);
            foreach (glob($temporaryPublic.'/media/energy/*') as $file) {
                unlink($file);
            }
            rmdir($temporaryPublic.'/media/energy');
            rmdir($temporaryPublic.'/media');
            rmdir($temporaryPublic);
        }
    }

    public function test_pdf_page_fragment_preserves_original_path_and_rejects_invalid_metadata(): void
    {
        $slug = 'lg-f14r15whs-lave-linge';
        $product = ['slug' => $slug, 'reference' => 'F14R15WHS'];
        $entry = config('product-energy.'.$slug);
        config(['product-energy.'.$slug.'.label_page' => 2]);
        $service = new ProductEnergyLabels;
        $label = $service->forProduct($product, 'fr');
        $this->assertSame($entry['label'], $label['label']);
        $this->assertSame($entry['label'].'#page=2', $label['label_url']);
        $this->assertSame($entry['label'].'#page=2', $service->documentsForProduct($product, 'fr')[0]['label_url']);
        $html = view('components.product-energy', ['product' => $product, 'locale' => 'fr'])->render();
        $this->assertStringContainsString('href="'.$entry['label'].'#page=2"', $html);
        $profile = ['metrics' => [], 'scale' => null, 'status' => 'verified', 'sources' => []];
        $documents = [$label];
        $html = view('components.product-energy-characteristics', compact('profile', 'documents'))->render();
        $this->assertStringContainsString('href="'.$entry['label'].'#page=2"', $html);
        foreach ([0, -1, 1001, '2', 2.0, true, null] as $page) {
            config(['product-energy.'.$slug.'.label_page' => $page]);
            $this->assertNull($service->forProduct($product, 'fr'));
            $this->assertSame([], $service->documentsForProduct($product, 'fr'));
        }
        config(['product-energy.'.$slug.'.label_page' => 1]);
        $this->assertSame($entry['label'], $service->forProduct($product, 'fr')['label_url']);
    }

    public function test_manual_links_open_the_verified_sheet_page_without_changing_the_disk_path(): void
    {
        $slug = 'lg-f14r15whs-lave-linge';
        $product = ['slug' => $slug, 'reference' => 'F14R15WHS'];
        $entry = config('product-energy.'.$slug);
        $service = new ProductEnergyLabels;
        config(['product-energy.'.$slug.'.sheet_pages' => ['fr' => 91, 'en' => 61],
            'product-energy.'.$slug.'.label_page' => 180]);
        $label = $service->forProduct($product, 'fr');
        $this->assertSame($entry['sheets']['fr'], $label['sheet']);
        $this->assertSame($entry['sheets']['fr'].'#page=91', $label['sheet_url']);
        $this->assertSame($entry['sheets']['en'].'#page=61', $label['sheet_urls']['en']);
        $this->assertSame($entry['label'].'#page=180', $label['label_url']);
        $html = view('components.product-energy', ['product' => $product, 'locale' => 'fr'])->render();
        $this->assertStringContainsString('href="'.$label['sheet_url'].'"', $html);
        $profile = ['metrics' => [], 'scale' => null, 'status' => 'verified', 'sources' => []];
        $documents = [$label];
        $html = view('components.product-energy-characteristics', compact('profile', 'documents'))->render();
        $this->assertStringContainsString('href="'.$label['sheet_urls']['en'].'" hreflang="en"', $html);
        foreach ([['fr' => 0], ['fr' => 1001], ['fr' => '91'], ['fr' => 1.5], ['fr' => null], ['es' => 3], '91', null] as $pages) {
            config(['product-energy.'.$slug.'.sheet_pages' => $pages]);
            $this->assertNull($service->forProduct($product, 'fr'));
            $this->assertSame([], $service->documentsForProduct($product, 'fr'));
        }
        config(['product-energy.'.$slug.'.sheet_pages' => ['fr' => 1]]);
        $this->assertSame($entry['sheets']['fr'], $service->forProduct($product, 'fr')['sheet_url']);
    }

    public function test_price_display_guard_keeps_original_documents_accessible_without_a_price_arrow(): void
    {
        $slug = 'lg-f14r15whs-lave-linge';
        $product = ['slug' => $slug, 'reference' => 'F14R15WHS'];
        $service = new ProductEnergyLabels;
        $original = $service->forProduct($product, 'fr');
        $this->assertNotNull($original);
        config(['product-energy.'.$slug.'.price_display' => false]);
        $this->assertNull($service->forProduct($product, 'fr'));
        $this->assertSame([], $service->forProductLabels($product, 'fr'));
        $documents = $service->documentsForProduct($product, 'fr');
        $this->assertCount(1, $documents);
        $this->assertSame($original['label'], $documents[0]['label']);
        $this->assertSame($original['sheet'], $documents[0]['sheet']);
        $html = view('components.product-energy', ['product' => $product, 'locale' => 'fr'])->render();
        $this->assertStringNotContainsString('energy-arrow', $html);
        $profile = ['metrics' => [], 'scale' => null, 'status' => 'verified', 'sources' => []];
        $html = view('components.product-energy-characteristics', compact('profile', 'documents'))->render();
        $this->assertStringContainsString('href="'.$original['label'].'"', $html);
        $this->assertStringContainsString('href="'.$original['sheet'].'"', $html);
        $this->assertStringNotContainsString('energy-arrow', $html);
        config(['product-energy.'.$slug.'.price_display' => true]);
        $tower = $this->multiModuleFixture();
        config(['product-energy.test-tower.components.1.price_display' => false]);
        $this->assertNull($service->forProduct($tower, 'fr'));
        $this->assertSame([], $service->forProductLabels($tower, 'fr'));
        $this->assertCount(2, $service->documentsForProduct($tower, 'fr'));
    }

    public function test_original_fiche_reservation_is_visible_in_every_supported_language_without_a_price_arrow(): void
    {
        $slug = 'lg-f14r15whs-lave-linge';
        $product = ['slug' => $slug, 'reference' => 'F14R15WHS'];
        config(['product-energy.'.$slug.'.price_display' => false,
            'product-energy.'.$slug.'.document_warning' => 'supplier_regulation_reference_typo_2018_1186']);
        $service = new ProductEnergyLabels;
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $this->assertSame([], $service->forProductLabels($product, $locale));
            $documents = $service->documentsForProduct($product, $locale);
            $this->assertCount(1, $documents);
            $this->assertArrayHasKey($locale, $documents[0]['sheets']);
            $profile = ['metrics' => [], 'scale' => null, 'status' => 'verified', 'sources' => []];
            $html = view('components.product-energy-characteristics', compact('profile', 'documents'))->render();
            $this->assertStringContainsString(e(__('energy.supplier_regulation_reference_typo')), $html);
            $this->assertStringContainsString('2018/1186', $html);
            $this->assertStringContainsString('href="'.$documents[0]['sheets'][$locale].'"', $html);
            $this->assertStringNotContainsString('energy-arrow', $html);
        }
    }

    private function multiModuleFixture(): array
    {
        $washer = config('product-energy.lg-f14r15whs-lave-linge');
        $dryer = config('product-energy.froid-rb33b610esa-samsung-combines-congelateur-en-bas');
        // Reuse existing local documents only as fixture files, with distinct module references.
        config(['product-energy.test-tower' => [
            'reference' => 'PARENT-EXACT',
            'components' => [$washer + ['scope' => 'washing'], $dryer + ['scope' => 'drying']],
        ]]);

        return ['slug' => 'test-tower', 'reference' => 'PARENT-EXACT'];
    }

    public function test_two_modules_have_separate_documents_classes_and_accessible_contexts(): void
    {
        $product = $this->multiModuleFixture();
        $service = new ProductEnergyLabels;
        $this->assertNull($service->forProduct($product, 'fr'));
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $labels = $service->forProductLabels($product, $locale);
            $this->assertCount(2, $labels);
            $this->assertSame(['washing', 'drying'], array_column($labels, 'scope'));
            $this->assertSame(['A', 'E'], array_column($labels, 'class'));
            $this->assertNotSame($labels[0]['sheet'], $labels[1]['sheet']);
            $html = view('components.product-energy', compact('product', 'locale'))->render();
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new DOMXPath($document);
            $this->assertSame(2, $xpath->query('//details[@class="energy-disclosure"]/summary')->length);
            $this->assertStringContainsString(__('energy.contexts.washing'), $html);
            $this->assertStringContainsString(__('energy.contexts.drying'), $html);
            $this->assertSame(0, $xpath->query('//a//a')->length);
        }
        $this->assertSame([], $service->forProductLabels(array_replace($product, ['reference' => 'OTHER']), 'fr'));
        $this->assertSame([], $service->forProductLabels($product + ['variant_id' => 'v1'], 'fr'));
    }

    public function test_missing_module_document_hides_both_labels_instead_of_inventing_a_combined_class(): void
    {
        $product = $this->multiModuleFixture();
        config(['product-energy.test-tower.components.1.sheets.fr' => '/media/energy/missing.pdf']);
        $this->assertSame([], (new ProductEnergyLabels)->forProductLabels($product, 'fr'));
        $html = view('components.product-energy', ['product' => $product, 'locale' => 'fr'])->render();
        $this->assertStringNotContainsString('energy-arrow', $html);
        $this->assertStringNotContainsString('energy-sheet', $html);
    }

    public function test_duplicate_or_incomplete_module_scopes_fail_closed(): void
    {
        $product = $this->multiModuleFixture();
        config(['product-energy.test-tower.components.1.scope' => 'washing']);
        $this->assertSame([], (new ProductEnergyLabels)->forProductLabels($product, 'fr'));
        config(['product-energy.test-tower.components' => [config('product-energy.test-tower.components.0')]]);
        $this->assertSame([], (new ProductEnergyLabels)->forProductLabels($product, 'fr'));
    }
}
