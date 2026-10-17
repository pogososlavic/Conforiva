<?php

namespace App\Services\Commerce;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/** First-party measurement from the payment ledger, independent of browser returns. */
final class PaidPurchases
{
    public function event(string $id): ?array
    {
        $order = $this->verifiedOrder($id);
        if ($order === null) {
            return null;
        }

        return $this->payload($order);
    }

    private function verifiedOrder(string $id): ?object
    {
        $db = app(OrderStore::class)->connection();
        $order = $db->table('commerce_orders')->where('id', $id)->first();
        if (! $order || $order->status !== 'paid' || ! $order->paid_at || $order->currency !== 'EUR') {
            return null;
        }
        $customer = json_decode(Crypt::decryptString($order->customer_encrypted), true, 32, JSON_THROW_ON_ERROR);
        $receipt = $customer['bank_transfer']['receipt'] ?? null;
        if (! is_array($receipt) || ($receipt['method'] ?? '') !== 'merchant_verified_private_receipt'
            || ($receipt['amount_cents'] ?? null) !== (int) $order->total_cents
            || ! is_string($receipt['bank_reference'] ?? null) || $receipt['bank_reference'] === '') {
            return null;
        }
        $reference = $receipt['bank_reference'];
        $payment = $db->table('commerce_payment_events')->where('order_id', $id)
            ->where('stripe_event_id', 'bank_receipt_'.hash('sha256', $reference))->where('outcome', 'bank_paid')->first();
        $expected = hash('sha256', json_encode([$id, (int) $order->total_cents, 'EUR', $reference], JSON_THROW_ON_ERROR));
        if (! $payment || ! hash_equals($expected, $payment->payload_hash)) {
            return null;
        }

        return $order;
    }

    private function payload(object $order): array
    {
        $id = $order->id;
        $db = app(OrderStore::class)->connection();

        $items = $db->table('commerce_order_items')->where('order_id', $id)->orderBy('line_key')->get();
        if ($items->isEmpty() || (int) $items->sum('line_cents') !== (int) $order->subtotal_cents
            || (int) $order->subtotal_cents + (int) $order->shipping_cents !== (int) $order->total_cents) {
            throw new RuntimeException('Purchase snapshot mismatch.');
        }
        $taxCents = 0;
        $netCents = 0;
        $payloadItems = [];
        foreach ($items as $item) {
            if (! isset($item->vat_rate_basis) || ! is_numeric($item->vat_rate_basis)
                || $item->vat_rate_basis < 0 || $item->vat_rate_basis > 10000
                || $item->quantity < 1 || (int) $item->line_cents !== (int) $item->unit_price_cents * (int) $item->quantity) {
                throw new RuntimeException('Purchase tax snapshot unavailable.');
            }
            $basis = (int) $item->vat_rate_basis;
            $lineNet = intdiv((int) $item->line_cents * 10000 + intdiv(10000 + $basis, 2), 10000 + $basis);
            $netCents += $lineNet;
            $taxCents += (int) $item->line_cents - $lineNet;
            $payloadItems[] = ['item_id' => 'homya-'.substr(hash('sha256', $item->slug), 0, 40).($item->variant_id !== '' ? '-'.$item->variant_id : ''), 'item_name' => mb_substr($item->name, 0, 100),
                'item_variant' => mb_substr($item->variant_reference ?: $item->variant_name, 0, 100),
                'price' => $lineNet / (int) $item->quantity / 100, 'quantity' => (int) $item->quantity];
        }
        // The existing checkout ships free. Refuse to invent a shipping tax rate if that changes.
        if ((int) $order->shipping_cents !== 0) {
            throw new RuntimeException('Shipping tax definition required.');
        }

        return ['name' => 'purchase', 'paid_at' => Carbon::parse($order->paid_at)->utc()->toIso8601String(),
            'received_cents' => (int) $order->total_cents,
            'params' => ['transaction_id' => $id, 'currency' => 'EUR', 'value' => $netCents / 100,
                'tax' => $taxCents / 100, 'shipping' => 0, 'items' => $payloadItems]];
    }

    /** Reconcile only verified receipts in the requested period; never sends data externally. */
    public function reconcile(string $from, string $to, bool $apply = false): array
    {
        $db = app(OrderStore::class)->connection();
        if ($apply && ! $db->getSchemaBuilder()->hasTable('commerce_paid_purchases')) {
            throw new RuntimeException('Purchase migration required.');
        }
        $query = $db->table('commerce_orders')->where('status', 'paid')
            ->where('paid_at', '>=', $from)->where('paid_at', '<', $to)->orderBy('id');
        $summary = ['verified_purchases' => 0, 'received_cents' => 0, 'measurement_ready' => 0, 'unverified_or_incomplete' => 0,
            'new_records' => 0, 'currency' => 'EUR', 'applied' => $apply, 'external_events_sent' => 0];
        foreach ($query->lazyById(100) as $order) {
            try {
                $verified = $this->verifiedOrder($order->id);
            } catch (\Throwable) {
                $verified = null;
            }
            if ($verified === null) {
                $summary['unverified_or_incomplete']++;

                continue;
            }
            $summary['verified_purchases']++;
            $summary['received_cents'] += (int) $verified->total_cents;
            try {
                $event = $this->payload($verified);
            } catch (\Throwable) {
                $summary['unverified_or_incomplete']++;

                continue;
            }
            $summary['measurement_ready']++;
            if ($apply) {
                // One immutable snapshot per transaction even for concurrent or repeated reconciliations.
                $summary['new_records'] += $db->table('commerce_paid_purchases')->insertOrIgnore([
                    'order_id' => $order->id, 'paid_at' => $order->paid_at, 'received_cents' => $event['received_cents'],
                    'currency' => $event['params']['currency'],
                    'event_encrypted' => Crypt::encryptString(json_encode($event, JSON_THROW_ON_ERROR)), 'created_at' => now(),
                ]);
            }
        }

        return $summary;
    }
}
