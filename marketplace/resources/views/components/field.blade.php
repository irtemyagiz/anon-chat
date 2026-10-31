@props(['name', 'label', 'type' => 'text', 'hint' => null])
<div>
    <label for="{{ $name }}" class="label">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if ($type !== 'password') value="{{ old($name, $attributes->get('value')) }}" @endif
        {{ $attributes->except('value')->class(['input', 'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $errors->has($name)]) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @if ($hint && ! $errors->has($name))<p class="mt-1.5 text-xs text-subtle">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $name }}-error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
</div>
