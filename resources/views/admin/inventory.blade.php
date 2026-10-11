<x-admin-layout title="Produits et stocks"><p class="admin-intro">Inventaire interne. Les variantes partagent le stock de leur produit.</p>
@if(config('dashboard.enabled'))<p><a class="admin-primary" href="{{ route('admin.product.new') }}">Ajouter un produit</a></p>@endif
<section class="admin-card"><form class="admin-filter" method="get" action="{{ route('admin.inventory') }}"><label for="q">Référence ou produit</label><input id="q" name="q" type="search" maxlength="120" value="{{ request('q') }}" placeholder="Rechercher une référence"><button class="admin-primary" type="submit">Rechercher</button></form>
<div class="admin-table-scroll" tabindex="0" role="region" aria-label="Inventaire interne"><table class="admin-table"><thead><tr><th scope="col">Photo</th><th scope="col">Produit / Référence</th><th scope="col">Prix TTC</th><th scope="col">Total</th><th scope="col">Réservé</th><th scope="col">Disponible</th><th scope="col">Vente</th>@if(config('dashboard.enabled'))<th scope="col">Actions</th>@endif</tr></thead><tbody>
@forelse($inventory as $item)
<tr>
<td>@if($item->product_image)<img class="admin-inventory-thumb" src="{{ $item->product_image }}" width="64" height="64" alt="" loading="lazy">@else<span class="admin-inventory-thumb admin-inventory-thumb--empty" aria-label="Aucune photo">—</span>@endif</td>
<th scope="row"><strong>{{ $item->product_name }}</strong><small>Réf. {{ $item->reference }}</small><small>
@if($item->product_published)
<a href="{{ route('product', ['locale' => 'fr', 'slug' => $item->slug]) }}">Voir le produit ↗</a>
@else
Produit masqué du catalogue
@endif
</small></th>
<td>{{ $item->approved_price_cents === null ? 'Non validé' : number_format($item->approved_price_cents / 100, 2, ',', ' ').' €' }}</td><td>{{ $item->quantity }}</td><td>{{ $item->reserved }}</td><td>{{ max(0, $item->quantity - $item->reserved) }}</td><td>{{ $item->sale_enabled ? 'Autorisée' : 'Suspendue' }}</td>
@if(config('dashboard.enabled'))<td><div class="admin-product-actions">
<a class="admin-secondary admin-icon-button" href="{{ route('admin.product.edit', ['slug' => $item->slug]) }}" aria-label="Modifier le produit" title="Modifier"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg></a>
<form method="post" action="{{ route('admin.product.publication', ['slug' => $item->slug]) }}">@csrf<button class="admin-secondary admin-icon-button" type="submit" aria-label="{{ $item->product_published ? 'Suspendre le produit' : 'Réafficher le produit' }}" title="{{ $item->product_published ? 'Suspendre' : 'Réafficher' }}"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24">@if($item->product_published)<path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A11 11 0 0 1 12 5c6.4 0 10 7 10 7a16 16 0 0 1-3.1 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 3.2-.5"/>@else<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>@endif</svg></button></form>
<details class="admin-delete-details"><summary class="admin-danger admin-icon-button" aria-label="Supprimer le produit" title="Supprimer"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg></summary><form method="post" action="{{ route('admin.product.delete', ['slug' => $item->slug]) }}">@csrf @method('DELETE')<label><input type="checkbox" name="confirm" value="1" required> Confirmer</label><button class="admin-danger" type="submit">Confirmer la suppression</button></form></details>
</div></td>@endif
</tr>
@empty<tr><td colspan="{{ config('dashboard.enabled') ? 8 : 7 }}" class="admin-empty">Aucun produit pour cette recherche.</td></tr>@endforelse
</tbody></table></div><x-admin-pagination :items="$inventory" /></section></x-admin-layout>
