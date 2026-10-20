<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class CommerceBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        if (in_array($locale, config('homya.locales'), true)) {
            app()->setLocale($locale);
        }
        try {
            $response = $next($request);
        } catch (ValidationException $error) {
            $response = $request->expectsJson() ? response()->json(['errors' => $error->errors()], 422)
                : redirect()->back()->withErrors($error->errors())->withInput($this->safeOldInput($request));
        } catch (HttpExceptionInterface $error) {
            $response = response()->view('commerce.unavailable', [], $error->getStatusCode());
            // Keep framework rate-limit metadata while replacing the error body safely.
            $response->headers->add($error->getHeaders());
        } catch (Throwable) {
            // Never debug-render SQL/payment exceptions, credentials or customer details.
            $response = response()->view('commerce.unavailable', [], 503);
        }
        // Laravel's routing pipeline can render validation exceptions before this
        // middleware catches them. Sanitize newly flashed input on that path too.
        if (! $request->isMethodSafe() && $request->hasSession()) {
            $newFlash = $request->session()->get('_flash.new', []);
            if (in_array('_old_input', $newFlash, true) && in_array('errors', $newFlash, true)) {
                $request->session()->flashInput($this->safeOldInput($request));
            }
        }
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        if ($request->routeIs('returns.*', 'order.tracking*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /** Keep useful form values without retaining opaque tokens or malformed payloads. */
    private function safeOldInput(Request $request): array
    {
        $limits = ['email' => 254, 'name' => 150, 'phone' => 40, 'phone_region' => 2,
            'address' => 200, 'postal_code' => 12, 'city' => 100, 'country' => 2,
            'quantity' => 3, 'variant' => 4, 'payment_method' => 20, 'contract' => 500,
            'method' => 32, 'logistics' => 16, 'notes' => 1000,
            'billing_name' => 150, 'billing_address' => 200, 'billing_postal_code' => 20,
            'billing_city' => 100, 'billing_country' => 2];
        $input = [];
        foreach ($limits as $key => $limit) {
            $value = $request->input($key);
            if (is_string($value)) {
                $input[$key] = mb_substr($value, 0, $limit);
            } elseif ($key === 'quantity' && is_int($value) && $value >= 0 && $value <= 999) {
                $input[$key] = $value;
            }
        }
        if (in_array($request->input('billing_different'), [1, '1', true], true)) {
            $input['billing_different'] = '1';
        }
        $takeBack = $request->input('take_back');
        if (is_array($takeBack) && count($takeBack) <= 100) {
            $input['take_back'] = [];
            foreach ($takeBack as $key => $quantity) {
                if (! is_string($key) || ! preg_match('/\A[a-z0-9][a-z0-9-]{0,220}(?:~v[1-9][0-9]{0,2})?\z/', $key)) {
                    continue;
                }
                if ((is_int($quantity) && $quantity >= 0 && $quantity <= 99)
                    || (is_string($quantity) && preg_match('/\A(?:0|[1-9][0-9]?)\z/', $quantity))) {
                    $input['take_back'][$key] = $quantity;
                }
            }
        }

        return $input;
    }
}
