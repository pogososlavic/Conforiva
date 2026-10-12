<x-admin-layout title="Produits et stocks"><p class="admin-intro">Inventaire interne. Les variantes partagent le stock de leur produit.</p>
@if(config('dashboard.enabled'))<p><a class="admin-primary" href="{{ route('admin.product.new') }}">Ajouter un produit</a></p>@endif
<section class="admin-card">
<form class="admin-catalogue-filters" method="get" action="{{ route('admin.inventory') }}">
<label class="admin-catalogue-filters__search">Référence ou identifiant produit<input id="q" name="q" type="search" maxlength="120" value="{{ request('q') }}" placeholder="Référence, slug ou identifiant"></label>
<label>Vente<select name="sale"><option value="">Tous les états</option><option value="enabled" @selected(request('sale') === 'enabled')>Autorisée</option><option value="disabled" @selected(request('sale') === 'disabled')>Suspendue</option></select></label>
<label>Stock<select name="stock"><option value="">Tous les niveaux</option><option value="available" @selected(request('stock') === 'available')>Disponible</option><option value="exhausted" @selected(request('stock') === 'exhausted')>Épuisé / réservé</option><option value="reserved" @selected(request('stock') === 'reserved')>Avec réservation</option></select></label>
@if($publication_filter_available)<label>Affichage boutique<select name="publication"><option value="">Tous</option><option value="visible" @selected(request('publication') === 'visible')>Visible</option><option value="suspended" @selected(request('publication') === 'suspended')>Suspendu / masqué</option></select></label>@endif
<label>Prix TTC minimum (€)<input name="min_price" type="number" min="0" max="999999.99" step="0.01" value="{{ request('min_price') }}" inputmode="decimal"></label>
<label>Prix TTC maximum (€)<input name="max_price" type="number" min="0" max="999999.99" step="0.01" value="{{ request('max_price') }}" inputmode="decimal"></label>
<div class="admin-catalogue-filters__actions"><button class="admin-primary" type="submit">Filtrer les produits</button><a class="admin-secondary" href="{{ route('admin.inventory') }}">Effacer les filtres</a></div>
</form>
<p class="admin-muted">{{ number_format($inventory->total(), 0, ',', ' ') }} produit(s) correspondant aux critères. 10 produits sont affichés par page.</p>
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
@if(config('dashboard.enabled'))<td class="admin-actions-cell"><div class="admin-product-actions">
<a class="admin-secondary admin-icon-button admin-icon-button--edit" href="{{ route('admin.product.edit', ['slug' => $item->slug]) }}" aria-label="Modifier le produit" title="Modifier"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></a>
<form method="post" action="{{ route('admin.product.publication', ['slug' => $item->slug]) }}">@csrf<button class="admin-secondary admin-icon-button admin-icon-button--visibility" type="submit" aria-label="{{ $item->product_published ? 'Suspendre le produit' : 'Réafficher le produit' }}" title="{{ $item->product_published ? 'Suspendre' : 'Réafficher' }}"><i class="fa-solid {{ $item->product_published ? 'fa-eye-slash' : 'fa-eye' }}" aria-hidden="true"></i></button></form>
<details class="admin-delete-details"><summary class="admin-danger admin-icon-button admin-icon-button--delete" aria-label="Supprimer le produit" title="Supprimer"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></summary><form method="post" action="{{ route('admin.product.delete', ['slug' => $item->slug]) }}">@csrf @method('DELETE')<label><input type="checkbox" name="confirm" value="1" required> Confirmer</label><button class="admin-danger" type="submit">Confirmer la suppression</button></form></details>
</div></td>@endif
</tr>
@empty<tr><td colspan="{{ config('dashboard.enabled') ? 8 : 7 }}" class="admin-empty">Aucun produit pour cette recherche.</td></tr>@endforelse
</tbody></table></div><x-admin-pagination :items="$inventory" /></section></x-admin-layout>
