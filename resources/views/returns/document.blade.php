<!doctype html><html lang="{{ $locale }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>HOME CONFORT — {{ __('returns.received') }}</title>
<style>
    * { box-sizing: border-box; }
    html { color-scheme: light; background: #faf9f6; color: #28241b; font-family: system-ui, sans-serif; }
    body { width: min(760px, calc(100% - 32px)); margin: 24px auto; padding: clamp(16px, 4vw, 32px); border: 1px solid #ded8c9; border-radius: 12px; background: #fff; line-height: 1.6; overflow-wrap: anywhere; }
    h1 { margin-top: 0; font-size: clamp(24px, 5vw, 32px); line-height: 1.2; }
    dt { font-weight: 700; margin-top: 16px; }
    dd { margin: 4px 0 0; white-space: pre-wrap; }
    @media print { html { background: #fff; } body { width: 100%; margin: 0; padding: 0; border: 0; } }
</style></head><body>
<h1>HOME CONFORT — {{ __('returns.received') }}</h1>
<p>{{ __('returns.reference') }} : {{ $record['id'] }}</p><p>{{ __('returns.date') }} : {{ $record['submitted_at'] }} (UTC)</p>
<p>{{ __('returns.declaration') }}</p>
<dl>@foreach(['name', 'contract', 'email', 'phone', 'notes'] as $field)@if($record['declaration'][$field] ?? null)<dt>{{ __('returns.'.$field) }}</dt><dd>{{ $record['declaration'][$field] }}</dd>@endif @endforeach</dl>
<p>{{ __('returns.method') }} : {{ __('returns.download_method') }}</p>
<p>{{ __('returns.receipt_notice') }}</p><p>{{ __('returns.logistics_notice') }}</p>
<x-return-details />
<p>{{ __('documents.registered_office') }} : HOME CONFORT — {{ config('legal.company.address') }}</p>
</body></html>
