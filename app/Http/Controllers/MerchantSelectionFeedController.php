<?php

namespace App\Http\Controllers;

use App\Services\MerchantSelectionFeed;
use App\Services\Seo;
use Illuminate\Http\Request;
use Throwable;

final class MerchantSelectionFeedController extends Controller
{
    public function __invoke(Request $request, MerchantSelectionFeed $feed, Seo $seo)
    {
        abort_unless(config('merchant-selection.enabled') === true && $seo->onPublicOrigin($request), 404);

        try {
            if (! $feed->ready()) {
                throw new \RuntimeException('Selection feed prerequisites unavailable.');
            }
            $body = $feed->xml();
        } catch (Throwable) {
            // Neither partial products, stale snapshots nor private diagnostics may escape.
            return response('Merchant feed unavailable.', 503)
                ->header('Content-Type', 'text/plain; charset=UTF-8')
                ->header('Cache-Control', 'no-store, private')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response($body)->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
