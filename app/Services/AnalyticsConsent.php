<?php

namespace App\Services;

use App\Services\Commerce\OrderStore;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class AnalyticsConsent
{
    public function enabled(): bool
    {
        return config('analytics.enabled') === true && preg_match('/\AG-[A-Z0-9]{5,20}\z/', (string) config('analytics.measurement_id')) === 1;
    }

    public function current(Request $request): ?object
    {
        $id = $request->cookie('homya_analytics_choice');
        if (! $this->enabled() || ! is_string($id) || ! Str::isUuid($id)) {
            return null;
        }

        return app(OrderStore::class)->connection()->table('commerce_analytics_consents')->where('id', $id)
            ->where('measurement_id', config('analytics.measurement_id'))->where('expires_at', '>', now())->first();
    }

    public function choose(Request $request, bool $granted): string
    {
        $db = app(OrderStore::class)->connection();
        $current = $this->current($request);
        // Revoke the previous identity permanently, including orders already associated with it.
        if ($current) {
            $db->table('commerce_analytics_consents')->where('id', $current->id)->update(['granted' => false, 'client_id_encrypted' => null, 'updated_at' => now()]);
        }
        $id = (string) Str::uuid();
        $db->table('commerce_analytics_consents')->insert(['id' => $id, 'granted' => $granted,
            'measurement_id' => config('analytics.measurement_id'), 'expires_at' => now()->addDays(180), 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function orderContext(Request $request): ?array
    {
        $row = $this->current($request);

        return $row && $row->granted && $row->client_id_encrypted
            ? ['consent_id' => $row->id, 'measurement_id' => $row->measurement_id] : null;
    }

    public function clientId(string $id): ?string
    {
        $row = app(OrderStore::class)->connection()->table('commerce_analytics_consents')->where('id', $id)->first();
        if (! $row || ! $row->granted || Carbon::parse($row->expires_at)->lte(now())
            || $row->measurement_id !== config('analytics.measurement_id') || ! $row->client_id_encrypted) {
            return null;
        }

        return Crypt::decryptString($row->client_id_encrypted);
    }
}
