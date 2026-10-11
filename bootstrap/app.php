<?php

use App\Http\Middleware\ProductionGuard;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StorefrontActivity;
use App\Services\ProductionSecurity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SecurityHeaders::class);
        // Evaluate HTTPS after Laravel's proxy handling, before any web session or controller.
        $middleware->append(ProductionGuard::class);
        $middleware->append(StorefrontActivity::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $referenceFor = static function (Request $request): string {
            $reference = $request->attributes->get('homya.error_reference');
            if (is_string($reference) && preg_match('/\AHMY-[A-F0-9]{16}\z/', $reference)) {
                return $reference;
            }

            $reference = 'HMY-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 16));
            $request->attributes->set('homya.error_reference', $reference);

            return $reference;
        };
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'code', 'body', 'publishable_key', 'secret', 'webhook_secret', 'client_secret', 'refresh_token', 'developer_token', 'access_token', 'card_number', 'cardholder', 'expiry', 'expiry_month', 'expiry_year', 'cvc', 'cvv', 'iban', 'bic']);
        $exceptions->report(function (Throwable $error) use ($referenceFor) {
            if (app(ProductionSecurity::class)->localRequest(request())
                || (app()->environment('local', 'testing') && app()->runningInConsole() && request()->server('REMOTE_ADDR') === null)) {
                return null;
            }
            // Exception messages, SQL bindings, trace arguments and request data can contain secrets.
            try {
                $request = request();
                Log::error('HOMYA_PRODUCTION_EXCEPTION', [
                    'reference' => $referenceFor($request),
                    'type' => get_class($error),
                    'source' => basename($error->getFile()),
                    'line' => $error->getLine(),
                ]);
            } catch (Throwable) {
                // A failed logger must never cause raw exception disclosure.
            }

            return false;
        });
        $exceptions->render(function (Throwable $error, Request $request) use ($referenceFor) {
            // Also cover framework exceptions thrown before the guard with an accidental local .env.
            $localRequest = app(ProductionSecurity::class)->localRequest($request);
            if (! $localRequest) {
                config(['app.debug' => false]);
            }
            if ($error instanceof ValidationException) {
                return null;
            }
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503;
            $reference = $referenceFor($request);
            if ($request->is('admin', 'admin/*') && ! ($error instanceof ValidationException)) {
                return response()->view('admin.unavailable', ['status' => $status, 'reference' => $reference], $status)
                    ->header('Cache-Control', 'no-store, private')
                    ->header('Referrer-Policy', 'no-referrer')
                    ->header('X-Robots-Tag', 'noindex, nofollow')
                    ->header('X-HOMYA-Reference', $reference);
            }
            if ($request->routeIs('returns.*')) {
                return response()->view('returns.unavailable', ['reference' => $reference], $status)
                    ->header('Cache-Control', 'no-store, private')
                    ->header('Referrer-Policy', 'no-referrer')
                    ->header('X-Robots-Tag', 'noindex, nofollow')
                    ->header('X-HOMYA-Reference', $reference);
            }
            if ($localRequest || $request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            return response()->view('errors.page', ['status' => $status, 'reference' => $reference], $status)
                ->header('Cache-Control', 'no-store, private')
                ->header('Referrer-Policy', 'no-referrer')
                ->header('X-Robots-Tag', 'noindex, nofollow')
                ->header('X-HOMYA-Reference', $reference);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
