@php($language = $language ?? $data['document_language'] ?? 'fr')
<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 38px 48px; }
        body { margin: 0; color: #242923; font-family: 'DejaVu Sans', sans-serif; font-size: 10px; line-height: 1.45; }
        h1, h2, p { margin: 0; }
        h1 { color: #242923; font-size: 27px; line-height: 1.15; font-weight: 700; }
        h2 { margin: 11px 0 6px; font-size: 10px; font-weight: 700; color: #555e50; letter-spacing: .6px; page-break-after: avoid; }
        p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { vertical-align: top; word-wrap: break-word; }
        .right { text-align: right; }
        .muted { color: #697066; }
        .head { margin-bottom: 10px; border-bottom: 3px solid #c2a34b; }
        .head td { padding: 0 0 10px; }
        .head .brand-column { width: 43%; padding-top: 3px; }
        .brand { width: 144px; height: auto; }
        .brand-fallback { font-size: 20px; letter-spacing: 1px; }
        .document-number { margin-top: 7px; font-size: 11px; font-weight: 700; color: #6b5826; }
        .document-date { margin-top: 5px; font-size: 9px; color: #697066; }
        .draft { margin: 0 0 12px; padding: 9px 12px; border-left: 3px solid #b99738; background: #fbf5df; color: #665223; font-size: 9px; font-weight: 700; }
        .parties { margin-bottom: 9px; }
        .parties .party { width: 47%; padding: 9px 11px; border: 1px solid #e1e5dd; }
        .parties .gap { width: 6%; }
        .party h2 { margin: 0 0 5px; color: #797f74; font-size: 8px; letter-spacing: 1px; }
        .party .party-name { color: #242923; font-size: 12px; }
        .party p { margin: 2px 0; font-size: 9px; line-height: 1.35; }
        .party.customer { background: #f6f7f3; }
        .company-registration { color: #697066; font-size: 8px !important; }
        .metadata { margin: 5px 0 8px; padding: 6px 10px; background: #f7f6f0; border-left: 2px solid #c2a34b; font-size: 9px; }
        .metadata p { margin: 1px 0; }
        .code { font-size: 8px; word-wrap: break-word; }
        .lines thead { display: table-header-group; }
        .lines th { padding: 8px 6px; background: #30382e; color: #fff; font-size: 8px; font-weight: 700; text-align: right; line-height: 1.4; }
        .lines th:first-child { text-align: left; padding-left: 10px; }
        .lines td { padding: 7px 6px; border-bottom: 1px solid #e1e5dd; font-size: 9px; text-align: right; }
        .lines td:first-child { text-align: left; padding-left: 10px; }
        .lines tr { page-break-inside: avoid; }
        .lines tbody tr:nth-child(even) td { background: #f7f8f5; }
        .item-name { font-size: 9.5px; }
        .item-reference { margin: 2px 0 0; color: #697066; font-size: 8px; }
        .item-details { margin: 2px 0 0; color: #697066; font-size: 7.5px; }
        .calculation-note { margin-top: 6px; font-size: 7px; line-height: 1.4; }
        .total { margin: 9px 0 7px; page-break-inside: avoid; }
        .total td { padding: 3px 9px; font-size: 9px; }
        .total .spacer { width: 47%; padding: 0; }
        .total .total-label { width: 30%; color: #626b5c; }
        .total .total-value { width: 23%; }
        .total td.grand-label, .total td.grand-value { padding-top: 8px; padding-bottom: 8px; background: #eee5c8; color: #252c22; border-top: 1px solid #c2a34b; font-size: 13px; font-weight: 700; }
        .tax-note { color: #697066; font-size: 8px; }
        .bank-section { margin-top: 9px; page-break-inside: avoid; }
        .bank-section h2 { margin: 0; padding: 6px 10px; background: #eee5c8; color: #554724; font-size: 9px; }
        .bank-details { border: 1px solid #e3dcc6; }
        .bank-details td { width: 50%; padding: 7px 10px; background: #fcfaf3; font-size: 9px; line-height: 1.7; }
        .bank-details strong { color: #5a6253; font-size: 8px; }
        .conditions { white-space: pre-wrap; word-wrap: break-word; font-size: 8px; line-height: 1.5; orphans: 3; widows: 3; }
        .terms-section { margin-top: 9px; }
        .terms-section h2 { margin-top: 0; padding-top: 7px; border-top: 1px solid #dfe3da; }
        .terms-section p { margin: 4px 0; }
        .payment-note { font-size: 8px; color: #697066; }
        .acceptance { margin-top: 8px; padding: 6px 10px; border: 1px solid #e1e5dd; font-size: 8px; page-break-inside: avoid; }
        .identity { margin-top: 10px; padding-top: 6px; border-top: 1px solid #dfe3da; color: #697066; font-size: 7px; line-height: 1.5; page-break-inside: avoid; }
        .identity strong { color: #414b3b; }
    </style>
</head>
<body>
@php($text = fn ($key) => __('documents.'.$key, [], $language))
@php($money = fn ($value) => $value === null ? $text('to_complete') : \App\Services\Documents\DocumentData::money($value, $language))
@php([$decimalSeparator, $thousandsSeparator] = match ($language) { 'en' => ['.', ','], 'de' => [',', '.'], default => [',', ' '] })
@php($quoteValidity = null)
@if(!empty($data['valid_until_at']))
    @php($validityDate = \Illuminate\Support\Carbon::parse($data['valid_until_at'])->setTimezone('Europe/Paris'))
    @php($quoteValidity = match ($language) { 'en' => $validityDate->format('d/m/Y H:i').' (Paris time)', 'de' => $validityDate->format('d.m.Y H:i').' Uhr (Pariser Zeit)', default => $validityDate->format('d/m/Y à H:i').' (heure de Paris)' })
@endif
<table class="head"><tr>
    <td class="brand-column">@if($logo)<img class="brand" src="{{ $logo }}" alt="HOME CONFORT">@else<strong class="brand-fallback">HOME CONFORT</strong>@endif</td>
    <td class="right"><h1>{{ $text('types.'.$data['type']) }}</h1><p class="document-number">{{ $record['number'] ?? $text('no_number') }}</p><p class="document-date">{{ $text('date') }} : {{ isset($record['issued_at']) ? substr($record['issued_at'], 0, 10) : $text('draft') }}</p></td>
</tr></table>
@if($record['status'] === 'draft')<div class="draft">{{ $text('draft_notice') }}</div>@endif
<table class="parties"><tr>
    <td class="party">
        <h2>{{ $text('issuer') }}</h2>
        <strong class="party-name">{{ $issuer['name'] }}</strong>
        <p>{{ $issuer['form'] }}@if($issuer['capital'] ?? null)<br>{{ $text('capital') }} : {{ $issuer['capital'] }}@endif</p>
        <p>{{ $text('registered_office') }} : {{ $issuer['address'] }}</p>
        @if($issuer['email'] ?? null)<p>{{ $text('email') }} : {{ $issuer['email'] }}</p>@endif
        @if($issuer['phone'] ?? null)<p>{{ $text('phone') }} : {{ $issuer['phone'] }}</p>@endif
        <p class="company-registration">{{ $text('siren') }} : {{ $issuer['siren'] }}<br>{{ $text('siret') }} : {{ $issuer['siret'] ?? '' }}<br>{{ $text('vat_number') }} : {{ $issuer['vat'] }}</p>
    </td>
    <td class="gap"></td>
    <td class="party customer">
        <h2>{{ $text('customer') }}</h2>
        @if($data['customer']['company'])<strong class="party-name">{{ $data['customer']['company'] }}</strong>@endif
        <p><strong>{{ $data['customer']['name'] }}</strong></p>
        <p>{{ $data['customer']['address'] }}</p>
        <p>{{ $data['customer']['postal_code'] }} {{ $data['customer']['city'] }} · {{ $data['customer']['country'] }}</p>
        @if($data['customer']['siren'])<p>{{ $text('siren') }} : {{ $data['customer']['siren'] }}</p>@endif
        @if($data['customer']['vat'])<p>{{ $text('vat') }} : {{ $data['customer']['vat'] }}</p>@endif
        @if($data['customer']['email'])<p>{{ $data['customer']['email'] }}</p>@endif
    </td>
</tr></table>
@if($data['delivery_address'] || $data['buyer_reference'] || ($record['source_document']['number'] ?? null) || $data['order_id'] || in_array($data['type'], ['quote', 'invoice'], true))
<div class="metadata">
    @if($data['delivery_address'])<p><strong>{{ $text('delivery_address') }} :</strong> {{ $data['delivery_address'] }}</p>@endif
    @if($data['buyer_reference'])<p><strong>{{ $text('customer_order_reference') }} :</strong> {{ $data['buyer_reference'] }}</p>@endif
    @if($record['source_document']['number'] ?? null)<p>{{ $text('source_document') }} : {{ $record['source_document']['number'] }}</p>@endif
    @if($data['order_id'])<p class="code">{{ $text('ecommerce_order') }} : {{ $data['order_id'] }}</p>@endif
    @if($data['type'] === 'quote')<p><strong>{{ $text('valid_until') }} :</strong> {{ $quoteValidity ?: ($data['valid_until'] ?: $text('to_complete')) }}</p>@endif
    @if($data['type'] === 'invoice')<p><strong>{{ $text('sale_delivery_date') }} :</strong> {{ $data['operation_date'] ?: $text('to_complete') }} · {{ $text('goods_sale') }}</p>@endif
</div>
@endif
<h2>{{ $text('items_eur') }}</h2>
<table class="lines">
    <thead><tr><th width="37%">{{ $text('designation_reference') }}</th><th width="6%">{{ $text('quantity') }}</th><th width="15%">{{ $text('unit_ex_tax') }}</th><th width="10%">{{ $text('vat') }}</th><th width="15%">{{ $text('total_ex_tax') }}</th><th width="17%">{{ $text('total_inc_tax') }}</th></tr></thead>
    <tbody>@foreach($data['lines'] as $line)<tr>
        <td><strong class="item-name">{{ $line['name'] ?: $text('to_complete') }}</strong><p class="item-reference">{{ $line['reference'] ?: $text('to_complete') }}</p><p class="item-details">{{ $text('unit_inc_tax') }} : {{ $money($line['unit_cents']) }}@if($line['eco_cents'] !== null && $line['eco_cents'] > 0)<br>{{ $text('eco_contribution') }} : {{ $money($line['eco_cents']) }} / {{ $text('per_unit') }}@endif</p></td>
        <td>{{ $line['quantity'] }}</td>
        <td>{{ $line['net_cents'] === null ? $text('to_complete') : number_format($line['net_cents'] / ($line['quantity'] * 100), 4, $decimalSeparator, $thousandsSeparator).' €' }}</td>
        <td>{{ $data['tax_mode'] === 'taxed' ? ($line['vat_rate'] !== '' ? str_replace('.', $decimalSeparator, $line['vat_rate']).' %' : $text('to_complete')) : ($data['tax_mode'] ? $text('vat_not_collected') : $text('to_complete')) }}</td>
        <td>{{ $money($line['net_cents']) }}</td><td>{{ $money($line['gross_cents']) }}</td>
    </tr>@endforeach</tbody>
</table>
<p class="muted calculation-note">{{ $text('ex_tax_note') }}</p>
<table class="total">
    <tr><td class="spacer"></td><td class="total-label">{{ $text('total_ex_tax') }}</td><td class="right total-value">{{ $money($data['net_cents']) }}</td></tr>
    @foreach($data['tax_groups'] as $rate => $amount)<tr><td class="spacer"></td><td class="total-label">{{ $text('vat') }} {{ $data['tax_mode'] === 'taxed' ? str_replace('.', $decimalSeparator, (string) $rate).' %' : $text('not_collected') }}</td><td class="right total-value">{{ $money($amount) }}</td></tr>@endforeach
    <tr class="grand"><td class="spacer"></td><td class="total-label grand-label">{{ $text('total_inc_tax') }}</td><td class="right total-value grand-value">{{ $money($data['total_cents']) }}</td></tr>
</table>
@if($data['tax_note'])<p class="conditions tax-note">{{ $data['tax_note'] }}</p>@endif
@if(in_array($data['type'], ['quote', 'purchase_order'], true) && !empty($data['bank_details']))
<div class="bank-section">
    <h2>{{ $text('bank_details') }}</h2>
    <table class="bank-details"><tr><td><strong>{{ $text('bank_beneficiary') }} :</strong> {{ $data['bank_details']['beneficiary'] }}<br><strong>{{ $text('bank_name') }} :</strong> {{ $data['bank_details']['bank_name'] }}</td><td><strong>IBAN :</strong> {{ trim(chunk_split($data['bank_details']['iban'], 4, ' ')) }}<br><strong>BIC / SWIFT :</strong> {{ $data['bank_details']['bic'] }}</td></tr></table>
</div>
@endif
<div class="terms-section">
    <h2>{{ $text('conditions') }}</h2>
    <p class="conditions"><strong>{{ $text('payment') }} :</strong> {{ $data['payment_terms'] ?: $text('to_complete') }}</p>
    <p class="conditions"><strong>{{ $text('delivery') }} :</strong> {{ $data['delivery_terms'] ?: $text('to_complete') }}</p>
    @if($data['type'] === 'invoice')<p><strong>{{ $text('due_date') }} :</strong> {{ $data['due_date'] ?: $text('to_complete') }}</p><p class="conditions"><strong>{{ $text('discount') }} :</strong> {{ $data['discount_terms'] ?: $text('to_complete') }}</p>@if($data['professional'])<p class="conditions"><strong>{{ $text('late_penalties') }} :</strong> {{ $data['late_terms'] ?: $text('to_complete') }}</p><p class="conditions">{{ $text('collection_indemnity') }}</p>@endif @endif
    @if($data['legal_notes'])<p class="conditions">{{ $data['legal_notes'] }}</p>@endif
    <p class="payment-note">{{ $text('payment_not_confirmed') }}</p>
</div>
@if($data['type'] === 'quote')<div class="acceptance">{{ $text('quote_acceptance') }}</div>@endif
@if($data['type'] === 'purchase_order')<div class="acceptance">{{ $text('purchase_order_validation') }}</div>@endif
<div class="identity">
    <strong>{{ $issuer['name'] }}</strong>@if($issuer['rcs'] ?? null) · {{ $issuer['rcs'] }}@endif
    @if($issuer['rcs_registered_on'] ?? null)<br>{{ $text('registered_on_rcs') }} : {{ $issuer['rcs_registered_on'] }}@endif
    @if($issuer['rne_registered_on'] ?? null) · {{ $text('registered_on_rne') }} : {{ $issuer['rne_registered_on'] }}@endif
    @if($data['type'] !== 'quote' && ($issuer['manager'] ?? null))<br>{{ $text('manager') }} : {{ $issuer['manager'] }}@endif
</div>
</body>
</html>
