<x-layouts.app :title="$collection->name" :description="$collection->description">
    <div class="container-page py-12">
        <p class="eyebrow">{{ __('Koleksiyon') }}</p>
        <h1 class="mt-1 text-3xl font-extrabold">{{ $collection->name }}</h1>
        <p class="mt-2 text-muted">{{ __(':name tarafından', ['name' => $collection->user->first_name]) }} · {{ trans_choice(':count model|:count model', $products->total(), ['count' => $products->total()]) }}@unless ($collection->is_public) · {{ __('Gizli') }}@endunless</p>
        @if ($collection->description)<p class="mt-3 max-w-2xl text-muted">{{ $collection->description }}</p>@endif
        <div class="mt-8">
            @if ($products->isEmpty())
                <x-empty-state icon="layers" :title="__('Bu koleksiyon henüz boş')"/>
            @else
                <x-product-grid :products="$products"/>
                <div class="mt-8">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</x-layouts.app>
