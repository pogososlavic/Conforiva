<?php

namespace Tests\Feature;

use App\Services\AdminAccess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminAccountsTest extends TestCase
{
    private string $temporary;

    private string $previousStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->previousStorage = storage_path();
        $this->temporary = sys_get_temp_dir().'/homya-accounts-test-'.Str::uuid();
        $this->app->useStoragePath($this->temporary);
        config(['dashboard.enabled' => true, 'admin.username' => 'fixture-owner', 'admin.password_hash' => Hash::make('fixture-owner-password-12345'), 'admin.accounts_path' => $this->temporary.'/app/private/admin/accounts.encrypted']);
        Http::preventStrayRequests();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->previousStorage);
        File::deleteDirectory($this->temporary);
        parent::tearDown();
    }

    public function test_owner_can_create_manager_and_manager_can_login(): void
    {
        $this->post('/admin/connexion', ['username' => 'fixture-owner', 'password' => 'fixture-owner-password-12345'])->assertRedirect('/admin');
        $this->get('/admin/gestionnaires')->assertOk();
        $this->post('/admin/gestionnaires', ['username' => 'fixture-manager', 'password' => 'fixture-manager-password-12345', 'password_confirmation' => 'fixture-manager-password-12345', 'current_password' => 'fixture-owner-password-12345'])->assertRedirect('/admin/gestionnaires');
        $file = config('admin.accounts_path');
        $this->assertSame(0600, fileperms($file) & 0777);
        $this->assertStringNotContainsString('fixture-manager-password-12345', File::get($file));
        $this->post('/admin/deconnexion')->assertRedirect('/admin/connexion');
        $this->post('/admin/connexion', ['username' => 'fixture-manager', 'password' => 'fixture-manager-password-12345'])->assertRedirect('/admin')->assertSessionHas('admin.auth.role', 'manager');
        $this->get('/admin/mon-compte')->assertOk()->assertSee('fixture-manager');
    }

    public function test_manager_creation_requires_owner_reauthentication_and_never_flashes_passwords(): void
    {
        $this->post('/admin/connexion', ['username' => 'fixture-owner', 'password' => 'fixture-owner-password-12345'])->assertRedirect('/admin');
        $input = ['username' => 'fixture-manager', 'password' => 'fixture-manager-password-12345', 'password_confirmation' => 'fixture-manager-password-12345'];
        foreach ([[], ['current_password' => 'wrong-fixture-owner-password']] as $reauthentication) {
            $this->from('https://example.invalid/untrusted-return')->post('/admin/gestionnaires', $input + $reauthentication)
                ->assertRedirect('/admin/gestionnaires')
                ->assertSessionHasErrors('current_password')
                ->assertSessionMissing('_old_input.password')
                ->assertSessionMissing('_old_input.password_confirmation')
                ->assertSessionMissing('_old_input.current_password');
            $this->assertSame(['username' => 'fixture-manager'], session('_old_input'));
            $this->assertFileDoesNotExist(config('admin.accounts_path'));
            $this->assertSame([], app(AdminAccess::class)->accounts());
        }
        $this->postJson('/admin/gestionnaires', $input)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertFileDoesNotExist(config('admin.accounts_path'));
        Mail::assertNothingSent();
    }

    public function test_manager_cannot_open_owner_routes_or_write_protected_data(): void
    {
        app(AdminAccess::class)->createManager('fixture-manager', 'fixture-manager-password-12345');
        $this->post('/admin/connexion', ['username' => 'fixture-manager', 'password' => 'fixture-manager-password-12345'])->assertRedirect('/admin');
        foreach (['/admin/gestionnaires', '/admin/parametres', '/admin/parametres/rib', '/admin/integrations', '/admin/documents'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/gestionnaires', ['username' => 'attacker-manager', 'password' => 'attacker-manager-password-123', 'password_confirmation' => 'attacker-manager-password-123'])->assertForbidden();
        $this->post('/admin/documents', ['type' => 'invoice'])->assertForbidden();
    }

    public function test_manager_password_change_requires_current_password_and_revokes_session(): void
    {
        app(AdminAccess::class)->createManager('fixture-manager', 'fixture-manager-password-12345');
        $this->post('/admin/connexion', ['username' => 'fixture-manager', 'password' => 'fixture-manager-password-12345'])->assertRedirect('/admin');
        $this->post('/admin/mon-compte/mot-de-passe', ['current_password' => 'incorrect-password', 'password' => 'fixture-manager-new-password', 'password_confirmation' => 'fixture-manager-new-password'])->assertSessionHasErrors('current_password');
        $this->post('/admin/mon-compte/mot-de-passe', ['current_password' => 'fixture-manager-password-12345', 'password' => 'fixture-manager-new-password', 'password_confirmation' => 'fixture-manager-new-password'])->assertRedirect('/admin/connexion')->assertSessionMissing('admin.auth');
        $this->post('/admin/connexion', ['username' => 'fixture-manager', 'password' => 'fixture-manager-password-12345'])->assertSessionHasErrors('username');
        $this->post('/admin/connexion', ['username' => 'fixture-manager', 'password' => 'fixture-manager-new-password'])->assertRedirect('/admin');
    }

    public function test_owner_account_and_existing_login_remain_compatible(): void
    {
        $this->post('/admin/connexion', ['username' => 'fixture-owner', 'password' => 'fixture-owner-password-12345'])->assertRedirect('/admin');
        $this->get('/admin/mon-compte')->assertOk()->assertSee('Administrateur principal');
        $this->get('/admin/gestionnaires')->assertOk();
        $this->assertSame([], app(AdminAccess::class)->accounts());
    }
}
