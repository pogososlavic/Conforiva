<?php

namespace Tests\Feature;

use App\Services\Admin\BankDetailsSettings;
use App\Services\Admin\Messages;
use App\Services\Commerce\AutomaticOrderQuote;
use App\Services\Documents\DocumentStore;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BankDetailsAvailabilityTest extends TestCase
{
    private object $documents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.url' => 'http://localhost', 'commerce.enabled' => true, 'commerce.legal_ready' => true,
            'commerce.quote_email.enabled' => false, 'mail.default' => 'array',
            'database.connections.commerce' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'commerce.bank' => ['enabled' => true, 'beneficiary' => 'Synthetic beneficiary', 'bank_name' => 'Synthetic bank',
                'iban' => 'DE36000000000000000000', 'bic' => 'TESTDE00']]);
        DB::purge('commerce');
        (require database_path('migrations/commerce/2026_10_09_000001_create_commerce_tables.php'))->up();
        (require database_path('migrations/commerce/2026_10_12_000003_add_destination_vat_and_order_quote_delivery.php'))->up();
        $this->app->instance(BankDetailsSettings::class, new class
        {
            public function current(): array
            {
                return config('commerce.bank');
            }
        });
        $this->documents = new class
        {
            public array $records = [];

            public int $reads = 0;

            public bool $failPdf = false;

            public function find(string $id): array
            {
                $this->reads++;
                if (! isset($this->records[$id])) {
                    throw new \RuntimeException('SYNTHETIC_PRIVATE_STORAGE_ERROR');
                }

                return $this->records[$id];
            }

            public function pdf(array $record): string
            {
                if ($this->failPdf) {
                    throw new \RuntimeException('SYNTHETIC_PRIVATE_PDF_ERROR');
                }

                return '%PDF-SYNTHETIC-OFFICIAL-'.$record['id'];
            }
        };
        $this->app->instance(DocumentStore::class, $this->documents);
        Http::preventStrayRequests();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        DB::purge('commerce');
        parent::tearDown();
    }

    private function order(): object
    {
        $id = (string) Str::uuid();
        $customer = ['name' => 'Synthetic customer', 'email' => 'customer@example.invalid', 'address' => 'Synthetic address',
            'postal_code' => '75001', 'city' => 'Synthetic city', 'country' => 'FR',
            'bank_transfer' => ['details' => config('commerce.bank')]];
        DB::connection('commerce')->table('commerce_orders')->insert(['id' => $id, 'owner_hash' => hash('sha256', 'synthetic-owner'),
            'checkout_key' => Str::uuid(), 'request_hash' => str_repeat('a', 64), 'locale' => 'fr', 'country' => 'FR', 'currency' => 'EUR',
            'subtotal_cents' => 12001, 'shipping_cents' => 0, 'total_cents' => 12001,
            'customer_encrypted' => Crypt::encryptString(json_encode($customer)), 'status' => 'pending_bank_transfer',
            'created_at' => now(), 'updated_at' => now()]);

        return DB::connection('commerce')->table('commerce_orders')->where('id', $id)->first();
    }

    private function quote(object $order, string $emailStatus = 'submitted', array $overrides = []): array
    {
        $id = (string) Str::uuid();
        $record = array_replace_recursive(['id' => $id, 'number' => 'SYNTHETIC/QUOTE-001', 'status' => 'issued',
            'data' => ['type' => 'quote', 'order_id' => $order->id, 'bank_details' => config('commerce.bank')]], $overrides);
        $this->documents->records[$id] = $record;
        DB::connection('commerce')->table('commerce_order_quotes')->insert(['order_id' => $order->id, 'document_id' => $id,
            'status' => $emailStatus, 'attempts' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $record;
    }

    public function test_missing_quote_with_unconfigured_mail_keeps_reference_deadline_and_support_without_false_email_or_pdf(): void
    {
        $order = $this->order();
        config(['commerce.quote_email.enabled' => true]);
        $this->app->instance(Messages::class, new class
        {
            public function emailReady(): bool
            {
                return false;
            }
        });
        app(AutomaticOrderQuote::class)->sendFor($order);
        $this->assertSame('blocked', DB::connection('commerce')->table('commerce_order_quotes')->value('status'));
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->withSession(['commerce.owner' => 'synthetic-owner'])->get('/'.$locale.'/commande/'.$order->id.'/virement')
                ->assertOk()->assertSee($order->id)->assertSee(__('commerce.bank_document_pending', [], $locale))
                ->assertSee('bank-deadline', false)->assertSee('/'.$locale.'/contact', false)
                ->assertDontSee('/document/bank', false)->assertDontSee(__('commerce.bank_email_notice', [], $locale))
                ->assertDontSee('DE36000000000000000000')->assertDontSee('SYNTHETIC_PRIVATE');
        }
        $this->get('/fr/commande/'.$order->id.'/document/bank')->assertNotFound();
        $this->get('/fr/commande/'.$order->id.'/document/summary')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Http::assertNothingSent();
    }

    public function test_issued_quote_links_to_exact_pdf_and_email_notice_only_after_confirmed_submission(): void
    {
        foreach (['submitted', 'blocked', 'unknown', 'sending'] as $status) {
            $order = $this->order();
            $record = $this->quote($order, $status);
            $response = $this->withSession(['commerce.owner' => 'synthetic-owner'])
                ->get('/fr/commande/'.$order->id.'/virement?total=1&document_id=untrusted')
                ->assertOk()->assertSee('/commande/'.$order->id.'/document/bank', false)->assertSee('120,01 €')
                ->assertDontSee('DE36000000000000000000')->assertDontSee('Synthetic beneficiary');
            $response->assertSee(__($status === 'submitted' ? 'commerce.bank_email_notice' : 'commerce.bank_email_unconfirmed'));
            $response->assertDontSee(__($status === 'submitted' ? 'commerce.bank_email_unconfirmed' : 'commerce.bank_email_notice'));
            foreach (['bank', 'summary'] as $kind) {
                $download = $this->get('/fr/commande/'.$order->id.'/document/'.$kind)->assertOk()
                    ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
                $this->assertSame('%PDF-SYNTHETIC-OFFICIAL-'.$record['id'], $download->getContent());
                $this->assertSame('attachment; filename="SYNTHETIC-QUOTE-001.pdf"', $download->headers->get('Content-Disposition'));
            }
        }
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_foreign_draft_wrong_type_missing_or_broken_pdf_never_produces_a_bank_link(): void
    {
        foreach (['foreign', 'draft', 'type', 'bank_missing', 'missing_record', 'broken_pdf'] as $case) {
            $order = $this->order();
            $overrides = match ($case) {
                'foreign' => ['data' => ['order_id' => (string) Str::uuid()]],
                'draft' => ['status' => 'draft'],
                'type' => ['data' => ['type' => 'invoice']],
                'bank_missing' => ['data' => ['bank_details' => null]],
                default => [],
            };
            $record = $this->quote($order, 'submitted', $overrides);
            if ($case === 'missing_record') {
                unset($this->documents->records[$record['id']]);
            }
            $this->documents->failPdf = $case === 'broken_pdf';
            $this->withSession(['commerce.owner' => 'synthetic-owner'])->get('/fr/commande/'.$order->id.'/virement')
                ->assertOk()->assertSee(__('commerce.bank_document_pending'))->assertDontSee('/document/bank', false)
                ->assertDontSee(__('commerce.bank_email_notice'))->assertDontSee('SYNTHETIC_PRIVATE');
            $this->get('/fr/commande/'.$order->id.'/document/bank')->assertNotFound();
        }
    }

    public function test_foreign_owner_is_denied_before_reading_document_and_expiry_blocks_bank_download(): void
    {
        $order = $this->order();
        $this->quote($order);
        foreach (['virement', 'document/bank', 'document/summary'] as $suffix) {
            $this->withSession(['commerce.owner' => 'another-owner'])->get('/fr/commande/'.$order->id.'/'.$suffix)->assertNotFound();
        }
        $this->assertSame(0, $this->documents->reads);
        $this->travel(73)->hours();
        $this->withSession(['commerce.owner' => 'synthetic-owner'])->get('/fr/commande/'.$order->id.'/virement')
            ->assertOk()->assertSee(__('commerce.bank_expired'))->assertDontSee('/document/bank', false);
        $this->get('/fr/commande/'.$order->id.'/document/bank')->assertNotFound();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }
}
