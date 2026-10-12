@props(['orders'])
<div class="admin-table-scroll" tabindex="0" role="region" aria-label="Liste des commandes"><table class="admin-table"><thead><tr><th scope="col">Commande / Date UTC</th><th scope="col">Statut</th><th scope="col">Devis par e-mail</th><th scope="col">Montant TTC</th><th scope="col">Détail</th></tr></thead><tbody>
@forelse($orders as $order)
    @php
        $quoteStatus = $order->quote_email_status ?? null;
        $quoteLabel = match ($quoteStatus) {
            'preparing' => 'Préparation',
            'blocked' => 'Bloqué',
            'sending' => 'Envoi en cours',
            'submitted' => 'Remis au transport email',
            'unknown' => 'Résultat à vérifier',
            'tracking_unavailable' => 'Migration de suivi absente',
            null => 'Aucune tentative enregistrée',
            default => 'État inconnu',
        };
    @endphp
    <tr><th scope="row"><span class="admin-reference">{{ $order->id }}</span><small>{{ $order->created_at }}</small></th><td><span class="admin-status {{ $order->status === 'paid' ? 'admin-status--paid' : '' }}">{{ \App\Services\AdminDashboard::STATUSES[$order->status] ?? $order->status }}</span></td><td>{{ $quoteLabel }}</td><td>{{ number_format($order->total_cents / 100, 2, ',', ' ') }} {{ $order->currency }}</td><td><a href="{{ route('admin.order', ['id' => $order->id]) }}" aria-label="Consulter la commande {{ $order->id }}">Consulter →</a></td></tr>
@empty<tr><td colspan="5" class="admin-empty">Aucune commande à afficher.</td></tr>@endforelse
</tbody></table></div>
