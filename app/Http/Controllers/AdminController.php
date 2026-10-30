<?php

namespace App\Http\Controllers;

use App\Services\Admin\AdminFormRecovery;
use App\Services\AdminAccess;
use App\Services\AdminAuthenticationAudit;
use App\Services\AdminDashboard;
use App\Services\Commerce\AutomaticOrderQuote;
use App\Services\Commerce\CheckoutReadiness;
use App\Services\Commerce\OrderStore;
use App\Services\ReturnRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class AdminController extends Controller
{
    public function callAction($method, $parameters)
    {
        try {
            return $this->{$method}(...array_values($parameters));
        } catch (ValidationException $error) {
            return app(AdminFormRecovery::class)->validation(request(), $error);
        } catch (\Throwable $error) {
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503;

            return response()->view('admin.unavailable', ['status' => $status], $status);
        }
    }

    public function login(AdminAccess $access)
    {
        return view('admin.login', ['configured' => $access->configured()]);
    }

    public function authenticate(Request $request, AdminAccess $access)
    {
        try {
            $data = $request->validate(['username' => ['required', 'string', 'max:80'], 'password' => ['required', 'string', 'max:256']]);
        } catch (ValidationException $error) {
            if ($request->expectsJson()) {
                throw $error;
            }
            $errors = $error->errors();
            $username = $request->input('username');
            $oldInput = is_string($username) && ! isset($errors['username']) ? ['username' => $username] : [];

            return redirect()->route('admin.login')->withErrors($errors)->withInput($oldInput);
        }
        if (! $access->login($request, $data['username'], $data['password'])) {
            app(AdminAuthenticationAudit::class)->record($request, 'failure', $data['username']);

            return redirect()->route('admin.login')->withErrors(['username' => 'Identifiant ou mot de passe incorrect.'])->withInput(['username' => $data['username']]);
        }

        app(AdminAuthenticationAudit::class)->record($request, 'success', $data['username']);

        return redirect()->route('admin.dashboard');
    }

    public function account(Request $request)
    {
        return view('admin.account', ['username' => $request->session()->get('admin.auth.username'), 'role' => $request->session()->get('admin.auth.role')]);
    }

    public function changePassword(Request $request, AdminAccess $access)
    {
        try {
            $data = $request->validate(['current_password' => ['required', 'string', 'max:256'], 'password' => ['required', 'string', 'min:16', 'max:72', 'confirmed']]);
        } catch (ValidationException $error) {
            return app(AdminFormRecovery::class)->validation($request, $error);
        }
        $account = $access->authenticate((string) $request->session()->get('admin.auth.username'), $data['current_password']);
        if (! $account || $account['id'] !== $request->session()->get('admin.auth.id')) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe actuel incorrect.']);
        }
        $access->changePassword($request, $data['password']);

        return to_route('admin.login')->with('admin_message', 'Mot de passe modifié. Reconnectez-vous avec votre nouveau mot de passe.');
    }

    public function managers(Request $request, AdminAccess $access)
    {
        abort_unless($access->isOwner($request), 403);
        abort_unless(config('dashboard.enabled'), 503);

        return view('admin.managers', ['managers' => $access->accounts()]);
    }

    public function createManager(Request $request, AdminAccess $access)
    {
        abort_unless($access->isOwner($request), 403);
        abort_unless(config('dashboard.enabled'), 503);
        try {
            $data = $request->validate(['username' => ['required', 'string', 'regex:/\A[a-zA-Z0-9._-]{3,80}\z/'], 'current_password' => ['required', 'string', 'max:256'], 'password' => ['required', 'string', 'min:16', 'max:72', 'confirmed']]);
            $account = $access->authenticate((string) $request->session()->get('admin.auth.username'), $data['current_password']);
            if (! $account || $account['id'] !== $request->session()->get('admin.auth.id')
                || $account['role'] !== 'owner' || $account['role'] !== $request->session()->get('admin.auth.role')) {
                throw ValidationException::withMessages(['current_password' => 'Mot de passe actuel incorrect.']);
            }
            $access->createManager($data['username'], $data['password']);
        } catch (ValidationException $error) {
            $username = $request->input('username');
            $oldInput = is_string($username) && ! isset($error->errors()['username'])
                && preg_match('/\A[a-zA-Z0-9._-]{3,80}\z/', $username) ? ['username' => $username] : [];

            return app(AdminFormRecovery::class)->validation($request, $error, $oldInput);
        }

        return to_route('admin.managers')->with('admin_message', 'Compte gestionnaire créé. Transmettez son mot de passe par un canal privé.');
    }

    public function logout(Request $request)
    {
        app(AdminAuthenticationAudit::class)->record($request, 'logout', (string) $request->session()->get('admin.auth.username'));
        $request->session()->forget('admin.auth');
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function dashboard(AdminDashboard $dashboard, CheckoutReadiness $readiness)
    {
        try {
            $metrics = $dashboard->overview();
        } catch (\Throwable) {
            $metrics = null;
        }

        return view('admin.dashboard', ['metrics' => $metrics, 'returns' => $dashboard->returns(), 'readiness' => $readiness]);
    }

    public function orders(Request $request, AdminDashboard $dashboard)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(array_keys(AdminDashboard::STATUSES))],
            'country' => ['nullable', Rule::in(config('commerce.countries', []))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'read' => ['nullable', Rule::in(['read', 'unread'])],
        ]);

        $db = app(OrderStore::class)->connection();

        return view('admin.orders', [
            'orders' => $dashboard->orders($data),
            'read_filter_available' => $db->getSchemaBuilder()->hasColumn('commerce_orders', 'admin_viewed_at'),
        ]);
    }

    public function order(string $id, AdminDashboard $dashboard)
    {
        return view('admin.order', $dashboard->order($id));
    }

    public function retryOrderQuote(string $id, OrderStore $orders, AutomaticOrderQuote $quotes)
    {
        $db = $orders->connection();
        abort_unless($db->getSchemaBuilder()->hasTable('commerce_order_quotes'), 503);
        $status = $db->table('commerce_order_quotes')->where('order_id', $id)->value('status');
        abort_unless($status === 'blocked', 409);
        $order = $db->table('commerce_orders')->where('id', $id)->first();
        abort_unless($order, 404);
        $quotes->sendFor($order);

        return to_route('admin.order', ['id' => $id])->with('admin_message', 'Le devis a été repris si les contrôles et la configuration sont prêts. Vérifiez son état sur cette page.');
    }

    public function inventory(Request $request, AdminDashboard $dashboard)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'sale' => ['nullable', Rule::in(['enabled', 'disabled'])],
            'stock' => ['nullable', Rule::in(['available', 'exhausted', 'reserved'])],
            'publication' => ['nullable', Rule::in(['visible', 'suspended'])],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'regex:/^\\d{1,6}(?:\\.\\d{1,2})?$/'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'regex:/^\\d{1,6}(?:\\.\\d{1,2})?$/', 'gte:min_price'],
        ]);
        $db = app(OrderStore::class)->connection();

        return view('admin.inventory', [
            'inventory' => $dashboard->inventory($data),
            'publication_filter_available' => $db->getSchemaBuilder()->hasTable('commerce_admin_products'),
        ]);
    }

    public function returns(AdminDashboard $dashboard)
    {
        return view('admin.returns', ['returns' => $dashboard->returns()]);
    }

    public function returnRequest(string $id, ReturnRequests $requests)
    {
        return view('admin.return', ['record' => $requests->find($id)]);
    }
}
