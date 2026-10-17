<?php

namespace App\Http\Middleware;

use App\Services\AnalyticsConsent;
use App\Services\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(24));
        $request->attributes->set('security.csp_nonce', $nonce);
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        // Inline styles remain supported for the existing interface; scripts require self or a per-request nonce.
        if (! $response->headers->has('Content-Security-Policy')) {
            $analytics = app(AnalyticsConsent::class)->enabled() && ! $request->is('admin', 'admin/*');
            $analyticsScript = $analytics ? ' https://www.googletagmanager.com' : '';
            $analyticsConnect = $analytics ? ' https://www.google-analytics.com https://region1.google-analytics.com' : '';
            $response->headers->set('Content-Security-Policy', $request->is('admin', 'admin/*') ? AdminBoundary::CSP
                : "default-src 'self'; script-src 'self' 'nonce-{$nonce}'{$analyticsScript}; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'{$analyticsConnect}; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
        }
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        $robots = $request->attributes->get('seo.robots');
        if ($robots !== null) {
            $response->headers->set('X-Robots-Tag', $robots);
        }
        // The public root redirects to its canonical homepage; a noindex header
        // on that redirect would conflict with the indexable destination.
        $publicDiscovery = $request->routeIs('seo.*')
            || ($request->routeIs('home.redirect') && $response->isRedirect());
        $canonicalRedirect = $request->attributes->get('homya.canonical_redirect') === true && $response->getStatusCode() === 301;
        if (! $canonicalRedirect && ($response->getStatusCode() >= 400 || ! app(Seo::class)->onPublicOrigin($request)
            || ($robots === null && ! $publicDiscovery))) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        $admin = $request->is('admin', 'admin/*');
        $commerce = $request->routeIs('cart.*', 'checkout.*', 'order.*', 'returns.*', 'support.*') || $admin;
        if ($request->routeIs('support.*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if ($admin) {
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        $response->headers->set('Referrer-Policy', $commerce ? 'no-referrer' : 'strict-origin-when-cross-origin');
        if ($commerce || $request->routeIs('analytics.*') || ($request->hasSession() && $request->session()->has('commerce.cart'))) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
