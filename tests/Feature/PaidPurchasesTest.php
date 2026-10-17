<?php

namespace Tests\Feature;

use App\Services\Commerce\OrderStore;
use App\Services\Commerce\PaidPurchases;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaidPurchasesTest extends TestCase
{
    private string $id;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['database.connections.commerce' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('commerce');
        (require database_path('migrations/commerce/2026_10_09_000001_create_commerce_tables.php'))->up();
        (require database_path('migrations/commerce/2026_10_12_000003_add_destination_vat_and_order_quote_delivery.php'))->up();
        (require database_path('migrations/commerce/2026_10_17_000005_create_paid_purchase_measurement.php'))->up();
        $this->id = (string) Str::uuid();
        $db = app(OrderStore::class)->connection();
        $db->table('commerce_inventory')->insert(['slug' => 'fixture', 'reference' => 'FIXTURE', 'quantity' => 10, 'reserved' => 2, 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $db->table('commerce_orders')->insert(['id' => $this->id, 'owner_hash' => str_repeat('a', 64), 'checkout_key' => (string) Str::uuid(), 'request_hash' => str_repeat('b', 64),
            'locale' => 'fr', 'country' => 'FR', 'currency' => 'EUR', 'subtotal_cents' => 24690, 'shipping_cents' => 0, 'total_cents' => 24690,
            'customer_encrypted' => Crypt::encryptString(json_encode(['email' => 'private@example.invalid', 'bank_transfer' => ['details' => ['iban' => 'private-fixture']]], JSON_THROW_ON_ERROR)),
            'status' => 'pending_bank_transfer', 'created_at' => now(), 'updated_at' => now()]);
        $db->table('commerce_order_items')->insert(['order_id' => $this->id, 'slug' => 'fixture', 'line_key' => 'fixture', 'reference' => 'FIXTURE', 'name' => 'Fixture',
            'quantity' => 2, 'unit_price_cents' => 12345, 'line_cents' => 24690, 'vat_class' => 'standard', 'vat_rate_basis' => 2000]);
    }

    public function test_only_bank_receipt_confirmation_creates_a_purchase_and_reconciliation_is_idempotent(): void
    {
        $service = app(PaidPurchases::class);
        $orders = app(OrderStore::class);
        $from = now()->subDay()->format('Y-m-d H:i:s');
        $to = now()->addDay()->format('Y-m-d H:i:s');
        $this->assertNull($service->event($this->id));
        $this->assertSame(0, $service->reconcile($from, $to, true)['new_records']);
        $orders->confirmBankReceipt($this->id, 24690, 'private-bank-fixture');
        $orders->confirmBankReceipt($this->id, 24690, 'private-bank-fixture');
        $event = $service->event($this->id);
        $this->assertSame('purchase', $event['name']);
        $this->assertSame($this->id, $event['params']['transaction_id']);
        $this->assertSame('EUR', $event['params']['currency']);
        $this->assertEquals(205.75, $event['params']['value']);
        $this->assertEquals(41.15, $event['params']['tax']);
        $this->assertSame(2, $event['params']['items'][0]['quantity']);
        $this->assertSame('homya-'.substr(hash('sha256', 'fixture'), 0, 40), $event['params']['items'][0]['item_id']);
        $this->assertStringNotContainsString('private', json_encode($event));
        $dry = $service->reconcile($from, $to);
        $this->assertSame(1, $dry['verified_purchases']);
        $this->assertSame(0, $orders->connection()->table('commerce_paid_purchases')->count());
        $this->assertSame(1, $service->reconcile($from, $to, true)['new_records']);
        $this->assertSame(0, $service->reconcile($from, $to, true)['new_records']);
        $row = $orders->connection()->table('commerce_paid_purchases')->first();
        $this->assertSame($event, json_decode(Crypt::decryptString($row->event_encrypted), true));
        $this->assertSame(0, $service->reconcile($from, $to)['external_events_sent']);
        Http::assertNothingSent();
    }

    public function test_forged_paid_status_without_matching_payment_ledger_is_not_a_purchase(): void
    {
        $db = app(OrderStore::class)->connection();
        $db->table('commerce_orders')->where('id', $this->id)->update(['status' => 'paid', 'paid_at' => now()]);
        $this->assertNull(app(PaidPurchases::class)->event($this->id));
        $customer = ['bank_transfer' => ['receipt' => ['method' => 'merchant_verified_private_receipt', 'amount_cents' => 24690, 'bank_reference' => 'forged']]];
        $db->table('commerce_orders')->where('id', $this->id)->update(['customer_encrypted' => Crypt::encryptString(json_encode($customer))]);
        $this->assertNull(app(PaidPurchases::class)->event($this->id));
        $db->table('commerce_payment_events')->insert(['stripe_event_id' => 'bank_receipt_'.hash('sha256', 'forged'), 'order_id' => $this->id, 'payload_hash' => str_repeat('0', 64), 'outcome' => 'bank_paid', 'created_at' => now()]);
        $this->assertNull(app(PaidPurchases::class)->event($this->id));
    }

    public function test_wrong_amount_and_expired_transfer_never_generate_a_purchase(): void
    {
        $orders = app(OrderStore::class);
        try {
            $orders->confirmBankReceipt($this->id, 1, 'wrong-amount');
            $this->fail('Wrong amount accepted.');
        } catch (\RuntimeException) {
            $this->assertNull(app(PaidPurchases::class)->event($this->id));
        }
        $this->travel(73)->hours();
        $orders->expireBankTransfers();
        $this->assertNull(app(PaidPurchases::class)->event($this->id));
        $this->assertSame(0, $orders->connection()->table('commerce_paid_purchases')->count());
    }

    public function test_missing_tax_snapshot_is_reported_without_interrupting_payment_or_sending_google_events(): void
    {
        $orders = app(OrderStore::class);
        $orders->connection()->table('commerce_order_items')->where('order_id', $this->id)->update(['vat_rate_basis' => null]);
        $orders->confirmBankReceipt($this->id, 24690, 'fixture-no-tax');
        $this->assertSame('paid', $orders->connection()->table('commerce_orders')->value('status'));
        $summary = app(PaidPurchases::class)->reconcile(now()->subDay()->format('Y-m-d H:i:s'), now()->addDay()->format('Y-m-d H:i:s'), true);
        $this->assertSame(1, $summary['unverified_or_incomplete']);
        $this->assertSame(1, $summary['verified_purchases']);
        $this->assertSame(24690, $summary['received_cents']);
        $this->assertSame(0, $summary['measurement_ready']);
        $this->assertSame(0, $summary['new_records']);
        Http::assertNothingSent();
    }

    public function test_command_reports_only_the_receipt_date_and_rejects_invalid_periods(): void
    {
        app(OrderStore::class)->confirmBankReceipt($this->id, 24690, 'fixture-period');
        $from = now()->startOfDay()->format('Y-m-d');
        $to = now()->addDay()->format('Y-m-d');
        $this->artisan('homya:measure-paid-purchases', ['--from' => $from, '--to' => $to])->assertSuccessful();
        $this->assertSame(0, app(OrderStore::class)->connection()->table('commerce_paid_purchases')->count());
        $this->artisan('homya:measure-paid-purchases', ['--from' => $from, '--to' => $to, '--apply' => true])->assertSuccessful();
        $this->assertSame(1, app(OrderStore::class)->connection()->table('commerce_paid_purchases')->count());
        $this->artisan('homya:measure-paid-purchases', ['--from' => '2026-02-30', '--to' => '2026-03-05'])->assertFailed();
        $this->artisan('homya:measure-paid-purchases', ['--from' => $to, '--to' => $from])->assertFailed();
        $summary = app(PaidPurchases::class)->reconcile(now()->addDay()->format('Y-m-d H:i:s'), now()->addDays(2)->format('Y-m-d H:i:s'));
        $this->assertSame(0, $summary['verified_purchases']);
    }

    public function test_scheduled_measurement_is_disabled_by_default_and_respects_the_configured_start(): void
    {
        $this->artisan('homya:measure-paid-purchases', ['--scheduled' => true, '--apply' => true])->assertFailed();
        app(OrderStore::class)->confirmBankReceipt($this->id, 24690, 'fixture-scheduled');
        config(['purchase_measurement.enabled' => true, 'purchase_measurement.start_date' => now('UTC')->format('Y-m-d')]);
        $this->artisan('homya:measure-paid-purchases', ['--scheduled' => true, '--apply' => true])->assertSuccessful();
        $this->artisan('homya:measure-paid-purchases', ['--scheduled' => true, '--apply' => true])->assertSuccessful();
        $this->assertSame(1, app(OrderStore::class)->connection()->table('commerce_paid_purchases')->count());
    }
}
