<?php

namespace Tests\Feature;

use App\Services\AdminAccess;
use App\Services\Documents\DocumentData;
use App\Services\Documents\DocumentStore;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CommercialDocumentsTest extends TestCase
{
    private string $temporary;

    private string $originalStorage;

    protected function setUp(): void
    {
        parent::setUp();
        config(['legal.company.capital' => 'Capital synthétique de test', 'legal.company.rcs' => 'Immatriculation synthétique de test']);
        $this->withoutVite();
        $this->originalStorage = storage_path();
        $this->temporary = sys_get_temp_dir().'/homya-documents-test-'.Str::uuid();
        File::ensureDirectoryExists($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config(['dashboard.enabled' => true, 'admin.username' => 'fixture', 'admin.password_hash' => Hash::make('fixture-password'), 'database.connections.commerce' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        // Artificial account of zeroes with a valid checksum; never use a real RIB.
        config(['commerce.bank' => ['enabled' => true, 'beneficiary' => 'Bénéficiaire fictif de test', 'bank_name' => 'Banque fictive de test', 'iban' => 'DE36000000000000000000', 'bic' => 'TESTDE00'], 'mail.default' => 'array']);
        DB::purge('commerce');
        (require database_path('migrations/commerce/2026_10_09_000001_create_commerce_tables.php'))->up();
        (require database_path('migrations/commerce/2026_10_09_000002_create_admin_tables.php'))->up();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStorage);
        DB::purge('commerce');
        File::deleteDirectory($this->temporary);
        parent::tearDown();
    }

    private function auth(): array
    {
        return ['admin.auth' => ['id' => 'owner', 'username' => 'fixture', 'role' => 'owner', 'fingerprint' => app(AdminAccess::class)->fingerprint(), 'started' => now()->timestamp, 'seen' => now()->timestamp]];
    }

    private function complete(string $type = 'invoice'): array
    {
        // Synthetic tax and commercial terms, never HOMYA business defaults.
        $data = app(DocumentData::class)->blank($type);
        $data['customer'] = array_replace($data['customer'], ['name' => 'Client synthétique Élodie', 'email' => 'fixture@example.invalid', 'address' => '12 rue de démonstration', 'postal_code' => '75001', 'city' => 'Paris', 'country' => 'FR']);
        $data['tax_mode'] = 'taxed';
        $data['valid_until'] = now()->addDays(5)->toDateString();
        $data['operation_date'] = now('Europe/Paris')->toDateString();
        $data['due_date'] = now()->addDays(2)->toDateString();
        $data['payment_terms'] = 'Conditions fictives de recette uniquement';
        $data['delivery_terms'] = 'Livraison de démonstration';
        $data['discount_terms'] = 'Escompte fictif : néant';
        $data['eco_reviewed'] = $data['guarantee_reviewed'] = true;
        $data['lines'] = [['reference' => 'FIXTURE-001', 'name' => 'Article de démonstration', 'quantity' => 2, 'price' => '12,01', 'vat_rate' => '20', 'eco' => '0,02']];

        return $data;
    }

    private function draft(string $type = 'invoice'): array
    {
        return app(DocumentStore::class)->create($type, copy: $this->complete($type));
    }

    private function issue(array $record, ?callable $renderer = null): array
    {
        return app(DocumentStore::class)->issue($record['id'], $record['revision'], ['prefix' => 'HOMYA', 'first_sequence' => 1, 'series_confirmed' => true], $renderer ?? fn () => '%PDF-fixture');
    }

    public function test_routes_require_private_admin_and_enabled_module(): void
    {
        $this->get('/admin/documents')->assertRedirect('/admin/connexion')->assertHeader('Cache-Control', 'no-store, private');
        $record = $this->draft();
        $this->get('/admin/documents/'.$record['id'].'/pdf')->assertRedirect('/admin/connexion');
        config(['dashboard.enabled' => false]);
        $this->withSession($this->auth())->get('/admin/documents')->assertStatus(503)->assertDontSee('Client synthétique');
    }

    public function test_dashboard_creation_save_pdf_and_search_are_operational(): void
    {
        $this->withSession($this->auth())->post('/admin/documents', ['type' => 'quote'])->assertRedirect();
        $record = app(DocumentStore::class)->all()[0];
        $this->get('/admin/documents/'.$record['id'])->assertOk()->assertSee('À compléter avant émission')->assertSee('Brouillon', false);
        $this->post('/admin/documents/'.$record['id'], $this->complete('quote') + ['revision' => 1])->assertRedirect();
        $this->get('/admin/documents?q='.rawurlencode('Élodie').'&type=quote')->assertOk()->assertSee('Client synthétique Élodie')->assertSee('24,02');
        $this->get('/admin/documents/'.$record['id'].'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(0, DB::connection('commerce')->table('commerce_orders')->count());
    }

    public function test_all_draft_types_generate_pdfs_without_issuing_documents(): void
    {
        foreach (['quote', 'purchase_order', 'invoice'] as $type) {
            $draft = $this->draft($type);
            $store = app(DocumentStore::class);

            $this->assertStringStartsWith('%PDF-', $store->pdf($draft));
            $this->assertSame('draft', $store->find($draft['id'])['status']);
            $this->assertNull($store->currentSeries($type));
        }
        $this->assertSame([], glob(storage_path('app/private/commercial-documents/*-issued.encrypted')));
        $this->assertSame(0, DB::connection('commerce')->table('commerce_orders')->count());
    }

    public function test_exact_cent_calculation_rates_and_exemption_do_not_assume_tax(): void
    {
        $input = $this->complete();
        $result = app(DocumentData::class)->normalize($input, $input);
        $this->assertSame(2402, $result['total_cents']);
        $this->assertSame(2002, $result['net_cents']);
        $this->assertSame(400, $result['tax_cents']);
        $input['lines'][0]['vat_rate'] = '';
        $result = app(DocumentData::class)->normalize($input, $input);
        $this->assertNull($result['net_cents']);
        $this->assertNotEmpty(app(DocumentData::class)->missing($result));
        $input['tax_mode'] = 'exempt';
        $input['tax_note'] = 'Mention légale fictive pour test';
        $result = app(DocumentData::class)->normalize($input, $input);
        $this->assertSame(2402, $result['net_cents']);
        $this->assertSame(0, $result['tax_cents']);
        $this->assertEmpty(app(DocumentData::class)->missing($result));
        $input['lines'][] = ['reference' => 'TEST-LOW', 'name' => 'Article test', 'quantity' => 1, 'price' => '5.50', 'vat_rate' => '5.5', 'eco' => ''];
        $input['tax_mode'] = 'taxed';
        $input['lines'][0]['vat_rate'] = '20';
        $result = app(DocumentData::class)->normalize($input, $input);
        $this->assertSame(521, $result['lines'][1]['net_cents']);
        $this->assertSame(29, $result['lines'][1]['tax_cents']);
        $this->assertSame($result['total_cents'], $result['net_cents'] + $result['tax_cents']);
    }

    public function test_equivalent_vat_rates_group_together_and_country_is_validated(): void
    {
        $input = $this->complete();
        $input['customer']['country'] = 'fr';
        $input['lines'][] = array_replace($input['lines'][0], ['vat_rate' => '20.00', 'reference' => str_repeat('R', 255), 'name' => str_repeat('D', 600)]);
        $data = app(DocumentData::class)->normalize($input, $input);
        $this->assertSame('FR', $data['customer']['country']);
        $this->assertSame([20 => 800], $data['tax_groups']);
        $input['customer']['country'] = '12';
        $this->expectException(ValidationException::class);
        app(DocumentData::class)->normalize($input, $input);
    }

    public function test_required_invoice_and_quote_information_blocks_emission(): void
    {
        foreach (['quote', 'purchase_order', 'invoice'] as $type) {
            $record = app(DocumentStore::class)->create($type);
            try {
                $this->issue($record);
                $this->fail('An incomplete document must not issue.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors()['document']);
            }
            $this->assertSame('draft', app(DocumentStore::class)->find($record['id'])['status']);
            $this->assertNull(app(DocumentStore::class)->currentSeries($type));
        }
        $input = $this->complete();
        $input['professional'] = true;
        $result = app(DocumentData::class)->normalize($input, $input);
        $this->assertStringContainsString('professionnel', implode(' ', app(DocumentData::class)->missing($result)));
        $input['operation_date'] = now('Europe/Paris')->addDay()->toDateString();
        $input['tax_mode'] = 'reverse';
        $result = app(DocumentData::class)->normalize($input, $input);
        $this->assertGreaterThanOrEqual(3, count(app(DocumentData::class)->missing($result)));
        $this->assertStringContainsString('numéro de TVA', implode(' ', app(DocumentData::class)->missing($result)));
    }

    public function test_numbering_requires_confirmation_and_uses_one_series_for_all_types_and_languages(): void
    {
        $record = $this->draft();
        try {
            app(DocumentStore::class)->issue($record['id'], 1, []);
            $this->fail('No unconfirmed allocation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('numbering', $e->errors());
        }
        foreach ([0, 8, 41] as $wrongFirstSequence) {
            try {
                app(DocumentStore::class)->issue($record['id'], 1, ['series_confirmed' => true, 'prefix' => 'HOMYA', 'first_sequence' => $wrongFirstSequence], fn () => '%PDF-fixture');
                $this->fail('The confirmed first sequence cannot be replaced.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('numbering', $e->errors());
            }
            $this->assertNull(app(DocumentStore::class)->currentSeries('invoice'));
            $this->assertSame('draft', app(DocumentStore::class)->find($record['id'])['status']);
        }
        $first = $this->issue($record);
        $this->assertSame('HOMYA/FR-001', $first['number']);
        $next = $this->issue($this->draft());
        $this->assertSame('HOMYA/FR-002', $next['number']);
        $quote = $this->draft('quote');
        $english = app(DocumentStore::class)->issue($quote['id'], 1, ['document_language' => 'en'], fn () => '%PDF-fixture');
        $this->assertSame('HOMYA/EN-003', $english['number']);
        $purchaseOrder = $this->draft('purchase_order');
        $german = app(DocumentStore::class)->issue($purchaseOrder['id'], 1, ['document_language' => 'de'], fn () => '%PDF-fixture');
        $this->assertSame('HOMYA/DE-004', $german['number']);
        $this->assertSame('en', $english['data']['document_language']);
        $this->assertSame('de', $german['data']['document_language']);
        foreach (array_keys(DocumentData::TYPES) as $type) {
            $this->assertSame(4, app(DocumentStore::class)->currentSeries($type)['sequence']);
        }
    }

    public function test_global_archive_collision_blocks_issuance_without_consuming_a_number(): void
    {
        $issued = $this->issue($this->draft());
        // Simulate inconsistent historic numbering in the isolated encrypted archive.
        $issued['number'] = 'HOMYA/FR-002';
        $path = storage_path('app/private/commercial-documents/'.$issued['id'].'-issued.encrypted');
        file_put_contents($path, Crypt::encryptString(json_encode($issued, JSON_THROW_ON_ERROR)));
        $draft = $this->draft('quote');
        try {
            $this->issue($draft);
            $this->fail('A historic number collision must block issuance.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('numbering', $error->errors());
        }
        $this->assertSame('draft', app(DocumentStore::class)->find($draft['id'])['status']);
        $this->assertNull(app(DocumentStore::class)->find($draft['id'])['number']);
        $this->assertSame(1, app(DocumentStore::class)->currentSeries('quote')['sequence']);
        $this->assertCount(1, glob(storage_path('app/private/commercial-documents/*-issued.encrypted')));
    }

    public function test_frozen_archive_encryption_revisions_idempotence_and_duplicate(): void
    {
        $record = $this->draft('quote');
        $saved = app(DocumentStore::class)->save($record['id'], 1, $record['data']);
        $this->assertSame(2, $saved['revision']);
        $issued = $this->issue($saved);
        config(['legal.company.name' => 'Changed seller', 'legal.company.address' => 'Changed address']);
        $again = app(DocumentStore::class)->issue($record['id'], 2, [], fn () => throw new \RuntimeException('Must not rerender'));
        $this->assertSame($issued, $again);
        $this->assertSame('HOME CONFORT', $again['issuer']['name']);
        $this->assertSame('%PDF-fixture', app(DocumentStore::class)->pdf($again));
        foreach (glob(storage_path('app/private/commercial-documents/*.encrypted')) as $file) {
            $this->assertStringNotContainsString('Élodie', file_get_contents($file));
            $this->assertSame(0600, fileperms($file) & 0777);
        }
        try {
            app(DocumentStore::class)->save($record['id'], 2, $record['data']);
            $this->fail('Issued record is immutable.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }
        $new = app(DocumentStore::class)->create('quote', copy: $again['data']);
        $this->assertNotSame($record['id'], $new['id']);
        $this->assertNull($new['number']);
        $this->assertSame('draft', $new['status']);
        $this->assertSame($issued['pdf_sha256'], app(DocumentStore::class)->find($issued['id'])['pdf_sha256']);
    }

    public function test_stale_revision_and_failed_pdf_do_not_allocate_a_number(): void
    {
        $record = $this->draft();
        app(DocumentStore::class)->save($record['id'], 1, $record['data']);
        try {
            $this->issue($record);
            $this->fail('Stale issue must fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }
        $record = app(DocumentStore::class)->find($record['id']);
        try {
            $this->issue($record, fn () => throw new \RuntimeException('Renderer failed'));
            $this->fail('Renderer failure must not issue.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Renderer failed', $e->getMessage());
        }
        $this->assertNull(app(DocumentStore::class)->currentSeries('invoice'));
        $this->assertSame('draft', app(DocumentStore::class)->find($record['id'])['status']);
        $this->assertSame('HOMYA/FR-001', $this->issue($record)['number']);
    }

    public function test_corrupt_archive_stops_numbering_and_symlinks_are_rejected(): void
    {
        $this->issue($this->draft());
        $path = glob(storage_path('app/private/commercial-documents/*-issued.encrypted'))[0];
        file_put_contents($path, 'corrupted');
        $this->expectException(DecryptException::class);
        $this->issue($this->draft());
    }

    public function test_issue_controller_requires_password_review_and_never_flashes_secrets(): void
    {
        $record = $this->draft();
        $url = '/admin/documents/'.$record['id'].'/emettre';
        $this->withSession($this->auth())->from('/admin/documents/'.$record['id'])->post($url, ['revision' => 1, 'password' => 'wrong', 'confirmed' => 1])->assertSessionHasErrors('password');
        $this->assertNull(session('_old_input'));
        $this->assertSame('draft', app(DocumentStore::class)->find($record['id'])['status']);
        $this->post($url, ['revision' => 1, 'password' => 'fixture-password'])->assertSessionHasErrors('confirmed');
        $this->assertSame('draft', app(DocumentStore::class)->find($record['id'])['status']);
    }

    public function test_http_issuance_pdf_download_notification_and_retry_are_consistent(): void
    {
        $record = $this->draft('quote');
        $url = '/admin/documents/'.$record['id'].'/emettre';
        $payload = ['revision' => 1, 'password' => 'fixture-password', 'confirmed' => 1, 'prefix' => 'HOMYA', 'first_sequence' => 1, 'series_confirmed' => 1];
        $this->withSession($this->auth())->post($url, $payload)->assertRedirect('/admin/documents/'.$record['id']);
        $issued = app(DocumentStore::class)->find($record['id']);
        $this->assertSame('HOMYA/FR-001', $issued['number']);
        $this->get('/admin/documents/'.$record['id'])->assertOk()->assertSee('Archive originale');
        $download = $this->get('/admin/documents/'.$record['id'].'/pdf')->assertOk();
        $this->assertSame($issued['pdf_sha256'], hash('sha256', $download->getContent()));
        $this->post($url, $payload)->assertRedirect();
        $this->assertSame(1, DB::connection('commerce')->table('commerce_admin_events')->where('type', 'document_issued')->count());
        $this->assertSame(1, app(DocumentStore::class)->currentSeries('quote')['sequence']);
        $this->assertSame(0, DB::connection('commerce')->table('commerce_orders')->count());
        $this->post('/admin/documents/'.$record['id'], $this->complete('quote') + ['revision' => 1])->assertSessionHasErrors('document');
    }

    public function test_symlinked_storage_and_tampered_pdf_are_not_read(): void
    {
        $record = $this->issue($this->draft());
        $record['pdf'] = base64_encode('%PDF-tampered');
        try {
            app(DocumentStore::class)->pdf($record);
            $this->fail('Changed PDF must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Document integrity failure.', $e->getMessage());
        }
        $root = storage_path('app/private/commercial-documents');
        $target = $this->temporary.'/symlink-target';
        mkdir($target, 0700);
        File::deleteDirectory($root);
        symlink($target, $root);
        $this->expectException(\RuntimeException::class);
        app(DocumentStore::class)->all();
    }

    public function test_document_limit_does_not_share_notification_request_counter(): void
    {
        $record = $this->draft();
        $this->withSession($this->auth());
        for ($i = 0; $i < 8; $i++) {
            $this->get('/admin/notifications')->assertOk();
        }
        $this->get('/admin/documents/'.$record['id'].'/pdf')->assertOk();
        $this->post('/admin/documents/'.$record['id'].'/emettre', ['revision' => 1, 'confirmed' => 1, 'password' => 'fixture-password', 'series_confirmed' => 1, 'prefix' => 'HOMYA', 'first_sequence' => 1])->assertRedirect();
        $this->assertSame('issued', app(DocumentStore::class)->find($record['id'])['status']);
    }

    public function test_real_pdf_for_all_types_and_html_escaping(): void
    {
        foreach (array_keys(DocumentData::TYPES) as $type) {
            $record = $this->draft($type);
            $record['data']['customer']['name'] = '<script>unexpected</script> & Élodie';
            $record['issuer'] = app(DocumentData::class)->issuer();
            $html = view('documents.pdf', ['record' => $record, 'data' => $record['data'], 'issuer' => $record['issuer'], 'logo' => null])->render();
            $this->assertStringNotContainsString('<script>unexpected</script>', $html);
            $this->assertStringContainsString('&lt;script&gt;unexpected&lt;/script&gt;', $html);
            $pdf = app(DocumentStore::class)->pdf($record);
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertGreaterThan(10000, strlen($pdf));
        }
    }

    public function test_all_three_documents_include_the_supplied_home_confort_identity(): void
    {
        config(['contact.email' => null, 'contact.phone' => null]);
        config(['legal.company.capital' => null, 'legal.company.rcs' => null]);
        $expected = ['HOME CONFORT', 'Société par actions simplifiée (SAS)',
            '1010 A ROUTE DU MAHURY, 97354 RÉMIRE-MONTJOLY', '934757311',
            '93475731100014', 'FR05934757311'];
        foreach (array_keys(DocumentData::TYPES) as $type) {
            $record = $this->draft($type);
            $issuer = app(DocumentData::class)->issuer(requireComplete: false);
            $html = view('documents.pdf', ['record' => $record, 'data' => $record['data'], 'issuer' => $issuer, 'logo' => null])->render();
            $this->assertStringNotContainsString('751 035 122', $html);
            $this->assertStringNotContainsString('David Marechal', $html);
            foreach ($expected as $value) {
                $this->assertStringContainsString(e($value), $html, $type.' issuer identity');
            }
            if ($type === 'quote') {
                $this->assertStringNotContainsString(e('Dirigeant : RAPHAEL RUIZ'), $html);
            } else {
                $this->assertStringContainsString(e('Dirigeant : RAPHAEL RUIZ'), $html);
            }
            $this->assertStringNotContainsString('Email :', $html);
            $this->assertStringNotContainsString('Téléphone :', $html);
            $this->assertStringNotContainsString(config('legal.mediator.email'), $html);
            $this->assertStringNotContainsString(config('legal.mediator.phone'), $html);

            config(['contact.email' => 'fixture-homya@example.invalid', 'contact.phone' => '+33123456789']);
            $issuer = app(DocumentData::class)->issuer(requireComplete: false);
            $html = view('documents.pdf', ['record' => $record, 'data' => $record['data'], 'issuer' => $issuer, 'logo' => null])->render();
            $this->assertStringContainsString('Email : fixture-homya@example.invalid', $html);
            $this->assertStringContainsString('Téléphone : +33123456789', $html);
            config(['contact.email' => null, 'contact.phone' => null]);
        }
    }

    public function test_company_registration_is_present_for_every_type_while_quote_manager_is_private(): void
    {
        config(['legal.company.rcs' => 'SYNTHETIC-RCS', 'legal.company.rcs_registered_on' => 'SYNTHETIC-RCS-DATE',
            'legal.company.rne_registered_on' => 'SYNTHETIC-RNE-DATE', 'legal.company.manager' => 'SYNTHETIC-PRIVATE-MANAGER']);
        foreach (array_keys(DocumentData::TYPES) as $type) {
            $record = $this->draft($type);
            $issuer = app(DocumentData::class)->issuer(requireComplete: false);
            $html = view('documents.pdf', ['record' => $record, 'data' => $record['data'], 'issuer' => $issuer, 'logo' => null])->render();
            foreach (['SYNTHETIC-RCS', 'SYNTHETIC-RCS-DATE', 'SYNTHETIC-RNE-DATE'] as $companyFact) {
                $this->assertStringContainsString($companyFact, $html);
            }
            $this->assertSame($type !== 'quote', str_contains($html, 'SYNTHETIC-PRIVATE-MANAGER'));
        }
    }

    public function test_pdf_unit_net_precision_note_and_totals_remain_consistent_in_each_language(): void
    {
        $record = $this->draft('quote');
        $issuer = app(DocumentData::class)->issuer(requireComplete: false);
        $before = $record['data'];
        foreach (['fr' => [',', ' '], 'en' => ['.', ','], 'de' => [',', '.']] as $language => [$decimal, $thousands]) {
            $html = view('documents.pdf', ['record' => $record, 'data' => $record['data'], 'issuer' => $issuer, 'logo' => null, 'language' => $language])->render();
            $line = $record['data']['lines'][0];
            $this->assertStringContainsString(number_format($line['net_cents'] / ($line['quantity'] * 100), 4, $decimal, $thousands).' €', $html);
            $this->assertStringContainsString(e(__('documents.ex_tax_note', [], $language)), $html);
            $this->assertStringContainsString(e(DocumentData::money($record['data']['total_cents'], $language)), $html);
            $this->assertSame($before, $record['data']);
        }
    }

    public function test_draft_identity_is_available_but_official_issue_requires_missing_registration_facts(): void
    {
        config(['legal.company.capital' => null, 'legal.company.rcs' => null]);
        $record = $this->draft('quote');
        $this->assertSame('HOME CONFORT', app(DocumentData::class)->issuer(requireComplete: false)['name']);
        $this->assertStringStartsWith('%PDF-', app(DocumentStore::class)->pdf($record));
        $this->expectException(ValidationException::class);
        $this->issue($record);
    }

    private function orderFixture(): string
    {
        $id = (string) Str::uuid();
        DB::connection('commerce')->table('commerce_orders')->insert(['id' => $id, 'owner_hash' => hash('sha256', 'private-fixture-owner'), 'checkout_key' => Str::uuid(), 'request_hash' => str_repeat('b', 64), 'locale' => 'fr', 'country' => 'FR', 'currency' => 'EUR', 'subtotal_cents' => 1201, 'shipping_cents' => 0, 'total_cents' => 1201, 'customer_encrypted' => Crypt::encryptString(json_encode($this->complete()['customer'])), 'status' => 'paid', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('commerce')->table('commerce_order_items')->insert(['order_id' => $id, 'slug' => 'fixture', 'line_key' => 'fixture', 'reference' => 'FIXTURE', 'name' => 'Snapshot original', 'quantity' => 1, 'unit_price_cents' => 1201, 'line_cents' => 1201]);

        return $id;
    }

    public function test_order_snapshot_cannot_be_changed_and_client_download_is_owned(): void
    {
        $orderId = $this->orderFixture();
        $record = app(DocumentStore::class)->create('invoice', $orderId);
        $data = array_replace($this->complete(), ['order_id' => $orderId, 'lines' => $record['data']['lines']]);
        $data['lines'][0]['vat_rate'] = '20';
        $saved = app(DocumentStore::class)->save($record['id'], 1, $data);
        $changed = $data;
        $changed['lines'][0]['price'] = '1.00';
        try {
            app(DocumentStore::class)->save($record['id'], 2, $changed);
            $this->fail('Linked order prices are immutable.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('lines', $error->errors());
        }
        $url = '/fr/commande/'.$orderId.'/documents/'.$saved['id'];
        $this->withSession(['commerce.owner' => 'private-fixture-owner'])->get($url)->assertNotFound();
        $issued = $this->issue($saved);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->withSession(['commerce.owner' => 'different-owner'])->get($url)->assertNotFound();
        $manual = $this->issue($this->draft('quote'));
        $this->withSession(['commerce.owner' => 'private-fixture-owner'])->get('/fr/commande/'.$orderId.'/documents/'.$manual['id'])->assertNotFound();
        $this->assertSame('paid', DB::connection('commerce')->table('commerce_orders')->value('status'));
        $another = app(DocumentStore::class)->create('invoice', copy: $issued['data']);
        $this->expectException(ValidationException::class);
        $this->issue($another);
    }

    public function test_concurrent_issuance_allocates_distinct_numbers_under_real_file_lock(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl required for process concurrency test.');
        }
        $records = [$this->draft('quote'), $this->draft('quote')];
        $children = [];
        foreach ($records as $record) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    $this->issue($record, function () {
                        usleep(100000);

                        return '%PDF-concurrency';
                    });
                    exit(0);
                } catch (\Throwable) {
                    exit(1);
                }
            }
            $this->assertGreaterThan(0, $pid);
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        $numbers = array_column(app(DocumentStore::class)->all(), 'number');
        sort($numbers);
        $this->assertSame(['HOMYA/FR-001', 'HOMYA/FR-002'], $numbers);
    }
}
