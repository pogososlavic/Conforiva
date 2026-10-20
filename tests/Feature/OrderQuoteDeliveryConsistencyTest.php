<?php

namespace Tests\Feature;

use App\Services\AdminDashboard;
use App\Services\Commerce\AutomaticOrderQuote;
use App\Services\Documents\DocumentData;
use App\Services\Documents\DocumentStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OrderQuoteDeliveryConsistencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'commerce.quote_email.enabled' => true,
            'database.connections.commerce' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.invalid',
            'mail.from.address' => 'support@example.invalid',
            'contact.email' => 'support@example.invalid',
            'seo.public_url' => null,
        ]);
        DB::purge('commerce');
        Schema::connection('commerce')->create('commerce_order_quotes', function (Blueprint $table) {
            $table->uuid('order_id')->primary();
            $table->string('status');
            $table->unsignedInteger('attempts');
            $table->timestamp('attempt_started_at')->nullable();
            $table->string('blocked_reason')->nullable();
            $table->uuid('document_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        DB::purge('commerce');
        parent::tearDown();
    }

    public function test_automatic_quote_preserves_requested_take_back_and_business_day_terms_in_every_language(): void
    {
        $documents = new class
        {
            public array $data = [];

            public function forOrder(string $id, bool $issuedOnly): array
            {
                return [];
            }

            public function create(string $type, string $id, array $data, string $origin): array
            {
                $this->data = $data;

                return ['id' => (string) Str::uuid(), 'status' => 'issued', 'revision' => 1, 'number' => 'SYNTHETIC-QUOTE', 'data' => $data];
            }

            public function pdf(array $record): string
            {
                return '%PDF-SYNTHETIC-ONLY';
            }
        };
        $this->app->instance(DocumentStore::class, $documents);
        // Validate the orchestration and immutable take-back snapshot without real PDF, bank or SMTP I/O.
        $this->app->instance(DocumentData::class, new class
        {
            public function blank(string $type, string $id): array
            {
                return ['delivery_terms' => 'PREVIOUS-TAKE-BACK-DRAFT'];
            }

            public function missing(array $data, bool $allowMissingBankDetails): array
            {
                return [];
            }
        });
        $mailBodies = [];
        Mail::shouldReceive('send')->times(6)->andReturnUsing(function ($views) use (&$mailBodies) {
            $mailBodies[] = (string) $views['text'];
        });
        foreach (['fr' => 'jours ouvrés', 'en' => 'business days', 'de' => 'Arbeitstagen'] as $locale => $businessDays) {
            foreach ([true, false] as $hasTakeBack) {
                $source = ['customer' => ['name' => 'Synthetic customer', 'email' => 'customer@example.invalid'], 'items' => []];
                if ($hasTakeBack) {
                    $source['customer']['take_back'] = [['name' => 'Synthetic appliance', 'reference' => 'TEST-APPLIANCE', 'quantity' => 2]];
                }
                $this->app->instance(AdminDashboard::class, new class($source)
                {
                    public function __construct(private array $source) {}

                    public function order(string $id): array
                    {
                        return $this->source;
                    }
                });
                $order = (object) ['id' => (string) Str::uuid(), 'locale' => $locale, 'status' => 'pending_payment_details', 'created_at' => now()->toDateTimeString()];
                app(AutomaticOrderQuote::class)->sendFor($order);
                $this->assertSame('submitted', DB::connection('commerce')->table('commerce_order_quotes')->where('order_id', $order->id)->value('status'));
                $terms = $documents->data['delivery_terms'];
                $this->assertStringContainsString($businessDays, $terms);
                $this->assertStringContainsString('Europe/Paris', $terms);
                $this->assertStringNotContainsString('PREVIOUS-TAKE-BACK-DRAFT', $terms);
                if ($hasTakeBack) {
                    $this->assertStringContainsString(__('takeback.recorded', [], $locale), $terms);
                    $this->assertStringContainsString('Synthetic appliance — TEST-APPLIANCE : 2', $terms);
                } else {
                    $this->assertStringNotContainsString('TEST-APPLIANCE', $terms);
                }
            }
        }
        foreach ($mailBodies as $body) {
            $this->assertStringNotContainsString('support@homya.eu', $body);
        }
        Http::assertNothingSent();
    }

    public function test_quote_email_links_only_to_a_validated_public_origin(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $data = ['language' => $locale, 'name' => 'Synthetic customer', 'number' => 'TEST-QUOTE', 'deadline' => 'TEST-DEADLINE'];
            foreach ([null, 'http://127.0.0.1:8000', 'https://user:secret@shop.example.org', 'https://shop.example.org?next=bad'] as $invalidOrigin) {
                config(['seo.public_url' => $invalidOrigin]);
                $html = view('emails.order-quote', $data)->render();
                $this->assertStringNotContainsString('homya.eu', $html);
                $this->assertStringNotContainsString('href="https://shop.example.org', $html);
                $this->assertStringNotContainsString('127.0.0.1', $html);
            }
            config(['seo.public_url' => 'https://shop.example.org']);
            $html = view('emails.order-quote', $data)->render();
            $this->assertStringContainsString('href="https://shop.example.org"', $html);
            $this->assertStringNotContainsString('homya.eu', $html);
        }
    }
}
