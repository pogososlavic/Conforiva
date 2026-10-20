<?php

namespace Tests\Feature;

use App\Services\ReturnRequests;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReturnRequestsTest extends TestCase
{
    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        Mail::fake();
        $this->temporaryStorage = sys_get_temp_dir().'/homya-returns-test-'.Str::uuid();
        $this->app->useStoragePath($this->temporaryStorage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    private function declaration(): array
    {
        return ['name' => 'Synthetic test customer', 'contract' => 'TEST-ORDER-ONLY: test goods', 'method' => 'download', 'logistics' => 'bulky'];
    }

    public function test_phone_is_preserved_privately_and_confirmation_is_shown_after_saving(): void
    {
        $this->get('/fr/retours/declaration')->assertOk()
            ->assertSee('name="phone"', false)->assertDontSee('id="return-method"', false)
            ->assertDontSee('E-mail de contact (facultatif)');
        $nonce = session('returns.nonce');
        $data = $this->declaration() + ['phone' => '+33 6 12 34 56 78'];
        $this->post('/fr/retours/verifier', $data)->assertOk()->assertSee('+33 6 12 34 56 78');
        $this->post('/fr/retours/confirmer', ['nonce' => $nonce])->assertOk();
        $id = session('returns.last.id');
        $this->get('/fr/retours/'.$id.'/accuse')->assertOk()->assertSee(__('returns.confirmation_message'));
        $this->assertSame($data['phone'], app(ReturnRequests::class)->find($id)['declaration']['phone']);
        $this->get('/fr/retours/'.$id.'/document')->assertOk()->assertSee($data['phone']);
    }

    public function test_invalid_phone_is_rejected_before_confirmation(): void
    {
        $this->get('/fr/retours/declaration');
        $this->post('/fr/retours/verifier', $this->declaration() + ['phone' => '<script>invalid</script>'])
            ->assertRedirect()->assertSessionHasErrors('phone');
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/private/returns');
    }

    public function test_notification_is_not_recorded_until_explicit_confirmation_and_repeated_confirmation_is_idempotent(): void
    {
        $this->get('/fr/retours/declaration')->assertOk()->assertSee('Renoncer au contrat ici');
        $nonce = session('returns.nonce');
        $this->post('/fr/retours/verifier', $this->declaration())->assertOk()->assertSee('Confirmer la rétractation');
        $this->assertStringNotContainsString('Synthetic test customer', session('returns.draft'));
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/private/returns');
        $response = $this->post('/fr/retours/confirmer', ['nonce' => $nonce])->assertOk();
        $id = session('returns.last.id');
        $response->assertHeader('Content-Disposition', 'attachment; filename="HOME-CONFORT-retractation-'.$id.'.html"')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $content = $response->getContent();
        $submittedAt = app(ReturnRequests::class)->find($id)['submitted_at'];
        $this->travel(1)->hours();
        $repeated = $this->post('/fr/retours/confirmer', ['nonce' => $nonce])->assertOk();
        $this->assertSame($content, $repeated->getContent());
        $this->assertSame($submittedAt, app(ReturnRequests::class)->find($id)['submitted_at']);
        $files = File::files($this->temporaryStorage.'/app/private/returns');
        $this->assertCount(1, $files);
        $this->assertStringNotContainsString('Synthetic test customer', File::get($files[0]));
        $this->assertSame(0600, fileperms($files[0]->getPathname()) & 0777);
        $record = app(ReturnRequests::class)->find($id);
        $this->assertSame($this->declaration(), $record['declaration']);
        $this->assertSame('received', $record['status']);
        $this->assertNotEmpty($record['submitted_at']);
        $this->get('/fr/retours/'.$id.'/accuse')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->travelBack();
    }

    public function test_acknowledgement_is_private_and_can_be_retained_offline_without_executable_customer_input(): void
    {
        $data = $this->declaration();
        $data['name'] = '<script>alert(1)</script>';
        $record = app(ReturnRequests::class)->save($data);
        $url = '/fr/retours/'.$record['id'].'/document';
        $this->get($url)->assertNotFound()->assertDontSee('alert(1)');
        $response = $this->withSession(['returns.access.'.$record['id'] => true])->get($url)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>', false)->assertSee($record['submitted_at']);
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->get('/fr/retours/'.Str::uuid().'/accuse')->assertNotFound();
    }

    public function test_missing_reference_does_not_block_withdrawal_and_no_reason_or_bank_details_are_required(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/retours/declaration')->assertOk()->assertSee(__('returns.start', [], $locale));
        }
        $this->post('/fr/retours/verifier', ['name' => 'Synthetic customer', 'contract' => 'A stove received yesterday; reference unavailable', 'method' => 'download'])
            ->assertOk()->assertSee('Confirmer la rétractation');
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/private/returns');
        $this->post('/fr/retours/confirmer', ['nonce' => 'tampered'])->assertStatus(419);
    }

    public function test_invalid_or_oversized_inputs_cannot_create_a_record(): void
    {
        $this->get('/fr/retours/declaration');
        $this->post('/fr/retours/verifier', ['name' => ['invalid'], 'contract' => str_repeat('x', 501), 'method' => 'email', 'email' => "invalid\r\nvalue", 'notes' => str_repeat('x', 1001)])
            ->assertRedirect()->assertSessionHasErrors(['name', 'contract', 'method', 'email', 'notes']);
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/private/returns');
    }

    public function test_storage_failure_never_claims_that_the_declaration_was_received(): void
    {
        $this->get('/fr/retours/declaration');
        $nonce = session('returns.nonce');
        $this->post('/fr/retours/verifier', $this->declaration())->assertOk();
        File::ensureDirectoryExists($this->temporaryStorage.'/app/private');
        File::put($this->temporaryStorage.'/app/private/returns', 'synthetic-storage-conflict');
        $this->post('/fr/retours/confirmer', ['nonce' => $nonce])->assertStatus(503)
            ->assertDontSee('synthetic-sensitive-error')->assertDontSee('Déclaration de rétractation reçue');
    }

    public function test_download_method_is_an_explicit_required_choice_without_an_initial_default(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/retours/declaration')->assertOk();
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new DOMXPath($document);
            $this->assertSame(1, $xpath->query('//input[@name="method" and @type="radio" and @value="download" and @required]')->length);
            $this->assertSame(0, $xpath->query('//input[@name="method" and (@checked or @type="hidden")]')->length);
            $this->assertSame(1, $xpath->query('//label[@for="return-method-download"]')->length);
            $this->post('/'.$locale.'/retours/verifier', ['name' => 'Synthetic customer', 'contract' => 'TEST-CONTRACT'])
                ->assertRedirect()->assertSessionHasErrors('method');
        }
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/private/returns');
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Http::assertNothingSent();
    }

    public function test_confirmation_directly_transmits_the_private_document_and_it_is_recoverable_in_every_language(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/retours/declaration')->assertOk();
            $nonce = session('returns.nonce');
            $this->post('/'.$locale.'/retours/verifier', $this->declaration())->assertOk()
                ->assertSee(__('returns.before_confirmation', [], $locale));
            // Editing the unconfirmed draft retains only the consumer's explicit choice.
            $this->get('/'.$locale.'/retours/declaration')->assertOk()
                ->assertSee('value="download" required aria-describedby="return-method-help" checked', false);
            $nonce = session('returns.nonce');
            $response = $this->post('/'.$locale.'/retours/confirmer', ['nonce' => $nonce])->assertOk()
                ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertSee(__('returns.received', [], $locale))
                ->assertSee($this->declaration()['contract']);
            $id = session('returns.last.id');
            $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
            $record = app(ReturnRequests::class)->find($id);
            $this->assertSame($locale, $record['locale']);
            $this->assertSame('download', $record['declaration']['method']);
            $response->assertSee($record['submitted_at']);
            $recovery = '/'.$locale.'/retours/'.$id.'/accuse';
            $this->get('/'.$locale.'/retours/declaration')->assertOk()
                ->assertSee($recovery)->assertSee(__('returns.latest_receipt', [], $locale));
            $this->get($recovery)->assertOk()->assertSee($id);
        }
        $this->assertCount(3, File::files($this->temporaryStorage.'/app/private/returns'));
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Http::assertNothingSent();
    }

    public function test_recovery_link_and_document_are_not_exposed_to_another_session(): void
    {
        $record = app(ReturnRequests::class)->save($this->declaration());
        $id = $record['id'];
        $this->withSession(['returns.last' => ['id' => $id, 'nonce' => 'SYNTHETIC-ONLY']])
            ->get('/fr/retours/declaration')->assertOk()->assertDontSee($id);
        $this->get('/fr/retours/'.$id.'/accuse')->assertNotFound();
        $this->get('/fr/retours/'.$id.'/document')->assertNotFound();
    }

    public function test_retry_after_document_render_failure_reuses_the_saved_declaration_and_timestamp(): void
    {
        $this->get('/fr/retours/declaration');
        $nonce = session('returns.nonce');
        $this->post('/fr/retours/verifier', $this->declaration())->assertOk();
        $failFirstRender = true;
        View::composer('returns.document', function () use (&$failFirstRender) {
            if ($failFirstRender) {
                $failFirstRender = false;
                throw new \RuntimeException('SYNTHETIC-PRIVATE-RENDER-FAILURE');
            }
        });
        $this->post('/fr/retours/confirmer', ['nonce' => $nonce])->assertStatus(503)
            ->assertDontSee('SYNTHETIC-PRIVATE-RENDER-FAILURE');
        $id = session('returns.last.id');
        $submittedAt = app(ReturnRequests::class)->find($id)['submitted_at'];
        $this->post('/fr/retours/confirmer', ['nonce' => $nonce])->assertOk()->assertSee($id)->assertSee($submittedAt);
        $this->assertCount(1, File::files($this->temporaryStorage.'/app/private/returns'));
        $this->assertSame($id, session('returns.last.id'));
        $this->assertSame($submittedAt, app(ReturnRequests::class)->find($id)['submitted_at']);
    }
}
