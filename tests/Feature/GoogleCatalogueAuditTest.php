<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
            $template = json_decode(File::get(base_path($relativeOutput.'/merchant-products-review-template.json')), true, flags: JSON_THROW_ON_ERROR)['fixture'];
            $this->assertFalse($template['approved']);
            $this->assertNull($template['gtin']);
            $this->assertNull($template['mpn']);
            $this->assertNull($template['identifier_exists']);
            $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $template['fingerprints']['fr']);
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
}
