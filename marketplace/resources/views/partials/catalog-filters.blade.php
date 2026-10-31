{{-- Ortak filtre çubuğu. $action: form adresi, $filters: aktif filtreler, $categories (opsiyonel) --}}
<form action="{{ $action }}" method="GET" class="card mb-6 grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto_auto]">
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
</form>
