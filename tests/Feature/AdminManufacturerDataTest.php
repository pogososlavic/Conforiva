<?php

namespace Tests\Feature;

use App\Services\Admin\ProductManager;
use App\Services\AdminAccess;
use App\Services\Catalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdminManufacturerDataTest extends TestCase
{
    private string $temporary;

    private string $originalStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->originalStorage = storage_path();
        $this->temporary = sys_get_temp_dir().'/homya-manufacturer-test-'.Str::uuid();
        File::ensureDirectoryExists($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config(['dashboard.enabled' => true, 'admin.username' => 'fixture',
            'admin.password_hash' => Hash::make('fixture-private-password'),
            'database.connections.commerce' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('commerce');
        (require database_path('migrations/commerce/2026_10_09_000001_create_commerce_tables.php'))->up();
        (require database_path('migrations/commerce/2026_10_09_000002_create_admin_tables.php'))->up();
        Http::preventStrayRequests();
        $this->withSession(['admin.auth' => ['fingerprint' => app(AdminAccess::class)->fingerprint(),
            'started' => now()->timestamp, 'seen' => now()->timestamp]]);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStorage);
        DB::purge('commerce');
        File::deleteDirectory($this->temporary);
        parent::tearDown();
    }

    private function productData(int $revision = 0): array
    {
        return ['reference' => 'HOMYA-INTERNAL-REFERENCE', 'revision' => $revision,
            'price' => '123.45', 'quantity' => 12, 'published' => false,
            'brand' => 'Fixture Manufacturer', 'category' => 'Test', 'universe' => 'other',
            'condition' => 'neuf', 'languages' => ['fr' => ['name' => 'Fixture produit',
                'description' => 'Description du produit fixture.', 'category' => 'Test']]];
    }

    private function manufacturerData(): array
    {
        return ['gtin' => '036000291452', 'mpn' => 'MAKER-42',
            'manufacturer_source_url' => 'https://manufacturer.example.invalid/products/42',
            'manufacturer_verified' => '1'];
    }

    private function content(string $slug): array
    {
        return json_decode(DB::connection('commerce')->table('commerce_admin_products')
            ->where('slug', $slug)->value('content'), true, 32, JSON_THROW_ON_ERROR);
    }

    public function test_editor_creates_and_reads_documented_identifiers_without_approving_merchant(): void
    {
        $this->post('/admin/produits/nouveau', $this->productData() + $this->manufacturerData())
            ->assertRedirect()->assertSessionHasNoErrors();
        $slug = DB::connection('commerce')->table('commerce_admin_products')->value('slug');
        $expected = ['gtin' => '036000291452', 'mpn' => 'MAKER-42',
            'source_url' => 'https://manufacturer.example.invalid/products/42', 'verified' => true];
        $content = $this->content($slug);
        $this->assertSame($expected, $content['manufacturer']);
        $this->assertArrayNotHasKey('approved', $content);
        $this->assertArrayNotHasKey('identifier_exists', $content['manufacturer']);
        $this->assertSame($expected, app(Catalogue::class)->productForAdmin($slug)['manufacturer']);
        $this->assertSame($expected, app(ProductManager::class)->form($slug)['manufacturer']);
        $this->get('/admin/produits/'.$slug)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('036000291452')->assertSee('MAKER-42')
            ->assertSee('https://manufacturer.example.invalid/products/42')
            ->assertSee('name="manufacturer_verified" value="1" checked', false);
    }

    public function test_editor_accepts_documented_mpn_with_brand_and_can_remove_a_gtin(): void
    {
        $manager = app(ProductManager::class);
        $slug = $manager->save(null, $this->productData() + $this->manufacturerData());
        $changed = array_replace($this->manufacturerData(), ['gtin' => '', 'mpn' => 'MAKER-43']);
        $this->post('/admin/produits/'.$slug, $this->productData(1) + $changed)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['gtin' => null, 'mpn' => 'MAKER-43',
            'source_url' => 'https://manufacturer.example.invalid/products/42', 'verified' => true],
            $this->content($slug)['manufacturer']);
        $this->assertSame('HOMYA-INTERNAL-REFERENCE', DB::connection('commerce')
            ->table('commerce_inventory')->where('slug', $slug)->value('reference'));
    }

    public function test_invalid_identifiers_and_evidence_urls_are_rejected_before_any_product_write(): void
    {
        $invalid = [
            ['gtin', '036000291453'],
            ['gtin', '00000000'],
            ['gtin', '123456789'],
            ['gtin', 36000291452],
            ['mpn', str_repeat('M', 71)],
            ['mpn', "MAKER\n43"],
            ['manufacturer_source_url', 'http://manufacturer.example.invalid/42'],
            ['manufacturer_source_url', 'javascript:alert(1)'],
            ['manufacturer_source_url', 'https://login:password@manufacturer.example.invalid/42'],
            ['manufacturer_source_url', "https://manufacturer.example.invalid/prod\nucts/42"],
        ];
        foreach ($invalid as [$field, $value]) {
            $data = array_replace($this->productData() + $this->manufacturerData(), [$field => $value]);
            $this->post('/admin/produits/nouveau', $data)->assertUnprocessable()
                ->assertViewHas('errors', fn ($errors) => $errors->has($field))
                ->assertSessionMissing('_old_input');
        }
        $this->assertSame(0, DB::connection('commerce')->table('commerce_admin_products')->count());
        $this->assertSame(0, DB::connection('commerce')->table('commerce_inventory')->count());
    }

    public function test_verification_requires_evidence_and_at_least_one_real_identifier(): void
    {
        $withoutSource = array_replace($this->manufacturerData(), ['manufacturer_source_url' => '']);
        $this->post('/admin/produits/nouveau', $this->productData() + $withoutSource)
            ->assertUnprocessable()
            ->assertViewHas('errors', fn ($errors) => $errors->has('manufacturer_source_url'))
            ->assertSessionMissing('_old_input');
        $withoutIdentifiers = array_replace($this->manufacturerData(), ['gtin' => '', 'mpn' => '']);
        $this->post('/admin/produits/nouveau', $this->productData() + $withoutIdentifiers)
            ->assertUnprocessable()
            ->assertViewHas('errors', fn ($errors) => $errors->has('manufacturer_verified'))
            ->assertSessionMissing('_old_input');
        $this->assertSame(0, DB::connection('commerce')->table('commerce_admin_products')->count());
    }

    public function test_older_editor_requests_preserve_identifiers_but_explicit_clearing_is_supported(): void
    {
        $manager = app(ProductManager::class);
        $slug = $manager->save(null, $this->productData() + $this->manufacturerData());
        $previous = $this->content($slug)['manufacturer'];
        $this->post('/admin/produits/'.$slug, $this->productData(1))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($previous, $this->content($slug)['manufacturer']);
        $clear = ['gtin' => '', 'mpn' => '', 'manufacturer_source_url' => '', 'manufacturer_verified' => '0'];
        $this->post('/admin/produits/'.$slug, $this->productData(2) + $clear)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['gtin' => null, 'mpn' => null, 'source_url' => null, 'verified' => false],
            $this->content($slug)['manufacturer']);
    }

    public function test_manufacturer_validation_also_protects_direct_service_saves(): void
    {
        $manager = app(ProductManager::class);
        foreach ([['gtin' => '036000291453'], ['manufacturer_source_url' => 'https://login@manufacturer.example.invalid/42'],
            ['gtin' => null, 'mpn' => null, 'manufacturer_verified' => true]] as $invalid) {
            try {
                $manager->save(null, array_replace($this->productData() + $this->manufacturerData(), $invalid));
                $this->fail('Invalid manufacturer data accepted by direct save.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
        $this->assertSame(0, DB::connection('commerce')->table('commerce_admin_products')->count());
    }
}
