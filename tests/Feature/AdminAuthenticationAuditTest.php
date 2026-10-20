<?php

namespace Tests\Feature;

use App\Services\AdminAuthenticationAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class AdminAuthenticationAuditTest extends TestCase
{
    public function test_login_failure_success_and_logout_are_logged_without_credentials(): void
    {
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        config([
            'admin.username' => 'audit-fixture-owner',
            'admin.password_hash' => Hash::make('audit-fixture-password-12345'),
            'admin.accounts_path' => sys_get_temp_dir().'/audit-no-accounts-'.bin2hex(random_bytes(8)),
        ]);
        $events = [];
        $logger = Mockery::mock();
        $logger->shouldReceive('notice')->times(3)->withArgs(function ($message, $context) use (&$events) {
            $this->assertSame('ADMIN_AUTHENTICATION', $message);
            $this->assertSame(['outcome', 'subject', 'source', 'reference'], array_keys($context));
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $context['subject']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $context['source']);
            $encoded = json_encode($context);
            foreach (['audit-fixture-owner', 'audit-fixture-password-12345', 'incorrect-password', '127.0.0.1'] as $private) {
                $this->assertStringNotContainsString($private, $encoded);
            }
            $events[] = $context['outcome'];

            return true;
        });
        Log::shouldReceive('channel')->with('security')->times(3)->andReturn($logger);
        $this->post('/admin/connexion', ['username' => 'audit-fixture-owner', 'password' => 'incorrect-password'])->assertSessionHasErrors('username');
        $this->post('/admin/connexion', ['username' => 'audit-fixture-owner', 'password' => 'audit-fixture-password-12345'])->assertRedirect('/admin');
        $this->post('/admin/deconnexion')->assertRedirect('/admin/connexion')->assertSessionMissing('admin.auth');
        $this->assertSame(['failure', 'success', 'logout'], $events);
    }

    public function test_log_failure_does_not_escape_or_disclose_request_data(): void
    {
        Log::shouldReceive('channel')->with('security')->once()->andThrow(new \RuntimeException('unavailable log'));
        app(AdminAuthenticationAudit::class)->record(Request::create('/admin/connexion'), 'failure', 'private-fixture');
        $this->assertTrue(true);
    }
}
