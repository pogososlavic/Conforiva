@props(['compact' => false])
@if(app(\App\Services\AnalyticsConsent::class)->enabled() && (app()->environment('testing') || app(\App\Services\Seo::class)->onConfiguredPublicOrigin(request())))
<section class="{{ $compact ? 'footer-consent' : 'shell analytics-preferences' }}" data-analytics-preferences data-measurement-id="{{ config('analytics.measurement_id') }}" data-gtm-container-id="{{ preg_match('/\AGTM-[A-Z0-9]+\z/', (string) config('analytics.gtm_container_id')) ? config('analytics.gtm_container_id') : '' }}" data-gtm-manages-ga4="{{ config('analytics.gtm_manages_ga4') ? 'true' : 'false' }}" aria-label="{{ __('analytics.title') }}">
    <button type="button" class="{{ $compact ? 'footer-legal-button' : 'button-secondary' }}" data-analytics-open>{{ $compact ? __('footer.cookies') : __('analytics.preferences') }}</button>
    <div data-analytics-panel hidden>
        <h2>{{ __('analytics.title') }}</h2>
        <p>{{ __('analytics.description') }}</p>
        <p><a href="{{ route('information', ['locale' => app()->getLocale(), 'page' => 'confidentialite']) }}">{{ __('analytics.details') }}</a> · <a href="https://policies.google.com/privacy" rel="noopener noreferrer" target="_blank">Google</a></p>
        <div class="analytics-actions"><button type="button" class="button-secondary" data-analytics-choice="true">{{ __('analytics.accept') }}</button><button type="button" class="button-secondary" data-analytics-choice="false">{{ __('analytics.reject') }}</button></div>
        <p role="status" data-analytics-status data-success="{{ __('analytics.saved') }}" data-error="{{ __('analytics.error') }}"></p>
    </div>
</section>
@elseif($compact)
<a href="{{ route('information', ['locale' => app()->getLocale(), 'page' => 'cookies']) }}">{{ __('footer.cookies') }}</a>
@endif
