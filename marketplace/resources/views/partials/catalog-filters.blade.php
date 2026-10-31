{{-- Ortak filtre çubuğu. $action: form adresi, $filters: aktif filtreler, $categories (opsiyonel), $formats (opsiyonel) --}}
@php
    $advancedKeys = ['format', 'lisans', 'teknoloji', 'min', 'max'];
    $advancedActive = count(array_intersect(array_keys(array_filter($filters)), $advancedKeys));
@endphp
<form action="{{ $action }}" method="GET" class="card mb-6 p-3">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto_auto]">
        @isset($categories)
            <label class="relative">
                <span class="sr-only">{{ __('Ara') }}</span>
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle"/>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Model, etiket veya tasarımcı') }}" class="input pl-9">
            </label>
            <select name="kategori" class="input" aria-label="{{ __('Kategori') }}">
                <option value="">{{ __('Tüm kategoriler') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->slug }}" @selected(($filters['kategori'] ?? '') === $category->slug)>{{ $category->localizedName() }}</option>
                @endforeach
            </select>
        @endisset
        <select name="fiyat" class="input" aria-label="{{ __('Fiyat') }}">
            <option value="">{{ __('Ücretli ve ücretsiz') }}</option>
            <option value="ucretsiz" @selected(($filters['fiyat'] ?? '') === 'ucretsiz')>{{ __('Yalnızca ücretsiz') }}</option>
            <option value="ucretli" @selected(($filters['fiyat'] ?? '') === 'ucretli')>{{ __('Yalnızca ücretli') }}</option>
        </select>
        <select name="sirala" class="input" aria-label="{{ __('Sıralama') }}">
            @foreach (\App\Services\Catalog\ProductSearch::SORTS as $value => $label)
                <option value="{{ $value }}" @selected(($filters['sirala'] ?? 'yeni') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
        <button class="btn-primary">{{ __('Uygula') }}</button>
    </div>

    @if (filled($filters['etiket'] ?? null))
        <input type="hidden" name="etiket" value="{{ $filters['etiket'] }}">
    @endif

    <details class="group mt-3" @if ($advancedActive) open @endif>
        <summary class="flex cursor-pointer list-none items-center gap-2 text-sm font-semibold text-muted hover:text-ink">
            <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180"/>
            {{ __('Gelişmiş filtreler') }}
            @if ($advancedActive)<span class="chip">{{ $advancedActive }}</span>@endif
        </summary>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @isset($formats)
                <select name="format" class="input" aria-label="{{ __('Dosya formatı') }}">
                    <option value="">{{ __('Tüm dosya formatları') }}</option>
                    @foreach ($formats as $extension)
                        <option value="{{ $extension }}" @selected(($filters['format'] ?? '') === $extension)>{{ strtoupper($extension) }}</option>
                    @endforeach
                </select>
            @endisset
            <select name="teknoloji" class="input" aria-label="{{ __('Baskı teknolojisi') }}">
                <option value="">{{ __('Tüm baskı teknolojileri') }}</option>
                @foreach (\App\Services\Catalog\ProductSearch::TECHNOLOGIES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['teknoloji'] ?? '') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
            <label class="relative">
                <span class="sr-only">{{ __('En düşük fiyat (USD)') }}</span>
                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-subtle">$</span>
                <input name="min" inputmode="decimal" value="{{ $filters['min'] ?? '' }}" placeholder="{{ __('En düşük') }}" class="input pl-7" pattern="[0-9]+([.,][0-9]{1,2})?">
            </label>
            <label class="relative">
                <span class="sr-only">{{ __('En yüksek fiyat (USD)') }}</span>
                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-subtle">$</span>
                <input name="max" inputmode="decimal" value="{{ $filters['max'] ?? '' }}" placeholder="{{ __('En yüksek') }}" class="input pl-7" pattern="[0-9]+([.,][0-9]{1,2})?">
            </label>
            <label class="flex items-center gap-2 rounded-xl border border-line px-3 text-sm">
                <input type="checkbox" name="lisans" value="ticari" @checked(($filters['lisans'] ?? '') === 'ticari') class="accent-katman-600">
                {{ __('Ticari kullanım lisansı olanlar') }}
            </label>
        </div>
        <p class="mt-2 text-xs text-subtle">{{ __('Fiyat aralığı, tasarımcının belirlediği USD fiyata göre uygulanır.') }}</p>
    </details>
</form>
