@props(['locale', 'full' => false])
<section class="take-back-notice" @if($full) id="reprise" @endif>
    <h2>{{ __('takeback.title') }}</h2><p>{{ __('takeback.policy') }}</p>
    @if($full)
        <p>{{ __('takeback.preparation') }}</p><p>{{ __('takeback.other_products') }}</p>
        <p><a href="{{ route('information', ['locale' => $locale, 'page' => 'contact']) }}">{{ __('takeback.contact') }}</a></p>
    @else
        <p><a href="{{ route('information', ['locale' => $locale, 'page' => 'livraison']) }}#reprise">{{ __('takeback.details') }}</a></p>
    @endif
</section>
