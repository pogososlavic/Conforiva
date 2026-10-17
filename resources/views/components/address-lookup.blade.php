@props(['locale', 'prefix' => ''])
@php($lookupId = 'checkout-'.$prefix.'address')
<div class="address-lookup" data-address-lookup data-field-prefix="{{ $prefix }}" data-url="{{ route('checkout.addresses', ['locale' => $locale]) }}" data-empty="{{ __('commerce.address_empty') }}" data-failure="{{ __('commerce.address_failure') }}" data-loading="{{ __('commerce.address_loading') }}" data-selected="{{ __('commerce.address_selected') }}" data-count="{{ __('commerce.address_count') }}" hidden>
                        <ul id="{{ $lookupId }}-suggestions" class="address-suggestions" data-address-results role="listbox" aria-label="{{ __('commerce.address_results') }}" hidden></ul>
                        <p id="{{ $lookupId }}-help" class="checkout-help">{{ __('commerce.address_hint') }}</p>
                        <div class="address-lookup-controls">
                            <label><input type="checkbox" data-address-auto checked> {{ __('commerce.address_auto') }}</label>
                            <button type="button" class="button-secondary" data-address-search>{{ __('commerce.address_search') }}</button>
                        </div>
                        <p class="checkout-help">{{ __('commerce.address_privacy') }}</p>
                        <p data-address-status role="status" aria-live="polite" aria-atomic="true"></p>
                    </div>
