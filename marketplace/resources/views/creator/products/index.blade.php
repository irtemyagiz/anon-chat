<x-layouts.creator :title="__('Ürünlerim')">
    @if ($products->isEmpty())
        <x-empty-state icon="cube" :title="__('Henüz ürün eklemedin')" :description="__('İlk modelini ekle: görseller, indirilebilir dosyalar ve isteğe bağlı 3D önizleme.')">
            <a href="{{ route('creator.products.create') }}" class="btn-primary"><x-icon name="upload" class="size-4"/>{{ __('Yeni ürün ekle') }}</a>
        </x-empty-state>
    @else
        <div class="mb-4 flex justify-end"><a href="{{ route('creator.products.create') }}" class="btn-primary"><x-icon name="upload" class="size-4"/>{{ __('Yeni ürün ekle') }}</a></div>
        <div class="card divide-y divide-line">
            @foreach ($products as $product)
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="size-16 shrink-0 overflow-hidden rounded-xl bg-surface-2">
                        @if ($product->cover_image_path)
                            <img src="{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url($product->cover_image_path) }}" alt="" class="size-full object-cover" loading="lazy">
                        @else
                            <div class="layer-lines flex size-full items-center justify-center text-subtle"><x-icon name="cube"/></div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('creator.products.edit', $product) }}" class="truncate font-semibold hover:underline">{{ $product->title }}</a>
                            <x-status-badge :status="$product->status"/>
                        </div>
                        <p class="mt-1 text-xs text-muted">
                            {{ $product->is_free ? __('Ücretsiz') : number_format($product->price_cents, 0, ',', '.').' POINT' }}
                            · {{ trans_choice(':count dosya|:count dosya', $product->files_count, ['count' => $product->files_count]) }}
                            · {{ trans_choice(':count satış|:count satış', $product->sales_count, ['count' => $product->sales_count]) }}
                            · {{ trans_choice(':count görüntülenme|:count görüntülenme', $product->views_count, ['count' => $product->views_count]) }}
                        </p>
                        @if ($product->moderation_note)<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $product->moderation_note }}</p>@endif
                    </div>
                    <div class="flex shrink-0 gap-2">
                        @if ($product->isPublished())
                            <a href="{{ route('products.show', $product) }}" class="btn-ghost px-3 py-1.5">{{ __('Görüntüle') }}</a>
                        @endif
                        <a href="{{ route('creator.products.edit', $product) }}" class="btn-secondary px-3 py-1.5">{{ __('Düzenle') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</x-layouts.creator>
