<x-layouts.admin :title="__('Ürün moderasyonu')">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        @include('admin.partials.status-tabs', ['route' => 'admin.products.index', 'current' => $status, 'tabs' => ['pending_review' => __('İncelemede'), 'published' => __('Yayında'), 'rejected' => __('Reddedilen'), 'unpublished' => __('Yayında değil'), 'draft' => __('Taslak'), 'tumu' => __('Tümü')]])
        <form method="GET" class="mb-4 flex gap-2"><input type="hidden" name="durum" value="{{ $status }}"><input name="q" value="{{ request('q') }}" class="input py-1.5" placeholder="{{ __('Başlık ara') }}"><button class="btn-secondary py-1.5">{{ __('Ara') }}</button></form>
    </div>
    @if ($products->isEmpty())
        <x-empty-state icon="cube" :title="__('Bu durumda ürün yok')"/>
    @else
        <div class="card divide-y divide-line">
            @foreach ($products as $product)
                <a href="{{ route('admin.products.show', $product) }}" class="flex items-center gap-4 p-4 hover:bg-surface-2">
                    <div class="size-14 shrink-0 overflow-hidden rounded-xl bg-surface-2">
                        @if ($product->cover_image_path)<img src="{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url($product->cover_image_path) }}" alt="" class="size-full object-cover" loading="lazy">@endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2"><span class="truncate font-semibold">{{ $product->title }}</span><x-status-badge :status="$product->status"/>@if ($product->is_featured)<span class="chip">{{ __('Öne çıkan') }}</span>@endif</div>
                        <p class="mt-1 truncate text-xs text-muted">{{ $product->creator->creatorProfile?->store_name }} · {{ $product->category->localizedName() }} · {{ $product->is_free ? __('Ücretsiz') : $product->price_cents.' POINT' }} · {{ trans_choice(':count dosya|:count dosya', $product->files_count, ['count' => $product->files_count]) }}</p>
                    </div>
                    <span class="hidden text-xs text-subtle sm:block">{{ $product->updated_at->diffForHumans() }}</span>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</x-layouts.admin>
