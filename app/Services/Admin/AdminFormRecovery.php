<?php

namespace App\Services\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

/** Fixed local destinations; neither Referer nor session history selects a route. */
final class AdminFormRecovery
{
    public function redirect(Request $request): RedirectResponse
    {
        $name = $request->route()?->getName();
        $destination = match ($name) {
            'admin.login', 'admin.authenticate' => 'admin.login',
            'admin.password.change' => 'admin.account',
            'admin.managers.create' => 'admin.managers',
            'admin.orders' => 'admin.orders',
            'admin.inventory', 'admin.product.delete', 'admin.product.publication' => 'admin.inventory',
            'admin.product.create' => 'admin.product.new',
            'admin.product.save' => 'admin.product.edit',
            'admin.reply', 'admin.thread.read' => 'admin.thread',
            'admin.notifications.read' => 'admin.activity',
            'admin.deliveries' => 'admin.deliveries',
            'admin.delivery', 'admin.cancel', 'admin.receipt' => 'admin.order',
            'admin.settings.save' => 'admin.settings',
            'admin.bank.settings.save', 'admin.bank.settings.delete' => 'admin.bank.settings',
            'admin.integrations.save', 'admin.integrations.sync' => 'admin.integrations',
            'admin.documents.index', 'admin.documents.create' => 'admin.documents.index',
            'admin.documents.save', 'admin.documents.issue', 'admin.documents.duplicate', 'admin.documents.pdf' => 'admin.documents.edit',
            default => 'admin.dashboard',
        };
        $parameters = [];
        if (in_array($destination, ['admin.thread', 'admin.order', 'admin.documents.edit'], true)) {
            $id = $request->route('id');
            if (! is_string($id) || ! Str::isUuid($id)) {
                return to_route('admin.dashboard');
            }
            $parameters['id'] = $id;
        }
        if ($destination === 'admin.product.edit') {
            $slug = $request->route('slug');
            if (! is_string($slug) || ! preg_match('/\A[a-z0-9][a-z0-9-]{0,220}\z/', $slug)) {
                return to_route('admin.dashboard');
            }
            $parameters['slug'] = $slug;
        }

        return to_route($destination, $parameters);
    }

    public function validation(Request $request, ValidationException $error, array $oldInput = []): RedirectResponse
    {
        if ($request->expectsJson()) {
            throw $error;
        }

        $response = $this->redirect($request)->withErrors($error->errors());
        if ($oldInput === []) {
            $request->session()->forget('_old_input');

            return $response;
        }

        return $response->withInput($oldInput);
    }

    public function errors(ValidationException $error): ViewErrorBag
    {
        return (new ViewErrorBag)->put('default', $error->validator->errors());
    }
}
