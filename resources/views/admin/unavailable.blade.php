@php
    $status = (int) ($status ?? 503);
    [$heading, $message] = match ($status) {
        401 => ['Connexion requise', 'Reconnectez-vous pour accéder à votre espace administrateur.'],
        403 => ['Accès non autorisé', 'Votre compte ne permet pas d’effectuer cette action.'],
        404 => ['Page introuvable', 'Cette page de l’administration n’est pas accessible.'],
        419 => ['Votre session a expiré', 'Reconnectez-vous, puis recommencez votre action.'],
        429 => ['Veuillez patienter', 'Trop de tentatives. Patientez avant de vous reconnecter.'],
        default => ['Administration momentanément indisponible', 'Cette page ou ses données ne sont pas accessibles pour le moment. Réessayez dans quelques instants.'],
    };
    $reconnect = in_array($status, [401, 419, 429], true);
@endphp
<x-admin-layout title="Administration indisponible" :guest="true">
<section class="admin-card">
    <h2>{{ $heading }}</h2>
    <p>{{ $message }}</p>
    @if(!empty($reference))<p>Référence de suivi : <strong>{{ $reference }}</strong>. Communiquez-la au support HOME CONFORT.</p>@endif
    <a class="admin-primary" href="{{ route($reconnect ? 'admin.login' : 'admin.dashboard') }}">{{ $reconnect ? 'Se reconnecter au dashboard' : 'Retour au dashboard' }}</a>
</section>
</x-admin-layout>
