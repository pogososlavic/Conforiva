@props(['locale', 'standalone' => false, 'headingId' => 'advice-heading'])
@inject('channels', 'App\Services\ContactChannels')
<section class="advice-panel" aria-labelledby="{{ $headingId }}">
    <div class="advice-panel-media"><img src="{{ asset('media/home/open-conseil-1200.webp') }}" width="1200" height="816" alt="{{ __('home.advice_alt') }}" loading="lazy" decoding="async"></div>
    <div class="advice-panel-copy">
        <p class="advice-kicker"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 14v-2a8 8 0 0 1 16 0v2M4 13H3v6h4v-6H4Zm16 0h1v6h-4v-6h3Zm0 6c0 2-3 3-6 3" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>{{ __('contact.advice_eyebrow') }}</p>
        @if($standalone)<h1 id="{{ $headingId }}">{{ __('pages.entries.conseiller.title') }}</h1>@else<h2 id="{{ $headingId }}">{{ __('contact.advice_heading') }}</h2>@endif
        <p class="advice-intro">{{ __('contact.advice_intro') }}</p>
        <div class="advice-contact">
            @if($headingId === 'choosing-heading')
                <span class="advice-contact-icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M4 11a8 8 0 0 1 16 0v5M4 11v5M4 12H3v6h4v-6H4Zm16 0h1v6h-4v-6h3Zm0 6c0 3-4 3-7 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            @endif
            <h3>{{ __('contact.label') }}</h3>
            @if($channels->phone())<p>{{ __('contact.chat_help') }}</p><x-whatsapp-chat :floating="false" /><a class="advice-contact-details" href="{{ route('information', ['locale' => $locale, 'page' => 'contact']) }}">{{ __('contact.advice_contact') }}</a>
            @else<p>{{ __('contact.advice_pending') }}</p><a class="button-primary" href="{{ route('information', ['locale' => $locale, 'page' => 'contact']) }}">{{ __('contact.advice_contact') }} <span aria-hidden="true">→</span></a>@endif
        </div>
        <div class="advice-links"><a href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('home.compare') }}</a>@unless($standalone)<a href="{{ route('information', ['locale' => $locale, 'page' => 'conseiller']) }}">{{ __('pages.entries.conseiller.title') }} <span aria-hidden="true">→</span></a>@endunless</div>
    </div>
</section>
