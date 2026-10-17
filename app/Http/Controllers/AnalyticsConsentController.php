<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsConsent;
use App\Services\Commerce\OrderStore;
use App\Services\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

final class AnalyticsConsentController extends Controller
{
    public function state(Request $request, AnalyticsConsent $consent)
    {
        $this->guard($request, $consent);
        $row = $consent->current($request);

        return response()->json(['choice' => $row ? (bool) $row->granted : null, 'csrf' => csrf_token()])
            ->header('Cache-Control', 'no-store, private');
    }

    public function save(Request $request, AnalyticsConsent $consent)
    {
        $this->guard($request, $consent);
        $data = $request->validate(['granted' => 'required|boolean']);
        $id = $consent->choose($request, (bool) $data['granted']);

        return response()->json(['choice' => (bool) $data['granted']])->header('Cache-Control', 'no-store, private')
            ->cookie('homya_analytics_choice', $id, 180 * 24 * 60, '/', null, $request->isSecure(), true, false, 'Lax');
    }

    public function identify(Request $request, AnalyticsConsent $consent)
    {
        $this->guard($request, $consent);
        $data = $request->validate(['client_id' => ['required', 'string', 'max:64', 'regex:/\A[0-9]{1,20}\.[0-9]{1,20}\z/']]);
        $row = $consent->current($request);
        abort_unless($row && $row->granted, 403);
        app(OrderStore::class)->connection()->table('commerce_analytics_consents')->where('id', $row->id)->where('granted', true)
            ->update(['client_id_encrypted' => Crypt::encryptString($data['client_id']), 'updated_at' => now()]);

        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }

    private function guard(Request $request, AnalyticsConsent $consent): void
    {
        abort_unless($consent->enabled() && (app()->environment('testing') || app(Seo::class)->onConfiguredPublicOrigin($request)), 404);
    }
}
