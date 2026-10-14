<?php

namespace App\Services\Commerce;

use App\Services\Admin\Messages;
use App\Services\AdminDashboard;
use App\Services\Documents\DocumentData;
use App\Services\Documents\DocumentStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Throwable;

/** Creates one immutable official quote and submits its PDF to the configured HOMYA support mailer. */
final class AutomaticOrderQuote
{
    public function sendFor(object $order): void
    {
        if (! config('commerce.quote_email.enabled')) {
            return;
        }

        $db = app(OrderStore::class)->connection();
        if (! Schema::connection('commerce')->hasTable('commerce_order_quotes')) {
            Log::warning('HOMYA_ORDER_QUOTE_BLOCKED', ['reason' => 'migration_missing']);

            return;
        }

        $claimed = $db->transaction(function () use ($db, $order) {
            $db->table('commerce_order_quotes')->insertOrIgnore([
                'order_id' => $order->id,
                'status' => 'preparing',
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = $db->table('commerce_order_quotes')->where('order_id', $order->id)->lockForUpdate()->first();
            if (! $row || in_array($row->status, ['sending', 'submitted', 'unknown'], true)) {
                return null;
            }
            if ($row->status === 'preparing' && $row->attempt_started_at !== null
                && Carbon::parse($row->attempt_started_at)->gt(now()->subMinutes(5))) {
                return null;
            }

            $db->table('commerce_order_quotes')->where('order_id', $order->id)->update([
                'status' => 'preparing', 'blocked_reason' => null,
                'attempts' => (int) $row->attempts + 1,
                'attempt_started_at' => now(), 'updated_at' => now(),
            ]);

            return $db->table('commerce_order_quotes')->where('order_id', $order->id)->first();
        });
        if (! $claimed) {
            return;
        }

        $stage = 'smtp_readiness';
        try {
            if (! app(Messages::class)->emailReady() || ! filter_var(config('contact.email'), FILTER_VALIDATE_EMAIL)) {
                $this->block($db, $order->id, 'support_smtp_unavailable');

                return;
            }
            if (! in_array($order->status, ['pending_payment_details', 'pending_bank_transfer', 'pending_payment'], true)) {
                $this->block($db, $order->id, 'order_not_active');

                return;
            }

            $stage = 'order_snapshot';
            $source = app(AdminDashboard::class)->order($order->id);
            foreach ($source['items'] as $item) {
                if (! is_numeric($item->vat_rate_basis ?? null) || ! in_array($item->vat_class ?? null, ['standard', 'wood_heating'], true)) {
                    $this->block($db, $order->id, 'tax_snapshot_missing');

                    return;
                }
            }
            $email = (string) ($source['customer']['email'] ?? '');
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->block($db, $order->id, 'customer_email_missing');

                return;
            }

            $stage = 'quote_generation';
            $language = isset(DocumentData::LANGUAGES[$order->locale]) ? $order->locale : 'fr';
            $data = app(DocumentData::class)->blank('quote', $order->id);
            $deadline = app(BankTransfer::class)->deadline($order)->setTimezone('Europe/Paris');
            $data['document_language'] = $language;
            $data['tax_mode'] = 'taxed';
            $data['tax_note'] = __('documents.destination_vat_note', [], $language);
            $data['valid_until'] = $deadline->toDateString();
            $data['valid_until_at'] = $deadline->toIso8601String();
            $data['payment_terms'] = __('documents.order_quote_payment_terms', [], $language);
            $data['delivery_terms'] = __('documents.order_quote_delivery_terms', [], $language);
            $data['eco_reviewed'] = true;
            $data['guarantee_reviewed'] = true;

            $documentStore = app(DocumentStore::class);
            $documentId = $claimed->document_id;
            $record = $documentId ? $documentStore->find($documentId) : null;
            if (! $record) {
                $record = collect($documentStore->forOrder($order->id, false))
                    ->first(fn (array $item) => ($item['origin'] ?? null) === 'automatic_order_quote');
            }
            if (! $record) {
                $record = $documentStore->create('quote', $order->id, $data, origin: 'automatic_order_quote');
            }
            $documentId = $record['id'];
            $db->table('commerce_order_quotes')->where('order_id', $order->id)->update([
                'document_id' => $documentId, 'updated_at' => now(),
            ]);

            $stage = 'quote_issue';
            if ($record['status'] !== 'issued') {
                $record = $documentStore->issue($documentId, $record['revision'], [
                    'series_confirmed' => true,
                    'first_sequence' => (int) config('documents.numbering.first_sequence'),
                    'document_language' => $language,
                ]);
            }
            // The RIB is optional for an order quote. When no valid account is
            // configured, the PDF omits the bank-details section but is still issued and emailed.
            $issues = app(DocumentData::class)->missing($record['data'], allowMissingBankDetails: true);
            if ($issues !== []) {
                $this->block($db, $order->id, 'quote_validation_failed');

                return;
            }

            $stage = 'pdf_render';
            $pdf = $documentStore->pdf($record);
            $safeNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', $record['number']);
            $name = trim((string) ($source['customer']['name'] ?? ''));
            $mailText = __('documents.order_quote_email_body', [
                'name' => $name,
                'number' => $record['number'],
                'deadline' => $this->localizedDeadline($deadline, $language),
            ], $language);
            $mailData = [
                'name' => $name,
                'number' => $record['number'],
                'deadline' => $this->localizedDeadline($deadline, $language),
                'mailText' => $mailText,
                'language' => $language,
            ];
            $stage = 'email_render';
            $htmlBody = view('emails.order-quote', $mailData)->render();
            $textBody = view('emails.order-quote-text', $mailData)->render();

            // Only mark an attempt ambiguous once the PDF and both email bodies
            // are ready and control is about to pass to the SMTP transport.
            $db->table('commerce_order_quotes')->where('order_id', $order->id)->update([
                'status' => 'sending', 'blocked_reason' => null, 'updated_at' => now(),
            ]);
            $stage = 'smtp_dispatch';
            Mail::send([
                'html' => new HtmlString($htmlBody),
                'text' => new HtmlString($textBody),
            ], [], function ($message) use ($email, $record, $pdf, $safeNumber, $language) {
                $message->to($email)
                    ->from(config('contact.email'), 'HOMYA')
                    ->subject(__('documents.order_quote_email_subject', ['number' => $record['number']], $language))
                    ->attachData($pdf, $safeNumber.'.pdf', ['mime' => 'application/pdf']);
            });
            $db->table('commerce_order_quotes')->where('order_id', $order->id)->update([
                'status' => 'submitted', 'submitted_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $error) {
            $row = $db->table('commerce_order_quotes')->where('order_id', $order->id)->first();
            $sending = $row && $row->status === 'sending';
            $this->block($db, $order->id, $sending ? 'email_result_unknown' : 'quote_generation_failed', $sending ? 'unknown' : 'blocked');
            // Provider messages and arbitrary exception codes may contain customer
            // or transport secrets; retain only structural diagnostics.
            Log::error('HOMYA_ORDER_QUOTE_FAILED', [
                'order_reference' => substr(hash('sha256', (string) $order->id), 0, 16),
                'stage' => $stage,
                'error_type' => get_class($error),
            ]);
        }
    }

    private function block($db, string $orderId, string $reason, string $status = 'blocked'): void
    {
        $db->table('commerce_order_quotes')->where('order_id', $orderId)->update([
            'status' => $status, 'blocked_reason' => $reason, 'updated_at' => now(),
        ]);
    }

    private function localizedDeadline(Carbon $deadline, string $language): string
    {
        return match ($language) {
            'en' => $deadline->format('d/m/Y H:i').' (Paris time)',
            'de' => $deadline->format('d.m.Y H:i').' Uhr (Pariser Zeit)',
            default => $deadline->format('d/m/Y à H:i').' (heure de Paris)',
        };
    }
}
