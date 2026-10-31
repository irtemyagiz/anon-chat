<x-layouts.account :title="__('Favorilerim')">
    @if ($products->isEmpty())
        <x-empty-state icon="sparkles" :title="__('Henüz favorin yok')" :description="__('Beğendiğin modelleri ürün sayfasındaki “Favorilere ekle” ile buraya kaydedebilirsin.')"/>
    @else
        <x-product-grid :products="$products"/>
        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</x-layouts.account>
