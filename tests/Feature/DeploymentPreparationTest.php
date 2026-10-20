<?php

namespace Tests\Feature;

use App\Services\LocalCatalogueReadGuard;
use App\Services\ProductionSecurity;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

final class DeploymentPreparationTest extends TestCase
{
    public function test_production_hostname_rejects_urls_ports_paths_uppercase_and_empty_values(): void
    {
        foreach (['', 'https://example.test', 'example.test/path', 'example.test:443', 'EXAMPLE.test', 'localhost', "example.test\n", '127.0.0.1'] as $invalid) {
            config(['deployment.host' => $invalid]);
            $this->assertSame('', ProductionSecurity::host());
            $this->assertFalse(app(ProductionSecurity::class)->checks()['url_https_production_host']);
        }
        config(['deployment.host' => 'shop.example.test', 'app.url' => 'https://shop.example.test']);
        $this->assertSame('shop.example.test', ProductionSecurity::host());
        $this->assertTrue(app(ProductionSecurity::class)->checks()['url_https_production_host']);
    }

    public function test_canonical_redirect_uses_the_configured_new_domain_and_not_the_previous_brand(): void
    {
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        config(['deployment.host' => 'shop.example.test']);
        $this->app['env'] = 'production';
        $security = Mockery::mock(new ProductionSecurity);
        $security->shouldReceive('repairPrivateDirectoryPermissions')->andReturn(true);
        $security->shouldReceive('failures')->andReturn([]);
        $this->app->instance(ProductionSecurity::class, $security);
        $this->get('https://www.shop.example.test/fr?q=wood')->assertRedirect('https://shop.example.test/fr?q=wood')->assertStatus(301);
        $this->get('https://homya.eu/fr')->assertStatus(400);
    }

    public function test_catalogue_local_sql_guard_stays_closed_in_production(): void
    {
        DB::shouldReceive('connection')->never();
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LOCAL_READER_CONTEXT_REQUIRED');
        app(LocalCatalogueReadGuard::class)->requireContext();
    }

    public function test_preflight_reports_unsafe_targets_without_database_or_credential_output(): void
    {
        DB::shouldReceive('connection')->never();
        config([
            'catalogue.source' => 'sql',
            'catalogue.archive_path' => '/private/tmp/missing-hostinger-catalogue-'.bin2hex(random_bytes(8)),
            'commerce.enabled' => true,
            'database.connections.commerce.username' => 'root',
            'database.connections.commerce.password' => 'secret-preflight-fixture',
            'mail.default' => 'log',
        ]);
        $this->assertSame(1, Artisan::call('homya:hostinger-preflight', ['--json' => true]));
        $output = Artisan::output();
        $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($result['checks']['catalogue_files_source']);
        $this->assertFalse($result['checks']['catalogue_files_present']);
        $this->assertFalse($result['checks']['commerce_target_not_xampp']);
        $this->assertFalse($result['checks']['mail_transport_ready_for_checkout']);
        $this->assertStringNotContainsString('secret-preflight-fixture', $output);
    }

    public function test_checkout_preflight_uses_the_same_sender_and_contact_requirements_as_order_quotes(): void
    {
        DB::shouldReceive('connection')->never();
        Mail::shouldReceive('send')->never();
        config([
            'commerce.enabled' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.from.address' => 'sales@example.test',
            'contact.email' => 'help@example.test',
        ]);
        Artisan::call('homya:hostinger-preflight', ['--json' => true]);
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($result['checks']['mail_transport_ready_for_checkout']);

        config(['contact.email' => 'sales@example.test']);
        Artisan::call('homya:hostinger-preflight', ['--json' => true]);
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($result['checks']['mail_transport_ready_for_checkout']);

        config(['mail.from.address' => 'invalid', 'contact.email' => 'invalid']);
        Artisan::call('homya:hostinger-preflight', ['--json' => true]);
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($result['checks']['mail_transport_ready_for_checkout']);
    }
}
