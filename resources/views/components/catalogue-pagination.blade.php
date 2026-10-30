@props(['products', 'filters' => [], 'locale', 'routeName' => 'catalogue'])
@if($products->lastPage() > 1)
    @php
        $current = $products->currentPage();
        $last = $products->lastPage();
        $pages = array_values(array_unique(array_merge([1], range(max(1, $current - 1), min($last, $current + 1)), [$last])));
        sort($pages);
        $previous = 0;
        $pageUrl = fn (int $number) => route($routeName, ['locale' => $locale] + array_merge(\Illuminate\Support\Arr::except($filters, ['page']), $number > 1 ? ['page' => $number] : []));
    @endphp
    <nav class="pagination" aria-label="{{ __('catalogue.pagination') }}">
        <ul class="pagination-links">
            <li class="pagination-direction">@if($products->onFirstPage())<span aria-disabled="true"><span aria-hidden="true" class="pagination-arrow">←</span><span class="pagination-direction-label">{{ __('catalogue.previous') }}</span></span>@else<a href="{{ $pageUrl($current - 1) }}#results-heading" rel="prev"><span aria-hidden="true" class="pagination-arrow">←</span><span class="pagination-direction-label">{{ __('catalogue.previous') }}</span></a>@endif</li>
            @foreach($pages as $number)
                @if($number - $previous > 1)<li class="pagination-gap"><span aria-hidden="true">…</span></li>@endif
                <li>@if($number === $current)<span aria-current="page" aria-label="{{ __('catalogue.page', ['current' => $current, 'total' => $last]) }}">{{ $number }}</span>@else<a href="{{ $pageUrl($number) }}#results-heading" aria-label="{{ __('catalogue.go_page', ['number' => $number]) }}">{{ $number }}</a>@endif</li>
                @php $previous = $number; @endphp
            @endforeach
            <li class="pagination-direction">@if($products->hasMorePages())<a href="{{ $pageUrl($current + 1) }}#results-heading" rel="next"><span class="pagination-direction-label">{{ __('catalogue.next') }}</span><span aria-hidden="true" class="pagination-arrow">→</span></a>@else<span aria-disabled="true"><span class="pagination-direction-label">{{ __('catalogue.next') }}</span><span aria-hidden="true" class="pagination-arrow">→</span></span>@endif</li>
        </ul>
        <form class="page-jump" method="get" action="{{ route($routeName, ['locale' => $locale]) }}#results-heading">
            @foreach($filters as $key => $value)@if($key !== 'page')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
            <label for="jump-page">{{ __('catalogue.jump_page') }}</label><input id="jump-page" name="page" type="number" inputmode="numeric" min="1" max="{{ $last }}" value="{{ $current }}" required><button type="submit">{{ __('catalogue.go') }}</button>
        </form>
    </nav>
@endif
