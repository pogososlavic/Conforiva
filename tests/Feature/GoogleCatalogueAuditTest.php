<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\Commerce\CheckoutReadiness;
use App\Services\MerchantProducts;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class GoogleCatalogueAuditTest extends TestCase
{
    public function test_offline_audit_never_connects_and_saves_unapproved_supplier_review(): void
    {
        DB::shouldReceive('connection')->never();
        $name = 'google-audit-test-'.Str::uuid();
        $source = sys_get_temp_dir().'/'.$name;
        $relativeOutput = 'output/'.$name;
        File::ensureDirectoryExists($source.'/products');
        $product = ['slug' => 'fixture', 'reference' => '=FORMULA', 'name' => 'Produit test',
            'brand' => 'Test', 'universe' => 'heating', 'category' => ['slug' => 'test', 'name' => 'Test'],
            'condition' => 'neuf', 'price_cents' => 1500, 'currency' => 'EUR', 'has_variants' => false,
            'issues' => ['missing_specifications'], 'description' => 'Description test', 'images' => [], 'specifications' => [], 'variants' => []];
        File::put($source.'/index.json', json_encode(['meta' => ['complete' => true], 'products' => [$product]]));
        File::put($source.'/products/fixture.json', json_encode($product));
        config(['catalogue.source' => 'sql', 'dashboard.enabled' => true, 'commerce.inventory_enabled' => true,
            'catalogue.archive_path' => $source.'/index.json', 'catalogue.products_path' => $source.'/products',
            'catalogue.media_overrides_path' => $source.'/absent.json', 'merchant.reviews_path' => $source.'/absent-review.json']);
        try {
            $this->artisan('homya:google-audit', ['--locale' => 'fr', '--offline' => true, '--output' => $relativeOutput])->assertSuccessful();
            $report = json_decode(File::get(base_path($relativeOutput.'/google-audit-fr.json')), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(1, $report['products']);
            $this->assertSame('file_archive', $report['catalogue_scope']);
            $this->assertFalse($report['administration_checked']);
            $this->assertFalse($report['stock_checked']);
            $this->assertFalse($report['google_contacted']);
            $this->assertSame('absent', $report['review_file_status']);
            $this->assertNull($report['items'][0]['inventory_problems']);
            $this->assertSame(0, $report['currently_exportable']);
            $this->assertFalse($report['global_checks']['inventory_enabled']);
            $template = json_decode(File::get(base_path($relativeOutput.'/merchant-products-review-template.json')), true, flags: JSON_THROW_ON_ERROR)['fixture'];
            $this->assertFalse($template['approved']);
            $this->assertNull($template['gtin']);
            $this->assertNull($template['mpn']);
            $this->assertNull($template['identifier_exists']);
            $this->assertNull($template['energy_label_required']);
            $this->assertFalse($template['essential_details_verified']);
            $this->assertFalse($template['allow_google_energy_lookup']);
            $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $template['fingerprints']['fr']);
            $this->assertFileExists(base_path($relativeOutput.'/merchant-product-selection.csv'));
            $this->assertFileExists(base_path($relativeOutput.'/supplier-data-to-complete.csv'));
            $this->assertFileDoesNotExist(base_path($relativeOutput.'/merchant-preview-fr.xml'));
            $csv = fopen(base_path($relativeOutput.'/products-to-complete.csv'), 'r');
            fgetcsv($csv, separator: ';', escape: '');
            $row = fgetcsv($csv, separator: ';', escape: '');
            fclose($csv);
            $this->assertSame("'=FORMULA", $row[2]);
            $this->assertSame('sql', config('catalogue.source'));
            $this->assertTrue(config('dashboard.enabled'));
            // Existing evidence is preserved; the operator must choose a fresh folder.
            $this->expectException(\RuntimeException::class);
            $this->artisan('homya:google-audit', ['--locale' => 'fr', '--offline' => true, '--output' => $relativeOutput])->run();
        } finally {
            File::deleteDirectory($source);
            File::deleteDirectory(base_path($relativeOutput));
        }
    }

    public function test_report_cannot_be_written_in_public_or_via_path_traversal(): void
    {
        DB::shouldReceive('connection')->never();
        foreach (['public/google', 'output/../public', '/tmp/google-reviews'] as $directory) {
            $this->artisan('homya:google-audit', ['--output' => $directory])->assertFailed();
        }
    }

    public function test_offline_and_inventory_options_are_rejected_before_any_read(): void
    {
        DB::shouldReceive('connection')->never();
        $this->artisan('homya:google-audit', ['--offline' => true, '--with-inventory' => true])
            ->expectsOutput('--offline cannot be combined with --with-inventory.')
            ->assertFailed();
    }

    public function test_offline_audit_does_not_resolve_payment_or_read_admin_bank_settings(): void
    {
        DB::shouldReceive('connection')->never();
        $name = 'google-audit-offline-payment-'.Str::uuid();
        $source = sys_get_temp_dir().'/'.$name;
        $relativeOutput = 'output/'.$name;
        File::ensureDirectoryExists($source.'/products');
        $product = $this->fixtureProduct();
        File::put($source.'/index.json', json_encode(['meta' => ['complete' => true], 'products' => [$product]], JSON_THROW_ON_ERROR));
        File::put($source.'/products/'.$product['slug'].'.json', json_encode($product, JSON_THROW_ON_ERROR));
        $paymentResolved = false;
        $payment = Mockery::mock();
        $payment->shouldReceive('bankReady')->never();
        $payment->shouldReceive('cardReady')->never();
        $this->app->bind(CheckoutReadiness::class, function () use (&$paymentResolved, $payment) {
            $paymentResolved = true;

            return $payment;
        });
        config(['catalogue.source' => 'files', 'dashboard.enabled' => true,
            'catalogue.archive_path' => $source.'/index.json', 'catalogue.products_path' => $source.'/products',
            'catalogue.media_overrides_path' => $source.'/absent.json', 'merchant.reviews_path' => $source.'/absent-review.json',
            'commerce.enabled' => true, 'commerce.legal_ready' => true, 'commerce.inventory_enabled' => true,
            'merchant.enabled' => true, 'app.url' => 'https://homya.eu', 'seo.public_url' => 'https://homya.eu']);
        try {
            $this->artisan('homya:google-audit', ['--locale' => 'fr', '--offline' => true, '--output' => $relativeOutput])->assertSuccessful();
            $report = $this->report($relativeOutput, 'fr');
            $this->assertFalse($paymentResolved);
            $this->assertNull($report['global_checks']['checkout_payment_ready']);
            $this->assertNull($report['items'][0]['inventory_problems']);
            $this->assertFalse($report['merchant_configured']);
            $this->assertSame(0, $report['currently_exportable']);
            $this->assertTrue(config('commerce.enabled'));
            $this->assertTrue(config('commerce.legal_ready'));
            $this->assertTrue(config('commerce.inventory_enabled'));
        } finally {
            File::deleteDirectory($source);
            File::deleteDirectory(base_path($relativeOutput));
        }
    }

    public function test_inventory_is_read_once_for_all_languages_without_opening_the_global_gate(): void
    {
        $name = 'google-audit-inventory-'.Str::uuid();
        $reviewsPath = sys_get_temp_dir().'/'.$name.'.json';
        $relativeOutput = 'output/'.$name;
        $product = $this->fixtureProduct();
        $this->mockCatalogue($product);
        $fingerprints = array_fill_keys(config('homya.locales'), MerchantProducts::fingerprint($product));
        $review = ['approved' => true, 'fingerprints' => $fingerprints,
            'gtin' => '4006381333931', 'mpn' => 'FIXTURE-MODEL', 'title_source' => 'default', 'description_source' => 'default',
            'energy_label_required' => false, 'essential_details_verified' => true,
            'source_urls' => ['https://manufacturer.test/model?token=private-token', 'https://user:password@manufacturer.test/private']];
        File::put($reviewsPath, json_encode([$product['slug'] => $review], JSON_THROW_ON_ERROR));
        config(['merchant.reviews_path' => $reviewsPath, 'merchant.enabled' => false, 'dashboard.enabled' => false,
            'commerce.enabled' => false, 'commerce.inventory_enabled' => true, 'commerce.legal_ready' => false,
            'seo.public_url' => 'https://homya.eu', 'app.url' => 'https://homya.eu']);
        $connection = Mockery::mock(Connection::class);
        $query = Mockery::mock(Builder::class);
        DB::shouldReceive('connection')->with('commerce')->once()->andReturn($connection);
        DB::shouldReceive('connectUsing')->never();
        $connection->shouldReceive('table')->with('commerce_inventory')->once()->andReturn($query);
        $query->shouldReceive('select')->once()->with(['slug', 'reference', 'sale_enabled', 'approved_price_cents', 'validated_at', 'quantity', 'reserved'])->andReturnSelf();
        $query->shouldReceive('get')->once()->andReturn(collect([(object) [
            'slug' => $product['slug'], 'reference' => $product['reference'], 'sale_enabled' => true,
            'approved_price_cents' => $product['price_cents'], 'validated_at' => '2026-10-14 12:00:00', 'quantity' => 0, 'reserved' => 0,
        ]]));
        try {
            $this->artisan('homya:google-audit', ['--locale' => 'all', '--with-inventory' => true, '--output' => $relativeOutput])->assertSuccessful();
            foreach (config('homya.locales') as $locale) {
                $report = $this->report($relativeOutput, $locale);
                $this->assertSame('valid', $report['review_file_status']);
                $this->assertTrue($report['stock_checked']);
                $this->assertSame(1, $report['data_ready']);
                $this->assertSame(1, $report['data_ready_products']);
                $this->assertSame(0, $report['currently_exportable']);
                $this->assertSame([], $report['items'][0]['inventory_problems']);
                $this->assertSame(['missing_specifications'], $report['items'][0]['warnings']);
                $this->assertFalse($report['global_checks']['merchant_feed_enabled']);
                $document = new DOMDocument;
                $this->assertTrue($document->loadXML(File::get(base_path($relativeOutput.'/merchant-preview-'.$locale.'.xml'))));
                $xpath = new DOMXPath($document);
                $xpath->registerNamespace('g', 'http://base.google.com/ns/1.0');
                $this->assertSame('out_of_stock', $xpath->evaluate('string(//g:availability)'));
                $this->assertSame('15.00 EUR', $xpath->evaluate('string(//g:price)'));
            }
            $template = json_decode(File::get(base_path($relativeOutput.'/merchant-products-review-template.json')), true, flags: JSON_THROW_ON_ERROR)[$product['slug']];
            $this->assertFalse($template['approved']);
            $this->assertSame('4006381333931', $template['gtin']);
            $this->assertFalse($template['energy_label_required']);
            $this->assertTrue($template['essential_details_verified']);
            $this->assertContains('https://manufacturer.test/model', $template['source_urls']);
            $this->assertContains('https://homya.eu/fr/produit/'.$product['slug'], $template['source_urls']);
            $selection = $this->csvRows(base_path($relativeOutput.'/merchant-product-selection.csv'));
            $this->assertCount(2, $selection);
            $this->assertSame(implode(',', config('homya.locales')), $selection[1][4]);
            $supplier = $this->csvRows(base_path($relativeOutput.'/supplier-data-to-complete.csv'));
            $this->assertSame('false', $supplier[1][14]);
            $this->assertStringNotContainsString('private-token', File::get(base_path($relativeOutput.'/supplier-data-to-complete.csv')));
            $this->assertFalse(config('merchant.enabled'));
        } finally {
            File::delete($reviewsPath);
            File::deleteDirectory(base_path($relativeOutput));
        }
    }

    public function test_malformed_review_is_reported_without_leaking_its_contents(): void
    {
        DB::shouldReceive('connection')->never();
        $name = 'google-audit-invalid-'.Str::uuid();
        $reviewsPath = sys_get_temp_dir().'/'.$name.'.json';
        $relativeOutput = 'output/'.$name;
        $this->mockCatalogue($this->fixtureProduct());
        File::put($reviewsPath, '{"private-secret":');
        config(['merchant.reviews_path' => $reviewsPath, 'merchant.enabled' => false, 'commerce.enabled' => false]);
        try {
            $this->artisan('homya:google-audit', ['--locale' => 'fr', '--output' => $relativeOutput])->assertSuccessful();
            $report = $this->report($relativeOutput, 'fr');
            $this->assertSame('invalid', $report['review_file_status']);
            $this->assertSame(0, $report['data_ready']);
            $this->assertSame(0, $report['currently_exportable']);
            $this->assertStringNotContainsString('private-secret', File::get(base_path($relativeOutput.'/google-audit-fr.json')));
        } finally {
            File::delete($reviewsPath);
            File::deleteDirectory(base_path($relativeOutput));
        }
    }

    public function test_only_an_explicit_valid_unique_catalogue_gtin_prefills_the_unapproved_draft(): void
    {
        DB::shouldReceive('connection')->never();
        $name = 'google-audit-gtin-'.Str::uuid();
        $relativeOutput = 'output/'.$name;
        $product = $this->fixtureProduct();
        $product['specifications'] = [
            ['label' => 'EAN', 'value' => '4006381333931'],
            ['label' => 'SKU', 'value' => '9780306406157'],
        ];
        $this->mockCatalogue($product);
        config(['merchant.reviews_path' => sys_get_temp_dir().'/'.$name.'-absent.json',
            'merchant.enabled' => false, 'commerce.enabled' => false, 'seo.public_url' => 'https://homya.eu']);
        try {
            $this->artisan('homya:google-audit', ['--locale' => 'fr', '--output' => $relativeOutput])->assertSuccessful();
            $template = json_decode(File::get(base_path($relativeOutput.'/merchant-products-review-template.json')), true, flags: JSON_THROW_ON_ERROR)[$product['slug']];
            $this->assertSame('4006381333931', $template['gtin']);
            $this->assertFalse($template['approved']);
            $this->assertFalse($template['allow_google_energy_lookup']);
            $this->assertNull($template['title_source']);
            $this->assertNull($template['description_source']);
            $this->assertContains('https://homya.eu/fr/produit/'.$product['slug'], $template['source_urls']);
            $evidence = $template['manufacturer_identifier_evidence'];
            $this->assertSame('catalogue_specification:EAN', $evidence['type']);
            $this->assertTrue($evidence['syntax_valid']);
            $this->assertTrue($evidence['unique_in_audited_catalogue']);
            $this->assertFalse($evidence['independent_attribution_verified']);
            $this->assertSame($evidence, $this->report($relativeOutput, 'fr')['items'][0]['manufacturer_identifier_evidence']);
            $this->assertSame(0, $this->report($relativeOutput, 'fr')['data_ready']);
        } finally {
            File::deleteDirectory(base_path($relativeOutput));
        }
    }

    public function test_verified_admin_identifier_wins_while_conflicting_catalogue_and_review_are_reported(): void
    {
        DB::shouldReceive('connection')->never();
        $name = 'google-audit-gtin-conflict-'.Str::uuid();
        $relativeOutput = 'output/'.$name;
        $reviewsPath = sys_get_temp_dir().'/'.$name.'.json';
        $product = $this->fixtureProduct();
        $product['specifications'] = [['label' => 'EAN', 'value' => '4006381333931']];
        $product['manufacturer'] = ['verified' => true, 'gtin' => '9780306406157', 'mpn' => 'ADMIN-MODEL', 'source_url' => 'https://manufacturer.test/model'];
        $this->mockCatalogue($product);
        File::put($reviewsPath, json_encode([$product['slug'] => ['gtin' => '5901234123457', 'mpn' => 'OLD-MODEL']], JSON_THROW_ON_ERROR));
        config(['merchant.reviews_path' => $reviewsPath, 'merchant.enabled' => false, 'commerce.enabled' => false]);
        try {
            $this->artisan('homya:google-audit', ['--locale' => 'fr', '--output' => $relativeOutput])->assertSuccessful();
            $template = json_decode(File::get(base_path($relativeOutput.'/merchant-products-review-template.json')), true, flags: JSON_THROW_ON_ERROR)[$product['slug']];
            $this->assertSame('9780306406157', $template['gtin']);
            $this->assertSame('ADMIN-MODEL', $template['mpn']);
            $this->assertFalse($template['approved']);
            $this->assertSame('verified_administration', $template['manufacturer_identifier_evidence']['selected_source']);
            $this->assertTrue($template['manufacturer_identifier_evidence']['conflicts_with_declared_identifier']);
            $warnings = $this->report($relativeOutput, 'fr')['items'][0]['warnings'];
            $this->assertContains('manufacturer_gtin_conflicts_with_review', $warnings);
            $this->assertContains('catalogue_gtin_conflicts_with_declared_identifier', $warnings);
        } finally {
            File::delete($reviewsPath);
            File::deleteDirectory(base_path($relativeOutput));
        }
    }

    public function test_duplicate_catalogue_gtins_are_not_selected_automatically(): void
    {
        DB::shouldReceive('connection')->never();
        $name = 'google-audit-gtin-duplicate-'.Str::uuid();
        $relativeOutput = 'output/'.$name;
        $first = $this->fixtureProduct();
        $first['specifications'] = [['label' => 'GTIN', 'value' => '4006381333931']];
        $second = array_replace($first, ['slug' => 'second-merchant-fixture', 'reference' => 'FIXTURE-002']);
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('products')->andReturn(collect([$first, $second]));
        $catalogue->shouldReceive('product')->with($first['slug'])->andReturn($first);
        $catalogue->shouldReceive('product')->with($second['slug'])->andReturn($second);
        $catalogue->shouldReceive('sourceUnavailable')->andReturn(false);
        $catalogue->shouldReceive('translationUnavailable')->andReturn(false);
        $this->app->instance(Catalogue::class, $catalogue);
        config(['merchant.reviews_path' => sys_get_temp_dir().'/'.$name.'-absent.json',
            'merchant.enabled' => false, 'commerce.enabled' => false]);
        try {
            $this->artisan('homya:google-audit', ['--locale' => 'fr', '--output' => $relativeOutput])->assertSuccessful();
            $templates = json_decode(File::get(base_path($relativeOutput.'/merchant-products-review-template.json')), true, flags: JSON_THROW_ON_ERROR);
            foreach ([$first['slug'], $second['slug']] as $slug) {
                $this->assertNull($templates[$slug]['gtin']);
                $this->assertFalse($templates[$slug]['manufacturer_identifier_evidence']['unique_in_audited_catalogue']);
            }
            foreach ($this->report($relativeOutput, 'fr')['items'] as $item) {
                $this->assertContains('catalogue_gtin_not_unique', $item['warnings']);
            }
        } finally {
            File::deleteDirectory(base_path($relativeOutput));
        }
    }

    private function fixtureProduct(): array
    {
        return ['slug' => 'merchant-fixture', 'reference' => 'FIXTURE-001', 'name' => 'Produit test', 'description' => 'Description factuelle.',
            'brand' => 'Fabricant test', 'universe' => 'heating', 'category' => ['slug' => 'test', 'name' => 'Test'],
            'condition' => 'new', 'price_cents' => 1500, 'currency' => 'EUR', 'has_variants' => false,
            'issues' => ['missing_specifications'], 'specifications' => [], 'variants' => [],
            'images' => [['local_url' => '/media/catalogue/'.str_repeat('a', 24).'.jpg']]];
    }

    private function mockCatalogue(array $product): void
    {
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('products')->andReturn(collect([$product]));
        $catalogue->shouldReceive('product')->with($product['slug'])->andReturn($product);
        $catalogue->shouldReceive('sourceUnavailable')->andReturn(false);
        $catalogue->shouldReceive('translationUnavailable')->andReturn(false);
        $this->app->instance(Catalogue::class, $catalogue);
    }

    private function report(string $directory, string $locale): array
    {
        return json_decode(File::get(base_path($directory.'/google-audit-'.$locale.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function csvRows(string $path): array
    {
        $stream = fopen($path, 'r');
        $rows = [];
        while (($row = fgetcsv($stream, separator: ';', escape: '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
