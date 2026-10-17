@props(['name', 'type' => 'text', 'autocomplete', 'max', 'required' => true, 'label' => null, 'default' => ''])
<div class="checkout-field">
    <label for="checkout-{{ $name }}">{{ $label ?? __('commerce.'.$name) }}</label>
    <input id="checkout-{{ $name }}" type="{{ $type }}" name="{{ $name }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $max }}" value="{{ is_scalar(old($name, $default)) ? old($name, $default) : '' }}" @if($required) required @endif @if($errors->has($name)) aria-invalid="true" aria-describedby="checkout-{{ $name }}-error" @endif>
    @error($name)<p id="checkout-{{ $name }}-error" class="checkout-field-error">{{ $message }}</p>@enderror
</div>
