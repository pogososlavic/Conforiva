<dl class="company-details">
    <div><dt>{{ __('legal.company_labels.name') }}</dt><dd>{{ config('legal.hosting.name') }}</dd></div>
    <div><dt>{{ __('legal.hosting_address') }}</dt><dd>{{ config('legal.hosting.street') }}<br>{{ config('legal.hosting.city') }}<br>{{ __('legal.hosting_country') }}</dd></div>
    <div><dt>{{ __('legal.company_labels.phone') }}</dt><dd><a href="tel:{{ str_replace(' ', '', config('legal.hosting.phone')) }}">{{ config('legal.hosting.phone') }}</a></dd></div>
    <div><dt>{{ __('legal.company_labels.email') }}</dt><dd><a href="mailto:{{ config('legal.hosting.email') }}">{{ config('legal.hosting.email') }}</a></dd></div>
    <div><dt>{{ __('legal.hosting_website') }}</dt><dd><a href="{{ config('legal.hosting.website') }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">www.hostinger.fr <span aria-hidden="true">↗</span></a></dd></div>
</dl>
