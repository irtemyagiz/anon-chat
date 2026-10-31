@props(['product'])
<article {{ $attributes->class(['group card overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg']) }}>
    <a href="{{ route('products.show', $product) }}" class="block">
        <div class="relative aspect-[4/3] overflow-hidden bg-surface-2">
            @if ($product->cover_image_path)
                <img src="{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url($product->cover_image_path) }}" alt="{{ $product->title }}" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-[1.03]">
            @else
                <div class="layer-lines flex size-full items-center justify-center text-subtle"><x-icon name="cube" class="size-10"/></div>
            @endif
            @if ($product->is_free)
                <span class="absolute top-3 left-3 rounded-full bg-katman-600 px-2.5 py-1 text-xs font-bold text-white">{{ __('Ücretsiz') }}</span>
            @endif
        </div>
        <div class="p-4">
            <h3 class="line-clamp-1 font-display font-bold text-ink">{{ $product->title }}</h3>
            <p class="mt-0.5 line-clamp-1 text-xs text-muted">{{ $product->creator->creatorProfile?->store_name ?? $product->creator->username }}</p>
            <div class="mt-3 flex items-center justify-between gap-2">
                <x-price :cents="$product->price_cents" size="sm"/>
                <span class="inline-flex items-center gap-1 text-xs text-subtle"><x-icon name="heart" class="size-3.5"/>{{ $product->likes_count }}</span>
            </div>
        </div>
    </a>
</article>
