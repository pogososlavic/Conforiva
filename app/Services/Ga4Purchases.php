<?php

namespace App\Services;

use App\Services\Commerce\OrderStore;
use App\Services\Commerce\PaidPurchases;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

final class Ga4Purchases
{
    public function dispatch(bool $apply = false, bool $validate = false): array
    {
        $summary = ['eligible' => 0, 'submitted' => 0, 'validated' => 0, 'skipped' => 0, 'unknown' => 0];
        if (! app(AnalyticsConsent::class)->enabled()) {
            return $summary;
        }
        if (($apply || $validate) && ! preg_match('/\A[A-Za-z0-9_-]{10,128}\z/', (string) config('analytics.api_secret'))) {
            throw new \RuntimeException('GA4 transport not configured.');
        }
        $db = app(OrderStore::class)->connection();
        if ($apply || $validate) {
            $db->table('commerce_analytics_consents')->where('expires_at', '<=', now())
                ->update(['granted' => false, 'client_id_encrypted' => null]);
        }
        $rows = $db->table('commerce_paid_purchases as p')->join('commerce_orders as o', 'o.id', '=', 'p.order_id')
            ->where('o.status', 'paid')->where('p.paid_at', '>=', now()->subHours(72))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('commerce_analytics_deliveries as d')->whereColumn('d.order_id', 'p.order_id'))
            ->select(['p.order_id', 'o.customer_encrypted'])->orderBy('p.order_id')->limit(100)->get();
        foreach ($rows as $row) {
            $customer = json_decode(Crypt::decryptString($row->customer_encrypted), true, 32, JSON_THROW_ON_ERROR);
            $context = $customer['analytics'] ?? [];
            $consentId = $context['consent_id'] ?? '';
            if (($context['measurement_id'] ?? '') !== config('analytics.measurement_id') || ! is_string($consentId)
                || ! app(AnalyticsConsent::class)->clientId($consentId)) {
                $summary['skipped']++;

                continue;
            }
            $event = app(PaidPurchases::class)->event($row->order_id);
            if (! $event || count($event['params']['items']) > 200) {
                $summary['skipped']++;

                continue;
            }
            $summary['eligible']++;
            if (! $apply && ! $validate) {
                continue;
            }
            if (! $validate && $db->table('commerce_analytics_deliveries')->insertOrIgnore(['order_id' => $row->order_id, 'status' => 'sending', 'updated_at' => now()]) !== 1) {
                $summary['skipped']++;

                continue;
            }
            // Serialize with consent withdrawal; no order/payment lock or payment transaction is held.
            $outcome = $db->transaction(function () use ($db, $row, $consentId, $event, $validate) {
                $db->table('commerce_analytics_consents')->where('id', $consentId)->lockForUpdate()->first();
                $clientId = app(AnalyticsConsent::class)->clientId($consentId);
                if (! $clientId) {
                    return 'skipped';
                }
                $payload = ['client_id' => $clientId, 'timestamp_micros' => (int) (Carbon::parse($event['paid_at'])->getTimestamp() * 1000000),
                    'consent' => ['ad_user_data' => 'DENIED', 'ad_personalization' => 'DENIED'],
                    'events' => [['name' => 'purchase', 'params' => $event['params']]]];
                try {
                    $endpoint = $validate ? '/debug/mp/collect' : '/mp/collect';
                    $url = 'https://region1.google-analytics.com'.$endpoint.'?'.http_build_query([
                        'measurement_id' => config('analytics.measurement_id'), 'api_secret' => config('analytics.api_secret')]);
                    // Never log this request URL or an exception containing its secret.
                    $response = Http::timeout(8)->connectTimeout(3)->withoutRedirecting()->post($url, $payload);
                    $outcome = $validate
                        ? ($response->successful() && is_array($response->json('validationMessages')) && $response->json('validationMessages') === [] ? 'validated' : 'unknown')
                        : ($response->successful() ? 'submitted' : 'unknown');
                } catch (\Throwable) {
                    $outcome = 'unknown';
                }
                if (! $validate) {
                    $db->table('commerce_analytics_deliveries')->where('order_id', $row->order_id)->update(['status' => $outcome, 'updated_at' => now()]);
                }

                return $outcome;
            });
            $summary[$outcome]++;
        }

        return $summary;
    }
}
