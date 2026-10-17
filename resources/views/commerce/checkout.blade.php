<x-layout :title="__('commerce.checkout').' — HOMYA'" :catalogue-ready="true">
    <main id="main" class="shell commerce-page checkout-page" tabindex="-1">
        <a class="commerce-back" href="{{ route('cart.show', ['locale' => $locale]) }}">← {{ __('commerce.back_cart') }}</a>
        <h1>{{ __('commerce.checkout') }}</h1>
        <x-checkout-steps current="checkout" :locale="$locale" />
        <x-commerce-errors />
        @if(session('commerce_message'))<p class="commerce-success" role="status">{{ session('commerce_message') }}</p>@endif
        <p class="checkout-intro">{{ __(!$bankReady ? 'commerce.deferred_notice' : 'commerce.checkout_intro') }}</p>
        <div class="commerce-grid checkout-grid">
            <div class="checkout-main">
            <form id="checkout-form" class="checkout-form" method="post" action="{{ route('checkout.store', ['locale' => $locale]) }}">@csrf
                <input type="hidden" name="checkout_key" value="{{ $key }}"><input type="hidden" name="quote_hash" value="{{ $quoteHash }}">
                <input type="hidden" name="terms_hash" value="{{ $termsHash }}">
                <fieldset class="checkout-panel"><legend><span class="checkout-section-number" aria-hidden="true">1</span> {{ __('commerce.contact_details') }}</legend>
                    <div class="checkout-fields">
                        <x-checkout-field name="name" autocomplete="name" :max="150" />
                        <x-checkout-field name="email" type="email" autocomplete="email" :max="254" />
                        <x-checkout-field name="phone" type="tel" autocomplete="tel" :max="30" :required="false" />
                    </div>
                </fieldset>
                <fieldset class="checkout-panel"><legend><span class="checkout-section-number" aria-hidden="true">2</span> {{ __('commerce.shipping_address') }}</legend>
                    <x-checkout-field name="address" autocomplete="street-address" :max="200" />
                    <x-address-lookup :locale="$locale" />
                    <div class="checkout-fields">
                        <x-checkout-field name="postal_code" autocomplete="postal-code" :max="12" />
                        <x-checkout-field name="city" autocomplete="address-level2" :max="100" />
                    </div>
                    <div class="checkout-field"><label for="checkout-country">{{ __('commerce.country') }}</label><select id="checkout-country" name="country" autocomplete="country" required @if($errors->has('country')) aria-invalid="true" aria-describedby="checkout-country-error" @endif>@foreach(config('commerce.countries') as $country)<option value="{{ $country }}" @selected(old('country', 'FR') === $country)>{{ __('commerce.countries.'.$country) }}</option>@endforeach</select>
                        @error('country')<p id="checkout-country-error" class="checkout-field-error">{{ $message }}</p>@enderror
                    </div>
                    <p class="checkout-help">{{ __('commerce.shipping_free') }} {{ __('commerce.delivery_estimate') }}</p>
                </fieldset>
                <fieldset class="checkout-panel"><legend>{{ __('compliance.billing_title') }}</legend>
                    <p class="checkout-help">{{ __('compliance.billing_help') }}</p>
                    <details @if(old('billing_different') || $errors->hasAny(['billing_name', 'billing_address', 'billing_postal_code', 'billing_city', 'billing_country'])) open @endif>
                        <summary>{{ __('compliance.billing_different') }}</summary>
                        <label class="terms-checkbox"><input type="checkbox" name="billing_different" value="1" @checked(old('billing_different'))><span>{{ __('compliance.billing_different') }}</span></label>
                        @foreach(['name' => ['name', 150], 'address' => ['street-address', 200], 'postal_code' => ['postal-code', 20], 'city' => ['address-level2', 100], 'country' => ['country', 2]] as $field => $options)
                            <x-checkout-field :name="'billing_'.$field" :label="__('compliance.billing_'.$field)" :autocomplete="'billing '.$options[0]" :max="$options[1]" :required="false" :default="$field === 'country' ? 'FR' : ''" />
                            @if($field === 'address')<x-address-lookup :locale="$locale" prefix="billing_" />@endif
                        @endforeach
                        <p class="checkout-help">{{ __('compliance.billing_country_help') }}</p>
                    </details>
                </fieldset>
                @if(collect($quote['lines'])->contains(fn($line) => app(\App\Services\ApplianceTakeBack::class)->eligible($line['product'])))
                <fieldset class="checkout-panel"><legend>{{ __('takeback.title') }}</legend>
                    <p class="checkout-help">{{ __('takeback.policy') }}</p>
                    @foreach($quote['lines'] as $line)
                        @if(app(\App\Services\ApplianceTakeBack::class)->eligible($line['product']))
                        <div class="checkout-field"><label for="take-back-{{ $loop->index }}">{{ $line['product']['name'] }} — {{ __('takeback.quantity') }}</label>
                            <select id="take-back-{{ $loop->index }}" name="take_back[{{ $line['key'] }}]">
                                @for($quantity = 0; $quantity <= $line['quantity']; $quantity++)<option value="{{ $quantity }}" @selected((int) old('take_back.'.$line['key'], 0) === $quantity)>{{ $quantity === 0 ? __('takeback.none') : $quantity }}</option>@endfor
                            </select>
                        </div>
                        @endif
                    @endforeach
                    <p class="checkout-help">{{ __('takeback.preparation') }}</p>
                    <a href="{{ route('information', ['locale' => $locale, 'page' => 'livraison']) }}#reprise">{{ __('takeback.details') }}</a>
                </fieldset>
                @endif
                <fieldset class="checkout-panel payment-options"><legend><span class="checkout-section-number" aria-hidden="true">3</span> {{ __('commerce.payment_method') }}</legend>
                    @if($bankReady)<label class="payment-choice"><input type="radio" name="payment_method" value="bank_transfer" checked required><span><strong>{{ __('commerce.bank_method') }}</strong><small>{{ __('commerce.bank_next_step') }}</small></span></label><p class="checkout-help">{{ __('commerce.bank_deadline_notice') }}</p>@endif
                    @if(!$bankReady)<input type="hidden" name="payment_method" value="deferred"><strong>{{ __('commerce.status.pending_payment_details') }}</strong><p class="checkout-help">{{ __('commerce.deferred_notice') }}</p><p class="checkout-help">{{ __('commerce.deferred_reservation_notice') }}</p>@endif
                    @error('payment_method')<p class="checkout-field-error" role="alert">{{ $message }}</p>@enderror
                </fieldset>
                <div class="checkout-panel checkout-consent">
                    <label class="terms-checkbox"><input type="checkbox" name="terms" value="1" required @if($errors->has('terms')) aria-invalid="true" aria-describedby="checkout-terms-error" @endif><span>{{ __('commerce.accept_terms') }} <a href="{{ route('information', ['locale' => $locale, 'page' => 'cgv']) }}">{{ __('commerce.terms') }}</a>.</span></label>
                    @error('terms')<p id="checkout-terms-error" class="checkout-field-error">{{ $message }}</p>@enderror
                    <label class="terms-checkbox"><input type="checkbox" name="privacy_acknowledged" value="1" required @if($errors->has('privacy_acknowledged')) aria-invalid="true" aria-describedby="checkout-privacy-error" @endif><span>{{ __('commerce.acknowledge_privacy') }} <a href="{{ route('information', ['locale' => $locale, 'page' => 'confidentialite']) }}">{{ __('commerce.privacy') }}</a>.</span></label>
                    @error('privacy_acknowledged')<p id="checkout-privacy-error" class="checkout-field-error">{{ $message }}</p>@enderror
                    <label class="terms-checkbox"><input type="checkbox" name="shipping_acknowledged" value="1" required @if($errors->has('shipping_acknowledged')) aria-invalid="true" aria-describedby="checkout-shipping-error" @endif><span>{{ __('commerce.acknowledge_shipping') }}</span></label>
                    @error('shipping_acknowledged')<p id="checkout-shipping-error" class="checkout-field-error">{{ $message }}</p>@enderror
                    <p class="checkout-help">{{ __('commerce.privacy_acknowledgement_notice') }}</p>
                </div>
            </form>
                @if($bankReady)<x-bank-transfer-guide :locale="$locale" :ready="true" />@endif
            </div>
            <aside class="commerce-summary checkout-summary" aria-labelledby="checkout-summary-heading">
                <div class="checkout-summary-heading"><h2 id="checkout-summary-heading">{{ __('commerce.summary') }}</h2><a href="{{ route('cart.show', ['locale' => $locale]) }}">{{ __('commerce.edit_cart') }}</a></div>
                <ul class="checkout-items">@foreach($quote['lines'] as $line)<li>
                    <x-product-image :src="$line['product']['image']['local_url'] ?? null" alt="" sizes="64px" />
                    <div><strong>{{ $line['product']['name'] }}</strong>@if($line['variant_name'])<span>{{ $line['variant_name'] }}</span>@endif<span>{{ __('commerce.quantity') }} : {{ $line['quantity'] }}</span><b>{{ \App\Services\Catalogue::money($line['line_cents'], $locale) }}</b><x-product-energy :product="$line['product']" :locale="$locale" /></div>
                </li>@endforeach</ul>
                <dl class="checkout-totals"><div><dt>{{ __('commerce.subtotal') }}</dt><dd>{{ \App\Services\Catalogue::money($quote['total_cents'], $locale) }}</dd></div><div><dt>{{ __('commerce.delivery') }}</dt><dd>{{ __('commerce.free_delivery') }}</dd></div><div class="checkout-total"><dt>{{ __('commerce.total_tax_included') }}</dt><dd>{{ \App\Services\Catalogue::money($quote['total_cents'], $locale) }}</dd></div></dl>
                <div class="checkout-next-step"><strong>{{ __('commerce.next_step') }}</strong>
                    @if($bankReady)<p>{{ __('commerce.bank_next_step') }}</p>@endif
                    @if(!$bankReady)<p>{{ __('commerce.deferred_notice') }}</p>@endif
                </div>
                <button class="button-primary checkout-submit" type="submit" form="checkout-form" data-busy-label="{{ __('commerce.validating') }}"><span class="checkout-action-label"><span class="checkout-label-full" data-submit-label>{{ __('commerce.confirm_order_action') }}</span><span class="checkout-label-mobile" data-submit-label>{{ __('commerce.confirm_order_mobile') }}</span></span><strong>{{ \App\Services\Catalogue::money($quote['total_cents'], $locale) }}</strong></button>
                <p class="checkout-help">{{ __('commerce.validation_help') }}</p>
                <p class="checkout-return"><a href="{{ route('information', ['locale' => $locale, 'page' => 'retractation']) }}">{{ __('commerce.free_returns') }}</a></p>
                <p class="checkout-help"><a href="{{ route('information', ['locale' => $locale, 'page' => 'contact']) }}">{{ __('commerce.need_help') }}</a></p>
            </aside>
        </div>
        <x-purchase-help :locale="$locale" />
    </main>
</x-layout>
