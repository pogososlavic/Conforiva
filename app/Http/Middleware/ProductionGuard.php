<?php

namespace App\Http\Middleware;

use App\Services\AdminAccess;
use App\Services\ProductionSecurity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ProductionGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $reference = 'HMY-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 16));
        $request->attributes->set('homya.error_reference', $reference);
        $security = app(ProductionSecurity::class);
        if (! app()->environment('production')) {
            if ($security->localRequest($request)) {
                return $this->continue($request, $next, $reference);
            }
            // A development .env must never expose the application to a remote/public origin.
            config(['app.debug' => false]);

            return $this->unavailable($request, $request->is('admin', 'admin/*') ? 404 : 503, $reference, 'nonproduction_environment');
        }

        // Repair only the two protected directory modes after deployments. If PHP
        // cannot safely tighten them, the normal fail-closed checks still block traffic.
        $security->repairPrivateDirectoryPermissions();
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
                return $this->unavailable($request, 404, $reference, 'admin_access_closed', $failures);
            }

            return $this->continue($request, $next, $reference);
        }
        if ($failures !== []) {
            return $this->unavailable($request, 503, $reference, 'production_security_checks_failed', $failures);
        }
        // Only canonicalize safe browsing requests from the exact public alias.
        // Keep administration, writes and unusual ports behind the existing guards.
        if ($request->getHost() === 'www.'.ProductionSecurity::HOST
            && in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && in_array($request->getPort(), [80, 443], true)) {
            $request->attributes->set('homya.canonical_redirect', true);

            return redirect()->away('https://'.ProductionSecurity::HOST.$request->getRequestUri(), 301)
                ->header('X-HOMYA-Reference', $reference);
        }
        if ($request->getHost() !== ProductionSecurity::HOST || ($request->isSecure() && $request->getPort() !== 443)) {
            return $this->unavailable($request, 400, $reference, 'canonical_host_required');
        }
        if (! $request->isSecure()) {
            return $this->unavailable($request, 426, $reference, 'https_required');
        }

        return $this->continue($request, $next, $reference);
    }

    private function continue(Request $request, Closure $next, string $reference): Response
    {
        $response = $next($request);
        $response->headers->set('X-HOMYA-Reference', $reference);

        return $response;
    }

    private function unavailable(Request $request, int $status, string $reference, string $reason, array $checks = []): Response
    {
        try {
            // Production may filter warning-level logs (for example LOG_LEVEL=error).
            Log::error('HOMYA_REQUEST_BLOCKED', [
                'reference' => $reference,
                'status' => $status,
                'reason' => $reason,
                'failed_checks' => $checks,
            ]);
        } catch (\Throwable) {
            // Never expose logging failures or implementation details to visitors.
        }

        return response()->view('errors.page', ['status' => $status, 'reference' => $reference], $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('X-HOMYA-Reference', $reference);
    }
}
