<x-layout :catalogue-ready="$catalogueReady" :navigation-products="$navigationProducts" :description="__($catalogueReady ? 'home.description_ready' : 'home.description')">
    <main id="main" class="home-page" tabindex="-1">
        <section class="home-hero" aria-labelledby="hero-heading">
            <img class="home-hero-image" src="/media/home/open-salon-1600.webp" srcset="/media/home/open-salon-960.webp 960w, /media/home/open-salon-1600.webp 1600w" sizes="100vw" width="1600" height="1068" alt="" aria-hidden="true" fetchpriority="high" decoding="async">
            <div class="shell home-hero-layout">
                <div class="home-hero-copy">
                    <p class="home-kicker">{{ __('home.eyebrow') }}</p>
                    <h1 id="hero-heading">{{ __('home.heading') }}<br>{{ __('home.heading_accent') }}</h1>
                    <p>{{ __('home.intro') }}</p>
                    <div class="home-hero-actions">
                        <a class="home-button home-button-catalogue" href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('home.all_catalogue') }} <span aria-hidden="true">→</span></a>
                        <a class="home-button home-button-outline" href="{{ route('catalogue', ['locale' => $locale, 'category' => 'chauffage-poeles-a-bois']) }}">{{ __('home.see_stoves') }}</a>
                        <div class="home-hero-guide-row"><a class="home-hero-guide" href="#choosing">{{ __('home.choose_help') }} <span aria-hidden="true">↗</span></a></div>
                    </div>
                </div>
            </div>
        </section>

        <div class="shell home-highlights">
            @foreach (__('home.highlights') as $highlight)
                <div class="home-highlight"><span class="home-highlight-icon" aria-hidden="true">{{ ['◇', '€', '≡'][$loop->index] }}</span><div><strong>{{ $highlight['title'] }}</strong><p>{{ $highlight['detail'] }}</p></div></div>
            @endforeach
        </div>
        <p class="shell home-introduction">{{ __('home.store_intro') }}</p>

        <section class="home-story" aria-labelledby="story-heading">
            <div class="shell">
                <div class="home-section-heading"><h2 id="story-heading">{{ __('home.story_heading') }}</h2><p>{{ __('home.story_intro') }}</p></div>
                <div class="home-story-grid">
                    @foreach (['heating' => 'chauffage', 'appliances' => 'cuisine'] as $universe => $photo)
                        <article class="home-story-card">
                            <img src="/media/home/open-{{ $photo }}-960.webp" srcset="/media/home/open-{{ $photo }}-480.webp 480w, /media/home/open-{{ $photo }}-720.webp 720w, /media/home/open-{{ $photo }}-960.webp 960w" sizes="(max-width: 599px) calc(100vw - 32px), (max-width: 1328px) calc((100vw - 66px) / 2), 631px" width="960" height="640" loading="lazy" decoding="async" alt="{{ __('home.story.'.$universe.'.alt') }}">
                            <div><h3>{{ __('home.story.'.$universe.'.title') }}</h3><p>{{ __('home.story.'.$universe.'.detail') }}</p><a href="{{ route('catalogue', ['locale' => $locale, 'universe' => $universe]) }}">{{ __('catalogue.discover') }} <span aria-hidden="true">→</span></a></div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="universes" class="shell home-section" aria-labelledby="universes-heading" tabindex="-1">
            <div class="home-section-heading"><h2 id="universes-heading">{{ __('home.universes') }}</h2><p>{{ __('home.universes_intro') }}</p></div>
            <ul class="home-department-grid">
                @foreach ($departments as $department)
                    @php($key = $department['key'])
                    @php($departmentFilters = $key === 'cooling' ? array_replace($department['filters'], ['q' => __('home.cooling_search')]) : $department['filters'])
                    <li id="{{ $department['id'] }}" tabindex="-1">
                        <a class="home-department" href="{{ route('catalogue', ['locale' => $locale] + $departmentFilters) }}">
                            <div class="home-department-image"><x-product-image :src="$departmentImages[$key]['local_url'] ?? null" :width="$departmentImages[$key]['width'] ?? null" :height="$departmentImages[$key]['height'] ?? null" alt="" sizes="(max-width: 599px) 45vw, (max-width: 959px) 30vw, 230px" /></div>
                            <div class="home-department-copy"><p class="home-department-label">{{ __('home.departments.'.$key.'.label') }}</p><h3>{{ __('home.departments.'.$key.'.name') }}</h3><p>{{ __('home.departments.'.$key.'.description') }}</p><span>{{ __('catalogue.discover') }} <span aria-hidden="true">→</span></span></div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <ul class="home-complementary-grid">
                @foreach (['firewood'] as $slug)
                    <li id="universe-{{ $slug }}" tabindex="-1"><a href="{{ route('catalogue', ['locale' => $locale, 'universe' => $slug]) }}">@if($universeImages[$slug])@php($complementarySrcset = app(\App\Services\ResponsiveMedia::class)->srcset($universeImages[$slug]['local_url']))<img src="{{ $universeImages[$slug]['local_url'] }}" @if($complementarySrcset) srcset="{{ $complementarySrcset }}" sizes="64px" @endif width="{{ $universeImages[$slug]['width'] }}" height="{{ $universeImages[$slug]['height'] }}" alt="" loading="lazy" decoding="async">@endif<div><h3>{{ __('home.categories.'.$slug.'.name') }}</h3><p>{{ __('home.categories.'.$slug.'.description') }}</p></div><span aria-hidden="true">→</span></a></li>
                @endforeach
                @if($freezerProduct)
                    <li id="universe-freezers" tabindex="-1" data-home-freezer="{{ $freezerProduct['slug'] }}">
                        <a href="{{ route('product', ['locale' => $locale, 'slug' => $freezerProduct['slug']]) }}">
                            <x-product-image :src="$freezerProduct['image']['local_url'] ?? null" :width="$freezerProduct['image']['width'] ?? null" :height="$freezerProduct['image']['height'] ?? null" alt="" sizes="80px" />
                            <div><h3>{{ __('home.freezer.name') }}</h3><p>{{ $freezerProduct['brand'] }} {{ $freezerProduct['reference'] }}</p><p>{{ __('home.freezer.description') }}</p></div>
                            <span aria-hidden="true">→</span>
                        </a>
                    </li>
                @endif
            </ul>
        </section>

            <section id="featured" class="shell home-section" aria-labelledby="featured-heading" data-home-products="featured" tabindex="-1">
                <div class="home-section-heading"><h2 id="featured-heading">{{ __('home.featured') }}</h2><p>{{ __('home.featured_intro') }}</p></div>
                @if($catalogueReady && $featuredProducts->isNotEmpty())<div class="home-product-grid">@foreach($featuredProducts as $product)<x-product-card :product="$product" :locale="$locale" heading-level="h3" />@endforeach</div>@else<p class="home-empty-products">{{ $catalogueReady ? __('home.selection_empty') : ($catalogueUnavailable ? __('home.catalogue_unavailable') : __('home.status_detail')) }}</p>@endif
                <div class="home-section-action"><a class="home-button home-button-yellow" href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('home.all_catalogue') }} <span aria-hidden="true">→</span></a></div>
            </section>

        <section id="choosing" class="home-choosing" aria-labelledby="choosing-heading" tabindex="-1">
            <div class="shell"><x-advice-panel :locale="$locale" heading-id="choosing-heading" /></div>
        </section>

            <section id="discoveries" class="shell home-section" aria-labelledby="discovery-heading" data-home-products="discovery" tabindex="-1">
                <div class="home-section-heading"><h2 id="discovery-heading">{{ __('home.discovery') }}</h2><p>{{ __('home.discovery_intro') }}</p></div>
                @if($catalogueReady && $discoveryProducts->isNotEmpty())<div class="home-product-grid">@foreach($discoveryProducts as $product)<x-product-card :product="$product" :locale="$locale" heading-level="h3" />@endforeach</div>@else<p class="home-empty-products">{{ $catalogueReady ? __('home.selection_empty') : ($catalogueUnavailable ? __('home.catalogue_unavailable') : __('home.status_detail')) }}</p>@endif
                <div class="home-section-action"><a class="home-button home-button-yellow" href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('home.all_catalogue') }} <span aria-hidden="true">→</span></a></div>
            </section>

        <section class="shell home-browse-banner" aria-labelledby="browse-heading"><div><h2 id="browse-heading">{{ __('home.browse_heading') }}</h2><p>{{ __('home.browse_intro') }}</p></div><a class="home-button home-button-yellow" href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('home.browse_action') }} <span aria-hidden="true">→</span></a></section>
        <x-home-photo-credits />
    </main>
</x-layout>
