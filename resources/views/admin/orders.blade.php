<x-admin-layout title="Commandes">
    <p class="admin-intro">Recherchez les commandes par numéro, référence ou nom de produit, puis combinez les filtres.</p>
    <section class="admin-card">
        <form class="admin-order-filters" method="get" action="{{ route('admin.orders') }}">
            <label class="admin-order-filters__search">Commande ou produit
                <input type="search" name="q" value="{{ request('q') }}" maxlength="150" placeholder="N° de commande, référence, produit">
            </label>
            <label>Statut
                <select name="status"><option value="">Tous les statuts</option>@foreach(\App\Services\AdminDashboard::STATUSES as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
            </label>
            <label>Pays
                <select name="country"><option value="">Tous les pays</option><option value="FR" @selected(request('country') === 'FR')>France</option><option value="BE" @selected(request('country') === 'BE')>Belgique</option><option value="DE" @selected(request('country') === 'DE')>Allemagne</option></select>
            </label>
            <label>Date de début
                <input type="date" name="from" value="{{ request('from') }}">
            </label>
            <label>Date de fin
                <input type="date" name="to" value="{{ request('to') }}">
            </label>
            @if($read_filter_available)
                <label>Consultation
                    <select name="read"><option value="">Toutes</option><option value="unread" @selected(request('read') === 'unread')>Non consultées</option><option value="read" @selected(request('read') === 'read')>Déjà consultées</option></select>
                </label>
            @endif
            <div class="admin-order-filters__actions"><button class="admin-primary" type="submit">Rechercher</button><a class="admin-secondary" href="{{ route('admin.orders') }}">Effacer les filtres</a></div>
        </form>
        <p class="admin-muted">{{ number_format($orders->total(), 0, ',', ' ') }} commande(s) trouvée(s). Les filtres restent appliqués lors du changement de page.</p>
        <x-admin-orders :orders="$orders" />
        <x-admin-pagination :items="$orders" />
    </section>
</x-admin-layout>
