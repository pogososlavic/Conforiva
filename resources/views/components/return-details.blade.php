@inject('legalInformation', 'App\Services\LegalInformation')
@if($address = $legalInformation->returnAddress())
    <dl class="company-details return-details">
        <div><dt>{{ __('legal.return_address') }}</dt><dd>HOME CONFORT<br>{{ $address }}</dd></div>
    </dl>
@else
    <p>{{ __('legal.return_destination_pending') }}</p>
@endif
