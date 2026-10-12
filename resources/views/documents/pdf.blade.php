@php($language = $language ?? $data['document_language'] ?? 'fr')
<!doctype html><html lang="{{ $language }}"><head><meta charset="utf-8"><style>
@page { margin: 35px 35px 55px; } body{font-family:'DejaVu Sans',sans-serif;font-size:10px;color:#222;line-height:1.45} h1{font-size:23px;margin:0} h2{font-size:12px;margin:12px 0 6px} p{margin:4px 0} table{width:100%;border-collapse:collapse;table-layout:fixed} td,th{padding:7px;vertical-align:top;word-wrap:break-word} .head{border-bottom:4px solid #f7c844;margin-bottom:16px} .head td{padding:0 0 12px} .brand{width:90px} .right{text-align:right} .muted{color:#555} .draft{background:#fff3bd;padding:9px;font-weight:bold;margin:12px 0} .parties td{width:50%;padding:10px;background:#f5f5f5}.lines th{background:#ffe59b;text-align:left;font-size:9px}.lines td{border-bottom:1px solid #ddd;font-size:9px}.lines thead{display:table-header-group}.lines tr{page-break-inside:avoid}.total{margin:15px 0;page-break-inside:avoid}.total td{padding:3px 7px}.grand{font-size:15px;font-weight:bold;background:#fff3bd}.conditions{white-space:pre-wrap;word-wrap:break-word}.identity{margin-top:18px;font-size:8px;border-top:1px solid #ddd;padding-top:8px} .code{font-size:8px;word-wrap:break-word}
</style></head><body>
@php($text = fn ($key) => __('documents.'.$key, [], $language))
@php($money = fn ($value) => $value === null ? $text('to_complete') : \App\Services\Documents\DocumentData::money($value, $language))
@php([$decimalSeparator, $thousandsSeparator] = match ($language) { 'en' => ['.', ','], 'de' => [',', '.'], default => [',', ' '] })
@php($quoteValidity = null)
@if(!empty($data['valid_until_at']))
    @php($validityDate = \Illuminate\Support\Carbon::parse($data['valid_until_at'])->setTimezone('Europe/Paris'))
    @php($quoteValidity = match ($language) { 'en' => $validityDate->format('d/m/Y H:i').' (Paris time)', 'de' => $validityDate->format('d.m.Y H:i').' Uhr (Pariser Zeit)', default => $validityDate->format('d/m/Y à H:i').' (heure de Paris)' })
@endif
<table class="head"><tr><td width="30%">@if($logo)<img class="brand" src="{{ $logo }}" alt="HOMYA">@else<strong>HOMYA</strong>@endif</td><td width="70%" class="right"><h1>{{ $text('types.'.$data['type']) }}</h1><strong>{{ $record['number'] ?? $text('no_number') }}</strong><p>{{ $text('date') }} : {{ isset($record['issued_at']) ? substr($record['issued_at'], 0, 10) : $text('draft') }}</p></td></tr></table>
@if($record['status'] === 'draft')<div class="draft">{{ $text('draft_notice') }}</div>@endif
<table class="parties"><tr><td>
<h2>{{ $text('issuer') }}</h2>
<strong>{{ $issuer['name'] }}</strong>
<p>{{ $issuer['form'] }}<br>{{ $text('capital') }} : {{ $issuer['capital'] }}</p>
<p>{{ $text('registered_office') }} : {{ $issuer['address'] }}</p>
@if($issuer['email'] ?? null)<p>{{ $text('email') }} : {{ $issuer['email'] }}</p>@endif
@if($issuer['phone'] ?? null)<p>{{ $text('phone') }} : {{ $issuer['phone'] }}</p>@endif
<p>{{ $text('siren') }} : {{ $issuer['siren'] }}<br>{{ $text('siret') }} : {{ $issuer['siret'] ?? '' }}<br>{{ $text('vat_number') }} : {{ $issuer['vat'] }}</p>
</td><td><h2>{{ $text('customer') }}</h2>@if($data['customer']['company'])<strong>{{ $data['customer']['company'] }}</strong>@endif<p>{{ $data['customer']['name'] }}</p><p>{{ $data['customer']['address'] }}</p><p>{{ $data['customer']['postal_code'] }} {{ $data['customer']['city'] }} · {{ $data['customer']['country'] }}</p>@if($data['customer']['siren'])<p>{{ $text('siren') }} : {{ $data['customer']['siren'] }}</p>@endif @if($data['customer']['vat'])<p>{{ $text('vat') }} : {{ $data['customer']['vat'] }}</p>@endif @if($data['customer']['email'])<p>{{ $data['customer']['email'] }}</p>@endif</td></tr></table>
@if($data['delivery_address'])<p><strong>{{ $text('delivery_address') }} :</strong> {{ $data['delivery_address'] }}</p>@endif
@if($data['buyer_reference'])<p><strong>{{ $text('customer_order_reference') }} :</strong> {{ $data['buyer_reference'] }}</p>@endif
@if($record['source_document']['number'] ?? null)<p>{{ $text('source_document') }} : {{ $record['source_document']['number'] }}</p>@endif
@if($data['order_id'])<p class="code">{{ $text('ecommerce_order') }} : {{ $data['order_id'] }}</p>@endif
@if($data['type'] === 'quote')<p><strong>{{ $text('valid_until') }} :</strong> {{ $quoteValidity ?: ($data['valid_until'] ?: $text('to_complete')) }}</p>@endif
@if($data['type'] === 'invoice')<p><strong>{{ $text('sale_delivery_date') }} :</strong> {{ $data['operation_date'] ?: $text('to_complete') }} · {{ $text('goods_sale') }}</p>@endif
<h2>{{ $text('items_eur') }}</h2>
<table class="lines"><thead><tr><th width="37%">{{ $text('designation_reference') }}</th><th width="6%">{{ $text('quantity') }}</th><th width="15%">{{ $text('unit_ex_tax') }}</th><th width="10%">{{ $text('vat') }}</th><th width="15%">{{ $text('total_ex_tax') }}</th><th width="17%">{{ $text('total_inc_tax') }}</th></tr></thead><tbody>@foreach($data['lines'] as $line)<tr><td><strong>{{ $line['name'] ?: $text('to_complete') }}</strong><br>{{ $line['reference'] ?: $text('to_complete') }}<br><span class="muted">{{ $text('unit_inc_tax') }} : {{ $money($line['unit_cents']) }}</span>@if($line['eco_cents'] !== null && $line['eco_cents'] > 0)<br>{{ $text('eco_contribution') }} : {{ $money($line['eco_cents']) }} / {{ $text('per_unit') }}@endif</td><td>{{ $line['quantity'] }}</td><td>{{ $line['net_cents'] === null ? $text('to_complete') : number_format($line['net_cents'] / ($line['quantity'] * 100), 4, $decimalSeparator, $thousandsSeparator).' €' }}</td><td>{{ $data['tax_mode'] === 'taxed' ? ($line['vat_rate'] !== '' ? str_replace('.', $decimalSeparator, $line['vat_rate']).' %' : $text('to_complete')) : ($data['tax_mode'] ? $text('vat_not_collected') : $text('to_complete')) }}</td><td>{{ $money($line['net_cents']) }}</td><td>{{ $money($line['gross_cents']) }}</td></tr>@endforeach</tbody></table>
<p class="muted">{{ $text('ex_tax_note') }}</p>
<table class="total"><tr><td width="60%"></td><td>{{ $text('total_ex_tax') }}</td><td class="right">{{ $money($data['net_cents']) }}</td></tr>@foreach($data['tax_groups'] as $rate => $amount)<tr><td></td><td>{{ $text('vat') }} {{ $data['tax_mode'] === 'taxed' ? str_replace('.', $decimalSeparator, (string) $rate).' %' : $text('not_collected') }}</td><td class="right">{{ $money($amount) }}</td></tr>@endforeach<tr class="grand"><td></td><td>{{ $text('total_inc_tax') }}</td><td class="right">{{ $money($data['total_cents']) }}</td></tr></table>
@if($data['tax_note'])<p class="conditions">{{ $data['tax_note'] }}</p>@endif
@if(in_array($data['type'], ['quote', 'purchase_order'], true) && !empty($data['bank_details']))<h2>{{ $text('bank_details') }}</h2><table class="parties bank-details"><tr><td><strong>{{ $text('bank_beneficiary') }} :</strong> {{ $data['bank_details']['beneficiary'] }}<br><strong>{{ $text('bank_name') }} :</strong> {{ $data['bank_details']['bank_name'] }}</td><td><strong>IBAN :</strong> {{ trim(chunk_split($data['bank_details']['iban'], 4, ' ')) }}<br><strong>BIC / SWIFT :</strong> {{ $data['bank_details']['bic'] }}</td></tr></table>@endif
<h2>{{ $text('conditions') }}</h2><p class="conditions"><strong>{{ $text('payment') }} :</strong> {{ $data['payment_terms'] ?: $text('to_complete') }}</p><p class="conditions"><strong>{{ $text('delivery') }} :</strong> {{ $data['delivery_terms'] ?: $text('to_complete') }}</p>
@if($data['type'] === 'invoice')<p><strong>{{ $text('due_date') }} :</strong> {{ $data['due_date'] ?: $text('to_complete') }}</p><p class="conditions"><strong>{{ $text('discount') }} :</strong> {{ $data['discount_terms'] ?: $text('to_complete') }}</p>@if($data['professional'])<p class="conditions"><strong>{{ $text('late_penalties') }} :</strong> {{ $data['late_terms'] ?: $text('to_complete') }}</p><p>{{ $text('collection_indemnity') }}</p>@endif @endif
@if($data['legal_notes'])<p class="conditions">{{ $data['legal_notes'] }}</p>@endif
<p class="muted">{{ $text('payment_not_confirmed') }}</p>
@if($data['type'] === 'quote')<p>{{ $text('quote_acceptance') }}</p>@endif
@if($data['type'] === 'purchase_order')<p>{{ $text('purchase_order_validation') }}</p>@endif
<div class="identity">
<strong>{{ $issuer['name'] }}</strong> · {{ $issuer['rcs'] }}
@if($issuer['rcs_registered_on'] ?? null)<br>{{ $text('registered_on_rcs') }} : {{ $issuer['rcs_registered_on'] }}@endif
@if($issuer['rne_registered_on'] ?? null) · {{ $text('registered_on_rne') }} : {{ $issuer['rne_registered_on'] }}@endif
@if($issuer['manager'] ?? null)<br>{{ $text('manager') }} : {{ $issuer['manager'] }}@endif
</div>
</body></html>
