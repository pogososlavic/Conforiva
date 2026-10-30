{{ $mailText }}

@if($hasBankDetails ?? false)
{{ __('commerce.bank_email_attachment', [], $language) }}
@endif
