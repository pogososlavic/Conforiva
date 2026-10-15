<?php

namespace App\Http\Middleware;

use App\Services\GoogleCrawlerRanges;
use App\Services\StorefrontRequestLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StorefrontScrapeGuard
{
    public function __construct(
        private GoogleCrawlerRanges $googleRanges,
        private StorefrontRequestLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('storefront_protection.mode', 'observe');
        if ($mode === 'off' || ! $this->protects($request)) {
            return $next($request);
        }
        if (! in_array($mode, ['observe', 'enforce'], true)) {
            $this->limiter->signal('invalid_configuration', 'observe');

            return $next($request);
        }

        // Request::ip() only: no direct trust in visitor-supplied proxy headers or user-agents.
        $ip = $request->ip();
        $rangesReady = false;
        try {
            $rangesReady = $this->googleRanges->ready();
            if ($rangesReady && is_string($ip) && $this->googleRanges->contains($ip)) {
                return $next($request);
            }
        } catch (\Throwable) {
            // An unreadable or invalid crawler list must not cause public crawling failures.
            $rangesReady = false;
        }

        if ($mode === 'enforce') {
            if (config('storefront_protection.client_ip_verified') !== true) {
                $this->limiter->signal('client_ip_not_verified', 'observe');
                $mode = 'observe';
            } elseif (! $rangesReady) {
                $this->limiter->signal('crawler_ranges_not_ready', 'observe');
                $mode = 'observe';
            }
        }

        $result = $this->limiter->attempt(is_string($ip) ? $ip : '', $mode);
        if ($result === null || ! $result['limited']) {
            return $next($request);
        }

        $this->limiter->signal('request_limit_reached', $mode);
        if ($mode !== 'enforce') {
            return $next($request);
        }

        // Return directly: the global error renderer does not preserve exception headers.
        return new Response(
            $request->isMethod('HEAD') ? '' : "Trop de requêtes. Merci de réessayer dans quelques instants.\n",
            429,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
                'Retry-After' => (string) $result['retry_after'],
                'X-Robots-Tag' => 'noindex, nofollow',
            ],
        );
    }

    private function protects(Request $request): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        $locales = array_filter((array) config('homya.locales', []), 'is_string');
        if ($locales === []) {
            return false;
        }
        $locales = array_map(static fn (string $locale): string => preg_quote($locale, '~'), $locales);

        // Match the router's decoded path. Queries never get a separate rate budget.
        $path = rawurldecode(rtrim($request->getPathInfo(), '/'));

        return preg_match(
            '~\A/(?:'.implode('|', $locales).')(?:/(?:catalogue|promotions|produit/[a-z0-9][a-z0-9-]{0,220}))?\z~',
            $path,
        ) === 1;
    }
}
