<x-admin-layout title="Mon compte">
<section class="admin-card"><h2>Accès personnel</h2><p>Identifiant : <strong>{{ $username }}</strong></p><p>Rôle : <strong>{{ $role === 'owner' ? 'Administrateur principal' : 'Gestionnaire' }}</strong></p></section>
@if($role === 'manager')
<form class="admin-card admin-form" method="post" action="{{ route('admin.password.change') }}">@csrf<h2>Modifier mon mot de passe</h2><p>Choisissez au moins 16 caractères. Après modification, votre session sera fermée et vous devrez vous reconnecter.</p><label>Mot de passe actuel<input type="password" name="current_password" autocomplete="current-password" required maxlength="256"></label><label>Nouveau mot de passe<input type="password" name="password" autocomplete="new-password" required minlength="16" maxlength="72"></label><label>Confirmer le nouveau mot de passe<input type="password" name="password_confirmation" autocomplete="new-password" required minlength="16" maxlength="72"></label><button class="admin-primary" type="submit">Modifier le mot de passe</button></form>
@else
<section class="admin-card"><h2>Mot de passe principal</h2><p>Le mot de passe administrateur principal reste géré dans la configuration privée du projet. Cette page ne le modifie pas.</p></section>
@endif
</x-admin-layout>
