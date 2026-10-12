<?php

namespace App\Services;

use App\Services\Commerce\OrderStore;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

final class AdminDashboard
{
    public const STATUSES = [
        'cancelled' => 'Annulée', 'pending_payment_details' => 'Coordonnées de paiement attendues', 'pending_bank_transfer' => 'Virement attendu', 'pending_payment' => 'Paiement attendu', 'paid' => 'Paiement confirmé', 'expired' => 'Expirée', 'bank_transfer_expired' => 'Virement expiré'];

    public function overview(): array
    {
        $db = app(OrderStore::class)->connection();
        $recent = $db->table('commerce_orders')->select(['commerce_orders.id', 'commerce_orders.created_at', 'commerce_orders.status', 'commerce_orders.total_cents', 'commerce_orders.currency'])
            ->orderByDesc('commerce_orders.created_at')->orderByDesc('commerce_orders.id')->limit(8);
        $this->addQuoteStatus($recent);

        return [
            'orders' => $db->table('commerce_orders')->count(),
            'paid' => $db->table('commerce_orders')->where('status', 'paid')->count(),
            'paid_cents' => $db->table('commerce_orders')->where('status', 'paid')->where('currency', 'EUR')->sum('total_cents'),
            'pending' => $db->table('commerce_orders')->whereIn('status', ['pending_payment_details', 'pending_bank_transfer', 'pending_payment'])->count(),
            'products' => $db->table('commerce_inventory')->count(),
            'reserved' => $db->table('commerce_inventory')->sum('reserved'),
            'recent' => $recent->get(),
        ];
    }

