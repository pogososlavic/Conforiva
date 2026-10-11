<?php

namespace App\Http\Middleware;

use App\Services\ProductionSecurity;
use App\Services\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProductionGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $security = app(ProductionSecurity::class);
        if (! app()->environment('production')) {
            if ($security->localRequest($request)) {
                return $next($request);
            }
            // A development .env must never expose the application to a remote/public origin.
            config(['app.debug' => false]);

            return $this->unavailable($request->is('admin', 'admin/*') ? 404 : 503);
        }

        $failures = $security->failures();
        // Fail closed and suppress detailed error pages even with a misplaced local .env.
        config(['app.debug' => false]);
        if ($request->is('admin', 'admin/*')) {
            // Remote access is opt-in, only for the configured public HTTPS host, and
            // requires valid credentials plus every production security check.
            if (config('admin.remote_enabled') !== true
                || $failures !== []
                || ! app(AdminAccess::class)->configured()
                || $request->getHost() !== ProductionSecurity::HOST
                || ! $request->isSecure()
                || $request->getPort() !== 443) {
                return $this->unavailable(404);
            }

            return $next($request);
        }
        if ($failures !== []) {
            return $this->unavailable(503);
        }
        if ($request->getHost() !== ProductionSecurity::HOST || ($request->isSecure() && $request->getPort() !== 443)) {
            return $this->unavailable(400);
        }
        if (! $request->isSecure()) {
            return $this->unavailable(426);
        }

        return $next($request);
    }

    private function unavailable(int $status): Response
    {
        return response('Service temporairement indisponible.', $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
