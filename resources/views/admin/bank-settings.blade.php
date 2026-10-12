<x-admin-layout title="RIB HOMYA">
<p>Ajoutez ou mettez à jour les coordonnées bancaires affichées sur les devis et bons de commande. Elles sont stockées chiffrées dans l’espace privé de l’application.</p>
<section class="admin-card"><h2>État du RIB</h2>
@if($documentReady)
<p class="admin-notice admin-notice--success">Un RIB valide est configuré. Il sera ajouté aux prochains devis et bons de commande, même si le paiement par virement du checkout reste fermé.</p>
<dl class="admin-data"><div><dt>Bénéficiaire</dt><dd>{{ $bank['beneficiary'] }}</dd></div><div><dt>Banque</dt><dd>{{ $bank['bank_name'] }}</dd></div><div><dt>IBAN</dt><dd>{{ trim(chunk_split($bank['iban'], 4, ' ')) }}</dd></div><div><dt>BIC / SWIFT</dt><dd>{{ $bank['bic'] }}</dd></div><div><dt>Paiement par virement au checkout</dt><dd>{{ $bank['enabled'] ? 'Activé, selon les conditions de disponibilité' : 'Désactivé' }}</dd></div></dl>
@else
<p class="admin-notice">Aucun RIB complet et valide n’est disponible pour les documents. Renseignez les quatre champs ci-dessous.</p>
@endif
<p class="admin-muted">Les commandes déjà créées conservent leurs instructions bancaires d’origine. La suppression du RIB ne modifie pas leurs données historiques.</p></section>

<form class="admin-card admin-form" method="post" action="{{ route('admin.bank.settings.save') }}">
@csrf<input type="hidden" name="version" value="{{ $version }}">
<h2>{{ $documentReady ? 'Modifier les coordonnées' : 'Ajouter les coordonnées' }}</h2>
<div class="admin-form-grid">
<label>Bénéficiaire<input name="beneficiary" maxlength="200" autocomplete="organization" required value="{{ $bank['beneficiary'] }}"></label>
<label>Nom de la banque<input name="bank_name" maxlength="200" required value="{{ $bank['bank_name'] }}"></label>
<label>IBAN<input name="iban" maxlength="42" autocomplete="off" spellcheck="false" required value="{{ $bank['iban'] }}"></label>
<label>BIC / SWIFT<input name="bic" maxlength="19" autocomplete="off" spellcheck="false" required value="{{ $bank['bic'] }}"></label>
</div>
<label class="admin-check"><input type="checkbox" name="bank_enabled" value="1" @checked($bank['enabled'])> Autoriser aussi le paiement par virement dans le checkout lorsque les autres contrôles commerciaux sont prêts</label>
<label>Mot de passe administrateur<input type="password" name="current_password" autocomplete="current-password" required maxlength="256"></label>
<button class="admin-primary" type="submit">Enregistrer le RIB</button>
</form>

@if($hasBankValues)
<form class="admin-card admin-form" method="post" action="{{ route('admin.bank.settings.delete') }}">
@csrf @method('DELETE')<input type="hidden" name="version" value="{{ $version }}">
<h2>Supprimer le RIB</h2><p>Le RIB sera retiré des nouveaux devis et bons de commande. Les documents déjà émis et les commandes existantes resteront inchangés.</p>
<label class="admin-check"><input type="checkbox" name="confirmed" value="1" required> Je confirme la suppression des coordonnées bancaires HOMYA.</label>
<label>Mot de passe administrateur<input type="password" name="current_password" autocomplete="current-password" required maxlength="256"></label>
<button class="admin-danger" type="submit">Supprimer le RIB</button>
</form>
@endif
</x-admin-layout>
