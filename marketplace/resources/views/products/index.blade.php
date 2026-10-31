@php
    $heading = match (true) {
        filled($filters['q'] ?? null) => __('“:q” için sonuçlar', ['q' => $filters['q']]),
        ($filters['fiyat'] ?? null) === 'ucretsiz' => __('Ücretsiz modeller'),
        ($filters['sirala'] ?? null) === 'populer' => __('Popüler modeller'),
        ($filters['sirala'] ?? null) === 'yeni' => __('Yeni modeller'),
        default => __('Tüm modeller'),
    };
@endphp
<x-layouts.app :title="$heading">
    <div class="container-page py-12">
        <x-section-heading :eyebrow="__('Katalog')" :title="$heading"/>
        @include('partials.catalog-filters', ['action' => route('products.index'), 'filters' => $filters, 'categories' => $categories])

        @if ($products->isEmpty())
            @if (array_filter($filters))
                <x-empty-state icon="search" :title="__('Bu filtrelerle eşleşen model bulunamadı')" :description="__('Filtreleri değiştirmeyi veya daha genel bir arama yapmayı deneyin.')">
                    <a href="{{ route('products.index') }}" class="btn-secondary">{{ __('Filtreleri temizle') }}</a>
                </x-empty-state>
            @else
                <x-empty-state icon="sparkles" :title="__('Katalog ilk tasarımlarını bekliyor')" :description="__('Onaylanan ilk modeller burada listelenecek.')">
                    <a href="{{ route('creator.apply') }}" class="btn-primary">{{ __('Tasarımını yükle') }}</a>
                </x-empty-state>
            @endif
        @else
            <p class="mb-4 text-sm text-muted">{{ trans_choice(':count model bulundu|:count model bulundu', $products->total(), ['count' => $products->total()]) }}</p>
            <x-product-grid :products="$products"/>
            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </div>
</x-layouts.app>
