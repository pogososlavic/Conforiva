<?php

namespace App\Http\Middleware;

use App\Services\AdminAccess;
use App\Services\ProductionSecurity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminBoundary
{
    public const CSP = "default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; connect-src 'self'; object-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        // Check the actual peer, not a forwarded header supplied by a client.
        $security = app(ProductionSecurity::class);
        $localAllowed = $security->localRequest($request);
        $remoteAllowed = app()->environment('production')
            && config('admin.remote_enabled') === true
            && $request->getHost() === ProductionSecurity::HOST
            && $request->isSecure()
            && $request->getPort() === 443;

        if (! $localAllowed && ! $remoteAllowed) {
            $reference = $request->attributes->get('homya.error_reference');
            $response = response()->view('errors.page', ['status' => 404, 'reference' => $reference], 404);
        } elseif (! $request->routeIs('admin.login', 'admin.authenticate') && ! app(AdminAccess::class)->valid($request)) {
            $response = redirect()->route('admin.login');
        } else {
            $response = $next($request);
        }
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('X-Frame-Options', 'DENY');
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', self::CSP);
        }

        return $response;
    }
}
