@props(['product', 'locale'])
@php($energy = app(\App\Services\ProductEnergyLabels::class)->forProduct($product, $locale))
@if($energy)
    <div class="product-energy">
        <details class="energy-disclosure">
            <summary aria-label="{{ __('energy.open', ['class' => $energy['class'], 'scale' => $energy['scale'], 'reference' => $energy['reference']]) }}">
                <svg class="energy-arrow" viewBox="0 0 90 48" aria-hidden="true" focusable="false">
                    <path d="M1 1H69L89 24L69 47H1Z" fill="{{ $energy['color'] }}" stroke="#000" stroke-width="1" />
                    <text x="15" y="35" fill="#fff" stroke="#000" stroke-width=".55" paint-order="stroke" font-family="Arial, sans-serif" font-size="32" font-weight="bold">{{ $energy['class'] }}</text>
                    <text x="53" y="18" fill="#000" font-family="Arial, sans-serif" font-size="10" font-weight="bold">A</text>
                    <path d="M56 20v9m-2-2 2 2 2-2" fill="none" stroke="#000" />
                    <text x="53" y="40" fill="#000" font-family="Arial, sans-serif" font-size="10" font-weight="bold">G</text>
                </svg>
                <span class="energy-summary-text">{{ __('energy.label') }}</span>
            </summary>
            <div class="energy-document">
                <img src="{{ $energy['image'] }}" width="500" height="1000" loading="lazy" alt="{{ __('energy.image', ['reference' => $energy['reference'], 'class' => $energy['class']]) }}">
                <a href="{{ $energy['label'] }}">{{ __('energy.pdf') }}</a>
            </div>
        </details>
        <a class="energy-sheet" href="{{ $energy['sheet'] }}">{{ __('energy.sheet') }}<span class="sr-only"> — {{ $energy['reference'] }}</span></a>
    </div>
@endif
