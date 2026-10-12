@props(['title', 'guest' => false])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>{{ $title }} · HOMYA Administration</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
</head>
<body class="admin-page">
<a class="skip-link" href="#main">Aller au contenu</a>
<header class="admin-header"><a href="{{ route('admin.dashboard') }}" aria-label="HOMYA Administration"><x-brand-logo /></a><span class="admin-label">Administration · Local</span><a href="{{ route('home', ['locale' => 'fr']) }}">Voir la boutique ↗</a></header>
<div class="admin-shell {{ $guest ? 'admin-shell--guest' : '' }}">
@unless($guest)
<aside class="admin-sidebar"><nav aria-label="Administration">
@foreach(['dashboard' => 'Vue d’ensemble', 'orders' => 'Commandes', 'inventory' => 'Produits et stocks', 'returns' => 'Demandes de retour'] as $key => $label)
@php($active = request()->routeIs('admin.'.$key) || ($key === 'orders' && request()->routeIs('admin.order')) || ($key === 'returns' && request()->routeIs('admin.return')))
<a href="{{ route('admin.'.$key) }}" @if($active) aria-current="page" @endif>{{ $label }} @if($key === 'orders')<span class="admin-count-badge" data-admin-count="orders" aria-label="Commandes non consultées"></span>@endif</a>
@endforeach
@if(config('dashboard.enabled'))
@foreach(['documents.index' => 'Documents commerciaux', 'deliveries' => 'Livraisons','inbox' => 'Messages clients','activity' => 'Activité et notifications','bank.settings' => 'RIB HOMYA','settings' => 'Vendeur et paiements','integrations' => 'Google et Meta'] as $key => $label)<a href="{{ route('admin.'.$key) }}" @if(request()->routeIs('admin.'.$key) || ($key === 'documents.index' && request()->routeIs('admin.documents.*')) || ($key === 'inbox' && request()->routeIs('admin.thread'))) aria-current="page" @endif>{{ $label }} @if(in_array($key, ['inbox','activity']))<span data-admin-count="{{ $key === 'inbox' ? 'messages' : 'notifications' }}" aria-label="À lire"></span>@endif</a>@endforeach
@endif
</nav>
<p class="admin-notification-status" data-admin-notifications="{{ route('admin.notifications') }}" data-admin-orders-url="{{ route('admin.orders') }}" role="status" aria-live="polite"></p>
<button class="admin-secondary admin-sound-toggle" type="button" data-admin-sound-toggle aria-pressed="false">Activer le son des commandes</button>
<p class="admin-sound-help">Activez le son depuis ce bouton et laissez le tableau de bord ouvert. Le navigateur peut aussi afficher une notification système si vous l’autorisez.</p>
<p>Les quantités de stock sont visibles uniquement dans cet espace privé.</p>
<form method="post" action="{{ route('admin.logout') }}">@csrf<button class="admin-secondary" type="submit">Se déconnecter</button></form></aside>
@endunless
<main id="main" class="admin-main"><p class="admin-eyebrow">HOMYA / Espace administrateur</p><h1>{{ $title }}</h1>
<div class="admin-new-order-alert" data-admin-new-order-alert hidden role="alert"><span><i class="fa-solid fa-bell" aria-hidden="true"></i> Nouvelle commande reçue. Le badge du menu Commandes indique le nombre à consulter.</span><a href="{{ route('admin.orders') }}">Voir les commandes →</a></div>
@if($errors->any())<div class="admin-notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if(session('admin_message'))<div class="admin-notice admin-notice--success" role="status">{{ session('admin_message') }}</div>@endif
{{ $slot }}
</main></div>
</body></html>
