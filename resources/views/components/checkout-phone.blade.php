@php($phoneCountries = app(\App\Services\InternationalPhone::class)->countries(app()->getLocale()))
@php($phoneRegion = is_string(old('phone_region')) ? old('phone_region') : 'FR')
<div class="checkout-field checkout-phone-field">
    <label for="checkout-phone">{{ __('commerce.phone') }}</label>
    <div class="checkout-phone-controls">
        <div class="checkout-phone-region">
            <label class="sr-only" for="checkout-phone-region">{{ __('commerce.phone_region') }}</label>
            <select id="checkout-phone-region" name="phone_region" autocomplete="off" @if($errors->has('phone_region')) aria-invalid="true" aria-describedby="checkout-phone-region-error" @endif>
                @foreach($phoneCountries as $region => $country)<option value="{{ $region }}" @selected($phoneRegion === $region)>{{ $country['name'] }} ({{ $country['prefix'] }})</option>@endforeach
            </select>
        </div>
        <input id="checkout-phone" type="tel" name="phone" autocomplete="tel-national" inputmode="tel" maxlength="30" value="{{ is_scalar(old('phone')) ? old('phone') : '' }}" aria-describedby="checkout-phone-help{{ $errors->has('phone') ? ' checkout-phone-error' : '' }}" @if($errors->has('phone')) aria-invalid="true" @endif>
    </div>
    <p id="checkout-phone-help" class="checkout-help">{{ __('commerce.phone_help') }}</p>
    @error('phone')<p id="checkout-phone-error" class="checkout-field-error">{{ $message }}</p>@enderror
    @error('phone_region')<p id="checkout-phone-region-error" class="checkout-field-error">{{ $message }}</p>@enderror
</div>
