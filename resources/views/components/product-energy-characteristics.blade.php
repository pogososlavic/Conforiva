@props(['profile', 'documents' => []])
@if($profile)
<section id="product-energy-characteristics" class="specifications product-energy-characteristics" aria-labelledby="energy-characteristics-heading" tabindex="-1">
    <h2 id="energy-characteristics-heading">{{ __('energy.characteristics') }}</h2>
    <dl>
        @foreach($profile['metrics'] as $metric)
        <div><dt>{{ $metric['label'] }}@if($metric['context']) <span>({{ $metric['context'] }})</span>@endif</dt><dd>{{ $metric['value'] }}@if($metric['unit']) {{ $metric['unit'] }}@endif
            @if($metric['verified'])<small> — {{ __('energy.verified_value') }}</small>@endif
            @if($metric['source_url']) <a href="{{ $metric['source_url'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{ __('energy.source') }}<span class="sr-only"> — {{ $metric['label'] }}</span> ↗</a>@endif
        </dd></div>
        @endforeach
    </dl>
    @foreach($documents as $document)
        @if(($document['document_warning'] ?? null) === 'supplier_regulation_reference_typo_2018_1186')
            <p class="energy-document-warning">{{ __('energy.supplier_regulation_reference_typo') }}</p>
        @endif
        <p>
            @if(isset($document['scope'])){{ __('energy.contexts.'.$document['scope']) }} — @endif
            <a href="{{ $document['label_url'] ?? $document['label'] }}">{{ __(str_ends_with($document['label'], '.pdf') ? 'energy.pdf' : 'energy.original') }}<span class="sr-only"> — {{ $document['reference'] }}</span></a>
            @foreach(($document['sheet_urls'] ?? $document['sheets']) as $sheetLocale => $sheet)
                · <a href="{{ $sheet }}" hreflang="{{ $sheetLocale }}">{{ __('energy.sheet') }} ({{ strtoupper($sheetLocale) }})<span class="sr-only"> — {{ $document['reference'] }}</span></a>
            @endforeach
        </p>
    @endforeach
    @if($profile['scale'])<p>{{ __('energy.scale') }} : {{ $profile['scale'] }}</p>@endif
    @if($profile['status'] !== 'verified')<p>{{ __($profile['status'] === 'mixed' ? 'energy.mixed_data' : 'energy.archived_data') }}</p>@endif
    @if($profile['sources'])<p>{{ __('energy.sources') }}: @foreach($profile['sources'] as $source)<a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{ $source['label'] }} ↗</a>@if(!$loop->last) · @endif @endforeach</p>@endif
</section>
@endif
