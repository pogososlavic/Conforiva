@props(['framed' => false, 'src' => null, 'alt' => '', 'eager' => false, 'width' => null, 'height' => null, 'sizes' => '(max-width: 600px) calc(100vw - 32px), (max-width: 1000px) calc((100vw - 64px) / 2), 280px'])
@php($srcset = app(\App\Services\ResponsiveMedia::class)->srcset($src))
@php($imageFraming = $framed ? app(\App\Services\CatalogueImageFraming::class)->forImage($src) : null)
<div class="product-media" data-product-media @if($framed) data-image-framing="{{ json_encode($imageFraming) }}" @endif>
    @if ($src)
        <img src="{{ $src }}" @if($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif alt="{{ $alt }}" @if($width && $height) width="{{ $width }}" height="{{ $height }}" @endif loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" data-product-image @if($framed) data-framing-sizes="{{ $sizes }}" @endif>
    @endif
    <span class="image-fallback" data-image-fallback @if($src) hidden @endif>
        <svg class="image-fallback-icon" viewBox="0 0 48 48" fill="none" aria-hidden="true" focusable="false">
            <rect x="5" y="7" width="38" height="34" rx="5" />
            <circle cx="17" cy="18" r="3" />
            <path d="m8 35 11-11 7 7 5-5 9 9" />
        </svg>
        <span>{{ __('catalogue.image_missing') }}</span>
    </span>
</div>