    public function orders(array $filters = [])
    {
        $db = app(OrderStore::class)->connection();
        $query = $db->table('commerce_orders')
            ->select(['commerce_orders.id', 'commerce_orders.created_at', 'commerce_orders.status', 'commerce_orders.total_cents', 'commerce_orders.currency', 'commerce_orders.country'])
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('commerce_orders.status', $filters['status']))
            ->when(($filters['country'] ?? '') !== '', fn ($q) => $q->where('commerce_orders.country', $filters['country']))
            ->when(($filters['from'] ?? '') !== '', fn ($q) => $q->whereDate('commerce_orders.created_at', '>=', $filters['from']))
            ->when(($filters['to'] ?? '') !== '', fn ($q) => $q->whereDate('commerce_orders.created_at', '<=', $filters['to']))
            ->orderByDesc('commerce_orders.created_at')->orderByDesc('commerce_orders.id');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function ($orders) use ($search, $pattern) {
                $orders->where('commerce_orders.id', $search)
                    ->orWhereRaw("commerce_orders.id LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereExists(function ($items) use ($pattern) {
                        $items->selectRaw('1')->from('commerce_order_items')
                            ->whereColumn('commerce_order_items.order_id', 'commerce_orders.id')
                            ->where(function ($products) use ($pattern) {
                                $products->whereRaw("commerce_order_items.reference LIKE ? ESCAPE '!'", [$pattern])
                                    ->orWhereRaw("commerce_order_items.name LIKE ? ESCAPE '!'", [$pattern])
                                    ->orWhereRaw("commerce_order_items.slug LIKE ? ESCAPE '!'", [$pattern]);
                            });
                    });
            });
        }

        if (in_array(($filters['read'] ?? ''), ['read', 'unread'], true)
            && $db->getSchemaBuilder()->hasColumn('commerce_orders', 'admin_viewed_at')) {
            $query->when($filters['read'] === 'unread', fn ($q) => $q->whereNull('commerce_orders.admin_viewed_at'))
                ->when($filters['read'] === 'read', fn ($q) => $q->whereNotNull('commerce_orders.admin_viewed_at'));
        }

        $this->addQuoteStatus($query);

        return $query->paginate(20)->withQueryString();
    }

    public function order(string $id): array
    {
        $db = app(OrderStore::class)->connection();
        $order = $db->table('commerce_orders')->where('id', $id)->first();
        abort_if($order === null, 404);
        $customer = json_decode(Crypt::decryptString($order->customer_encrypted), true, 32, JSON_THROW_ON_ERROR);
        if ($db->getSchemaBuilder()->hasColumn('commerce_orders', 'admin_viewed_at')) {
            $db->table('commerce_orders')->where('id', $id)->whereNull('admin_viewed_at')->update(['admin_viewed_at' => now()]);
        }
        if ($db->getSchemaBuilder()->hasTable('commerce_admin_events')) {
            $db->table('commerce_admin_events')->where('type', 'order_created')->where('subject', $id)
                ->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        }

        $quoteTrackingAvailable = Schema::connection('commerce')->hasTable('commerce_order_quotes');

        return ['order' => $order, 'customer' => $customer,
            'items' => $db->table('commerce_order_items')->where('order_id', $id)->orderBy('id')->get(),
            'quote_tracking_available' => $quoteTrackingAvailable,
            'automatic_quote' => $quoteTrackingAvailable ? $db->table('commerce_order_quotes')->where('order_id', $id)->first() : null];
    }

    private function addQuoteStatus($query): void
    {
        if (Schema::connection('commerce')->hasTable('commerce_order_quotes')) {
            $query->leftJoin('commerce_order_quotes', 'commerce_order_quotes.order_id', '=', 'commerce_orders.id')
                ->addSelect('commerce_order_quotes.status as quote_email_status');

            return;
        }

        $query->addSelect(\Illuminate\Support\Facades\DB::raw("'tracking_unavailable' as quote_email_status"));
    }

    public function inventory(array $filters = [])
    {
        $db = app(OrderStore::class)->connection();
        $hasAdminProducts = $db->getSchemaBuilder()->hasTable('commerce_admin_products');
        $query = $db->table('commerce_inventory')->select('commerce_inventory.*');
        if ($hasAdminProducts) {
            $query->leftJoin('commerce_admin_products', 'commerce_admin_products.slug', '=', 'commerce_inventory.slug');
        }

        $query
            ->when(($filters['q'] ?? '') !== '', function ($q) use ($filters, $hasAdminProducts) {
                // Bound input and a parameterised, literal LIKE pattern.
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['q'])).'%';
                $q->where(fn ($q) => $q->whereRaw("commerce_inventory.reference LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("commerce_inventory.slug LIKE ? ESCAPE '!'", [$pattern])
                    ->when($hasAdminProducts, fn ($q) => $q->orWhereRaw("commerce_admin_products.reference LIKE ? ESCAPE '!'", [$pattern])));
            })
            ->when(($filters['sale'] ?? '') === 'enabled', fn ($q) => $q->where('commerce_inventory.sale_enabled', true))
            ->when(($filters['sale'] ?? '') === 'disabled', fn ($q) => $q->where('commerce_inventory.sale_enabled', false))
            ->when(($filters['stock'] ?? '') === 'available', fn ($q) => $q->whereColumn('commerce_inventory.quantity', '>', 'commerce_inventory.reserved'))
            ->when(($filters['stock'] ?? '') === 'exhausted', fn ($q) => $q->whereColumn('commerce_inventory.quantity', '<=', 'commerce_inventory.reserved'))
            ->when(($filters['stock'] ?? '') === 'reserved', fn ($q) => $q->where('commerce_inventory.reserved', '>', 0))
            ->when(isset($filters['min_price']) && $filters['min_price'] !== '', fn ($q) => $q->where('commerce_inventory.approved_price_cents', '>=', (int) round((float) $filters['min_price'] * 100)))
            ->when(isset($filters['max_price']) && $filters['max_price'] !== '', fn ($q) => $q->where('commerce_inventory.approved_price_cents', '<=', (int) round((float) $filters['max_price'] * 100)));

        if ($hasAdminProducts && in_array(($filters['publication'] ?? ''), ['visible', 'suspended'], true)) {
            $query->when($filters['publication'] === 'suspended', fn ($q) => $q->where('commerce_admin_products.published', false))
                ->when($filters['publication'] === 'visible', fn ($q) => $q->where(fn ($q) => $q->whereNull('commerce_admin_products.slug')->orWhere('commerce_admin_products.published', true)));
        }

        $inventory = $query->orderBy('commerce_inventory.reference')->orderBy('commerce_inventory.slug')->paginate(10)->withQueryString();

        $catalogue = app(Catalogue::class);
        $inventory->setCollection($inventory->getCollection()->map(function ($item) use ($catalogue) {
            $product = $catalogue->productForAdmin($item->slug);
            $item->product_name = $product['name'] ?? $item->slug;
            $item->product_image = $product['image']['local_url'] ?? null;
            $item->product_published = (bool) ($product['admin_published'] ?? true);

            return $item;
        }));

        return $inventory;
    }

    public function returns(): array
    {
        $records = [];
        $unreadable = 0;
        $directory = storage_path('app/private/returns');
        foreach (is_dir($directory) ? File::files($directory) : [] as $file) {
            if ($file->getExtension() !== 'encrypted') {
                continue;
            }
            try {
                $record = app(ReturnRequests::class)->find($file->getBasename('.encrypted'));
                $records[] = ['id' => $record['id'], 'submitted_at' => $record['submitted_at']];
            } catch (\Throwable) {
                $unreadable++;
            }
        }
        usort($records, fn ($a, $b) => strcmp($b['submitted_at'], $a['submitted_at']));

        return ['records' => array_slice($records, 0, 100), 'count' => count($records), 'unreadable' => $unreadable];
    }
}
