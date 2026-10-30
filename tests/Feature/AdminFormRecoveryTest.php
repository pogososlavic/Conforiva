<?php

namespace Tests\Feature;

use App\Services\AdminAccess;
use App\Services\Documents\DocumentData;
use App\Services\Documents\DocumentStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminFormRecoveryTest extends TestCase
{
    private string $temporary;

    private string $originalStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->originalStorage = storage_path();
        $this->temporary = sys_get_temp_dir().'/homya-admin-recovery-test-'.Str::uuid();
        File::ensureDirectoryExists($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config(['dashboard.enabled' => true, 'admin.username' => 'recovery-owner',
            'admin.password_hash' => Hash::make('synthetic-recovery-owner-password'),
            'admin.accounts_path' => $this->temporary.'/app/private/admin/accounts.encrypted',
            'commerce.bank.enabled' => false, 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::shouldReceive('connection')->never();
        Http::preventStrayRequests();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Mail::assertNothingSent();
        $this->app->useStoragePath($this->originalStorage);
        File::deleteDirectory($this->temporary);
        parent::tearDown();
    }

    private function owner(): array
    {
        return ['admin.auth' => ['id' => 'owner', 'username' => 'recovery-owner', 'role' => 'owner',
            'fingerprint' => app(AdminAccess::class)->fingerprint(), 'started' => now()->timestamp, 'seen' => now()->timestamp]];
    }

    private function draft(): array
    {
        $data = app(DocumentData::class)->blank('invoice');
        $data['customer'] = array_replace($data['customer'], ['name' => 'Synthetic saved customer',
            'email' => 'synthetic@example.invalid', 'country' => 'FR']);
        $data['tax_mode'] = 'taxed';
        $data['lines'] = [['reference' => 'SYNTHETIC-ITEM', 'name' => 'Synthetic saved line',
            'quantity' => 2, 'price' => '12.34', 'vat_rate' => '20', 'eco' => '0']];

        return app(DocumentStore::class)->create('invoice', copy: $data);
    }

    public function test_document_recovery_is_display_only_and_keeps_the_submitted_revision(): void
    {
        $record = $this->draft();
        $marker = '<script>synthetic-document-recovery</script>';
        foreach ([1, null, 'invalid', 0] as $revision) {
            $input = $record['data'];
            if ($revision !== null) {
                $input['revision'] = $revision;
            }
            $input['customer']['email'] = 'invalid-synthetic-email';
            $input['customer']['name'] = $marker;
            $input['password'] = 'SYNTHETIC_PASSWORD_NEVER_DISPLAYED';
            $shownRevision = $revision === 1 ? 1 : '';
            $response = $this->withSession($this->owner() + ['_old_input' => ['secret' => 'SYNTHETIC_OLD_SECRET']])
                ->post('/admin/documents/'.$record['id'], $input)->assertUnprocessable()
                ->assertViewHas('form_revision', $shownRevision)
                ->assertViewHas('recovery_revision_valid', $revision === 1)
                ->assertViewHas('data', fn ($data) => $data['customer']['name'] === $marker && $data['total_cents'] === 2468)
                ->assertSee(e($marker), false)->assertDontSee($marker, false)
                ->assertSee('name="revision" value="'.$shownRevision.'"', false)
                ->assertSee('Émission indisponible')->assertSessionMissing('_old_input')
                ->assertDontSee('SYNTHETIC_PASSWORD_NEVER_DISPLAYED')->assertDontSee('SYNTHETIC_OLD_SECRET');
            $response->assertDontSee('action="'.route('admin.documents.issue', ['id' => $record['id']]).'"', false);
            $this->assertSame($record, app(DocumentStore::class)->find($record['id']));
            $this->assertSame([], glob($this->temporary.'/app/private/commercial-documents/*-issued.encrypted'));
        }
    }

    public function test_document_json_validation_never_renders_or_flashes_private_inputs(): void
    {
        $record = $this->draft();
        $input = $record['data'] + ['revision' => 1];
        $input['customer']['email'] = 'invalid-synthetic-email';
        $input['customer']['name'] = 'SYNTHETIC_PRIVATE_CUSTOMER';
        $this->withSession($this->owner())->postJson('/admin/documents/'.$record['id'], $input)
            ->assertUnprocessable()->assertJsonValidationErrors('customer.email')
            ->assertDontSee('SYNTHETIC_PRIVATE_CUSTOMER')->assertSessionMissing('_old_input');
        $this->assertSame($record, app(DocumentStore::class)->find($record['id']));
    }

    public function test_sensitive_validation_returns_to_its_fixed_route_without_flashing_secrets(): void
    {
        $this->withSession($this->owner() + ['_old_input' => ['secret' => 'SYNTHETIC_OLD_SECRET'],
            '_previous' => ['url' => 'https://example.invalid/untrusted-history']])
            ->from('https://example.invalid/untrusted-referer')
            ->post('/admin/parametres', ['secret' => 'SYNTHETIC_SUBMITTED_SECRET', 'body' => 'SYNTHETIC_PRIVATE_TEXT'])
            ->assertRedirect('/admin/parametres')->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input');
        $this->assertFileDoesNotExist($this->temporary.'/.env');
    }

    public function test_expired_admin_session_returns_json_401_before_reading_private_data(): void
    {
        $owner = $this->owner();
        $owner['admin.auth']['seen'] -= (int) config('admin.idle_seconds') + 1;
        $this->withSession($owner)->getJson('/admin/notifications')->assertUnauthorized()
            ->assertJsonPath('code', 'admin_session_expired')->assertSessionMissing('admin.auth')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_login_throttle_keeps_retry_headers_and_never_discloses_credentials(): void
    {
        $credentials = ['username' => 'recovery-owner', 'password' => 'SYNTHETIC_WRONG_PASSWORD'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/admin/connexion', $credentials)->assertRedirect('/admin/connexion');
        }
        $this->post('/admin/connexion', $credentials)->assertStatus(429)->assertHeader('Retry-After')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('SYNTHETIC_WRONG_PASSWORD')->assertDontSee(config('admin.password_hash'))
            ->assertSessionMissing('_old_input.password');
    }
}
