<x-admin-layout :title="$product['slug'] ? 'Modifier le produit' : 'Ajouter un produit'">
<a href="{{ route('admin.inventory') }}">← Produits et stocks</a>
<form method="post" enctype="multipart/form-data" class="admin-card admin-form" action="{{ $product['slug'] ? route('admin.product.save', ['slug' => $product['slug']]) : route('admin.product.create') }}">@csrf
<input type="hidden" name="revision" value="{{ $product['revision'] }}">
<p>Prix TTC final, toutes variantes confondues. Le stock total inclut les unités déjà réservées. Les commandes enregistrées conservent leurs prix et références.</p>
<div class="admin-form-grid">
<label>Référence<input name="reference" required maxlength="255" value="{{ $product['reference'] }}" @readonly($product['slug'])></label>
<label>Prix TTC (€)<input name="price" inputmode="decimal" required value="{{ $product['price'] }}"></label>
<label>Stock total<input name="quantity" type="number" min="{{ $product['reserved'] ?? 0 }}" max="10000000" required value="{{ $product['quantity'] }}"></label>
<label>État réel du produit<select name="condition"><option value="">À préciser avant publication</option>@foreach(['neuf' => 'Neuf', 'occasion' => 'Occasion', 'reconditionne' => 'Reconditionné'] as $key => $label)<option value="{{ $key }}" @selected(($product['condition'] ?? null) === $key)>{{ $label }}</option>@endforeach</select></label>
<label>Marque<input name="brand" maxlength="120" required value="{{ $product['brand'] }}"></label>
<label>Catégorie principale<input name="category" maxlength="120" required value="{{ $product['category'] }}"></label>
<label>Univers<select name="universe">@foreach(['firewood' => 'Bois et combustibles','heating' => 'Chauffage','appliances' => 'Électroménager','furniture' => 'Mobilier','televisions' => 'Téléviseurs','other' => 'Autres'] as $key => $label)<option value="{{ $key }}" @selected(($product['universe'] ?? 'other') === $key)>{{ $label }}</option>@endforeach</select></label>
</div>
<p class="admin-muted">{{ $product['reserved'] ?? 0 }} unité(s) réservée(s) en interne. Ces quantités ne sont pas affichées dans la boutique.</p>
<fieldset><legend>Identifiants fabricant pour Google Merchant</legend>
<p class="admin-muted">Renseignez uniquement les identifiants confirmés par le fabricant ou votre fournisseur. La référence interne HOMYA ne permet pas de déduire un MPN. Ces informations alimentent la revue privée du catalogue ; la validation complète pour Google Merchant reste une étape distincte.</p>
@if($product['has_variants'] ?? false)<p class="admin-muted">Ce produit possède des variantes. Chaque variante doit disposer de ses propres identifiants et d’une vérification distincte ; les identifiants saisis ici ne sont pas repris automatiquement pour les variantes.</p>@endif
<div class="admin-form-grid">
<label>GTIN (EAN, UPC, …)<input name="gtin" inputmode="numeric" maxlength="14" pattern="[0-9]{8}|[0-9]{12}|[0-9]{13}|[0-9]{14}" value="{{ $product['manufacturer']['gtin'] ?? '' }}"><span class="admin-muted">8, 12, 13 ou 14 chiffres. Conservez les zéros initiaux.</span></label>
<label>MPN du fabricant<input name="mpn" maxlength="70" value="{{ $product['manufacturer']['mpn'] ?? '' }}"></label>
<label>Source fabricant ou fournisseur<input name="manufacturer_source_url" type="url" maxlength="1000" placeholder="https://…" value="{{ $product['manufacturer']['source_url'] ?? '' }}"><span class="admin-muted">Lien HTTPS d’une fiche qui confirme les identifiants, sans accès de connexion dans l’URL.</span></label>
</div>
<input type="hidden" name="manufacturer_verified" value="0">
<label class="admin-check"><input type="checkbox" name="manufacturer_verified" value="1" @checked($product['manufacturer']['verified'] ?? false)> J’ai vérifié les identifiants pour ce produit exact à partir de la source indiquée.</label>
</fieldset>
@foreach(['fr' => 'Français','en' => 'Anglais','de' => 'Allemand'] as $locale => $label)
@if($locale !== 'fr')<details class="admin-disclosure"><summary>{{ $label }} · traduction optionnelle</summary>@endif
<fieldset><legend>{{ $label }}</legend><label>Nom du produit<input name="languages[{{ $locale }}][name]" maxlength="255" value="{{ $product['languages'][$locale]['name'] ?? '' }}" @required($locale === 'fr')></label><label>Catégorie traduite<input name="languages[{{ $locale }}][category]" maxlength="120" value="{{ $product['languages'][$locale]['category'] ?? ($locale === 'fr' ? $product['category'] : '') }}" @required($locale === 'fr')></label><label>Description<textarea name="languages[{{ $locale }}][description]" rows="5" maxlength="12000">{{ $product['languages'][$locale]['description'] ?? '' }}</textarea></label></fieldset>
@if($locale !== 'fr')</details>@endif
@endforeach
<p class="admin-muted">Pour un nouveau produit, seules les langues renseignées seront publiées. Texte simple, sans code HTML.</p>
@if($product['images'] ?? [])<div class="admin-gallery">@foreach($product['images'] as $index => $photo)<div><img class="admin-product-preview" src="{{ $photo['local_url'] }}" alt="Photo {{ $index + 1 }}"><label class="admin-check"><input type="checkbox" name="remove_images[]" value="{{ $index }}"> Retirer la photo {{ $index + 1 }} de la galerie</label></div>@endforeach</div>@endif
<label>Ajouter ou remplacer la photo principale<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><p class="admin-muted">JPEG, PNG ou WebP · 5 Mo maximum · 500 × 500 pixels minimum. La photo est convertie en WebP.</p>
<label>Ajouter des photos complémentaires<input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"></label><p class="admin-muted">Jusqu’à 5 nouvelles photos par enregistrement, 20 dans la galerie. Les photos retirées restent conservées dans les fichiers d’origine.</p>
<label class="admin-check"><input type="checkbox" name="published" value="1" @checked($product['published'])> Autoriser la vente et afficher ce produit</label>
<div class="admin-actions"><button class="admin-primary" type="submit">Enregistrer le produit</button><a href="{{ route('admin.inventory') }}">Retour à la liste</a></div>
</form></x-admin-layout>
