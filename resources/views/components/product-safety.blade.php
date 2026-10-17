@props(['product'])
@if($safety = app(\App\Services\ProductSafetyInformation::class)->forProduct($product))
<section class="product-safety specifications" aria-labelledby="product-safety-heading">
    <h2 id="product-safety-heading">{{ __('safety.title') }}</h2>
    <dl><div><dt>{{ __('safety.brand') }}</dt><dd>{{ $safety['brand'] }}</dd></div><div><dt>{{ __('catalogue.reference') }}</dt><dd>{{ $safety['reference'] }}</dd></div>
    @if(isset($safety['contact_name']))
        <div><dt>{{ __('safety.contact') }}</dt><dd>{{ $safety['contact_name'] }}<br>{{ $safety['address'] }}<br><a href="mailto:{{ $safety['email'] }}">{{ $safety['email'] }}</a></dd></div>
    @endif
    </dl>
    @if(isset($safety['contact_name']))<p>{{ __('safety.contact_scope') }}</p>@endif
    @if(isset($safety['warnings']))<ul>@foreach(__('safety.'.$safety['warnings']) as $warning)<li>{{ $warning }}</li>@endforeach</ul>@endif
    <p><a href="{{ $safety['support'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{ __('safety.support') }} ↗</a></p>
    @if(isset($safety['safety_document']))<p><a href="{{ $safety['safety_document'] }}" target="_blank" rel="noopener noreferrer">{{ __('safety.document') }} (PDF)</a></p>@endif
    <p><a href="{{ $safety['source'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{ __('safety.source') }} ↗</a></p>
</section>
@endif
