<?php

namespace Tests\Feature;

use App\Services\AdminDashboard;
use App\Services\Commerce\AutomaticOrderQuote;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class AutomaticOrderQuoteSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['legal.company.capital' => 'Capital synthétique de test', 'legal.company.rcs' => 'Immatriculation synthétique de test']);
        config([
            'commerce.quote_email.enabled' => true,
            'database.connections.commerce' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.invalid',
            'mail.from.address' => 'support@example.invalid',
            'contact.email' => 'support@example.invalid',
        ]);
        DB::purge('commerce');
        // Only the quote-delivery ledger is needed to exercise the error handler.
        Schema::connection('commerce')->create('commerce_order_quotes', function (Blueprint $table) {
            $table->uuid('order_id')->primary();
            $table->string('status');
            $table->unsignedInteger('attempts');
            $table->timestamp('attempt_started_at')->nullable();
            $table->string('blocked_reason')->nullable();
            $table->timestamps();
        });
        Mail::fake();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        DB::purge('commerce');
        parent::tearDown();
    }

    public function test_quote_failures_never_log_exception_messages_or_private_values(): void
    {
        $values = ['CUSTOMER_FIXTURE', 'ADDRESS_FIXTURE', 'IBAN_FIXTURE', 'SMTP_FIXTURE'];
        $message = 'CUSTOMER_FIXTURE Alice Example ADDRESS_FIXTURE 10 rue fictive '
            .'IBAN_FIXTURE DE36000000000000000000 '
            .'https://fixture:SMTP_FIXTURE@smtp.example.invalid/transport';

        $this->assertFailureLoggedWithoutPayload(new RuntimeException($message, 1234), $values);
    }

    public function test_quote_failures_never_log_arbitrary_string_exception_codes(): void
    {
        $error = new class('SQL_QUERY_FIXTURE') extends \PDOException
        {
            public function __construct(string $message)
            {
                parent::__construct($message);
                $this->code = 'PRIVATE_ERROR_CODE_FIXTURE';
            }
        };

        $this->assertFailureLoggedWithoutPayload($error, ['SQL_QUERY_FIXTURE', 'PRIVATE_ERROR_CODE_FIXTURE']);
    }

    private function assertFailureLoggedWithoutPayload(Throwable $error, array $privateValues): void
    {
        // Force a provider failure before any document or SMTP operation.
        $this->app->instance(AdminDashboard::class, new class($error)
        {
            public function __construct(private Throwable $error) {}

            public function order(string $id): array
            {
                throw $this->error;
            }
        });
        $logged = null;
        Log::shouldReceive('error')->once()->andReturnUsing(function (string $event, array $context) use (&$logged) {
            $this->assertSame('HOMYA_ORDER_QUOTE_FAILED', $event);
            $logged = $context;
        });
        $order = (object) ['id' => (string) Str::uuid(), 'status' => 'pending_payment_details', 'locale' => 'fr'];

        app(AutomaticOrderQuote::class)->sendFor($order);

        $this->assertSame([
            'order_reference' => substr(hash('sha256', $order->id), 0, 16),
            'stage' => 'order_snapshot',
            'error_type' => get_class($error),
        ], $logged);
        $encoded = json_encode($logged, JSON_THROW_ON_ERROR);
        foreach ($privateValues as $value) {
            $this->assertStringNotContainsString($value, $encoded);
        }
        $this->assertStringNotContainsString($order->id, $encoded);
        $ledger = DB::connection('commerce')->table('commerce_order_quotes')->where('order_id', $order->id)->first();
        $this->assertSame('blocked', $ledger->status);
        $this->assertSame('quote_generation_failed', $ledger->blocked_reason);
        $this->assertSame(1, $ledger->attempts);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Http::assertNothingSent();
    }
}
