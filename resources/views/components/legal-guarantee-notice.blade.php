<section class="legal-guarantee-notice shell" aria-label="{{ __('compliance.guarantee_title') }}">
    <div><h2>{{ __('compliance.guarantee_title') }}</h2><p>{{ __('compliance.guarantee_help') }}</p></div>
    <a href="{{ asset('legal/guarantees/eu-legal-guarantee-'.app()->getLocale().'.svg') }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('compliance.guarantee_open') }}">
        <img src="{{ asset('legal/guarantees/eu-legal-guarantee-'.app()->getLocale().'.svg') }}" alt="{{ __('compliance.guarantee_alt') }}" loading="lazy">
    </a>
</section>
