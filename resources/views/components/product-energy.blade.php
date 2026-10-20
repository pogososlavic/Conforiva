@props(['product', 'locale'])
@foreach(app(\App\Services\ProductEnergyLabels::class)->forProductLabels($product, $locale) as $energy)
    <div class="product-energy">
        @if(isset($energy['scope']))<span class="energy-component-context">{{ __('energy.contexts.'.$energy['scope']) }}</span>@endif
        <details class="energy-disclosure">
            <summary aria-label="{{ __('energy.open', ['class' => $energy['class'], 'scale' => $energy['scale'], 'reference' => $energy['reference'].(isset($energy['scope']) ? ' — '.__('energy.contexts.'.$energy['scope']) : '')]) }}">
                <svg class="energy-arrow" viewBox="0 0 90 48" aria-hidden="true" focusable="false">
                    <path d="M1 1H69L89 24L69 47H1Z" fill="{{ $energy['color'] }}" stroke="#000" stroke-width="1" />
                    <text x="9" y="35" fill="#fff" stroke="#000" stroke-width=".55" paint-order="stroke" font-family="Arial, sans-serif" font-size="{{ strlen($energy['class']) > 1 ? 21 : 32 }}" font-weight="bold">{{ $energy['class'] }}</text>
                    <text x="53" y="18" fill="#000" font-family="Arial, sans-serif" font-size="{{ strlen($energy['scale_max']) > 1 ? 8 : 10 }}" font-weight="bold">{{ $energy['scale_max'] }}</text>
                    <path d="M56 20v9m-2-2 2 2 2-2" fill="none" stroke="#000" />
                    <text x="53" y="40" fill="#000" font-family="Arial, sans-serif" font-size="{{ strlen($energy['scale_min']) > 1 ? 8 : 10 }}" font-weight="bold">{{ $energy['scale_min'] }}</text>
                </svg>
                <span class="energy-summary-text">{{ __('energy.label') }}</span>
            </summary>
            <div class="energy-document">
                <img src="{{ $energy['image'] }}" width="500" height="1000" loading="lazy" alt="{{ __('energy.image', ['reference' => $energy['reference'].(isset($energy['scope']) ? ' — '.__('energy.contexts.'.$energy['scope']) : ''), 'class' => $energy['class']]) }}">
                <a href="{{ $energy['label_url'] ?? $energy['label'] }}">{{ __(str_ends_with($energy['label'], '.pdf') ? 'energy.pdf' : 'energy.original') }}</a>
            </div>
        </details>
        <a class="energy-sheet" href="{{ $energy['sheet_url'] ?? $energy['sheet'] }}">{{ __('energy.sheet') }}<span class="sr-only"> — {{ $energy['reference'] }}</span></a>
    </div>
@endforeach
