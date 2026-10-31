@props(['name' => 'country_code', 'required' => false])
@php $localeIndex = app()->getLocale() === 'en' ? 1 : 0; @endphp
<div>
    <label for="{{ $name }}" class="label">{{ __('Ülke') }}</label>
    <select id="{{ $name }}" name="{{ $name }}" class="input" @required($required)>
        <option value="">{{ __('Seçin') }}</option>
        @foreach (config('countries') as $code => $names)
            <option value="{{ $code }}" @selected(old($name, request()->header('CF-IPCountry')) === $code)>{{ $names[$localeIndex] }}</option>
        @endforeach
    </select>
    <p class="mt-1.5 text-xs text-subtle">{{ __('Ödeme para birimi fatura ülkenize göre belirlenir (Türkiye: TL, diğer: USD).') }}</p>
    @error($name)<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
</div>
