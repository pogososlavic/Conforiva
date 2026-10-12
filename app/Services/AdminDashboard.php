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

    public function orders(?string $status)
    {
        $query = app(OrderStore::class)->connection()->table('commerce_orders')
            ->select(['commerce_orders.id', 'commerce_orders.created_at', 'commerce_orders.status', 'commerce_orders.total_cents', 'commerce_orders.currency', 'commerce_orders.country'])
            ->when($status, fn ($q) => $q->where('commerce_orders.status', $status))
            ->orderByDesc('commerce_orders.created_at')->orderByDesc('commerce_orders.id');
        $this->addQuoteStatus($query);

        return $query->paginate(20)->withQueryString();
    }

    public function order(string $id): array
    {
        $db = app(OrderStore::class)->connection();
        $order = $db->table('commerce_orders')->where('id', $id)->first();
        abort_if($order === null, 404);
        $customer = json_decode(Crypt::decryptString($order->customer_encrypted), true, 32, JSON_THROW_ON_ERROR);

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

    public function inventory(string $query)
    {
        $inventory = app(OrderStore::class)->connection()->table('commerce_inventory')
            ->when($query !== '', function ($q) use ($query) {
                // Bound input and a parameterised, literal LIKE pattern.
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%';
                $q->where(fn ($q) => $q->whereRaw("reference LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("slug LIKE ? ESCAPE '!'", [$pattern]));
            })->orderBy('reference')->orderBy('slug')->paginate(10)->withQueryString();

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
