<x-layouts.account :title="__('Kütüphanem')">
    @if ($entitlements->isEmpty())
        <x-empty-state icon="download" :title="__('Kütüphanen boş')" :description="__('Satın aldığın veya ücretsiz indirdiğin modeller burada listelenir; dosyalarına buradan her zaman ulaşabilirsin.')">
            <a href="{{ route('products.index') }}" class="btn-primary">{{ __('Modelleri keşfet') }}</a>
        </x-empty-state>
    @else
        <div class="space-y-3">
            @foreach ($entitlements as $entitlement)
                @php $product = $entitlement->product; @endphp
                <div class="card flex flex-col gap-4 p-4 sm:flex-row">
                    <div class="size-20 shrink-0 overflow-hidden rounded-xl bg-surface-2">
                        @if ($product->cover_image_path)<img src="{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url(\App\Services\Files\ImageProcessor::variantPath($product->cover_image_path, 480)) }}" alt="" class="size-full object-cover" loading="lazy">@endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($product->isPublished())<a href="{{ route('products.show', $product) }}" class="font-semibold hover:underline">{{ $product->title }}</a>@else<span class="font-semibold">{{ $product->title }}</span><span class="chip">{{ __('Satıştan kaldırıldı') }}</span>@endif
                        </div>
                        <p class="mt-0.5 text-xs text-muted">{{ $product->creator->creatorProfile?->store_name }} · {{ $entitlement->source->value === 'free' ? __('Ücretsiz') : __('Satın alındı') }} · {{ $entitlement->granted_at->format('d.m.Y') }}</p>
                        <ul class="mt-3 space-y-1.5">
                            @forelse ($product->files as $file)
                                <li class="flex items-center justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate">{{ $file->original_name }} <span class="text-xs text-subtle">· {{ Number::fileSize($file->size_bytes) }}</span></span>
                                    @if ($file->isDownloadable())
                                        <a href="{{ \App\Http\Controllers\DownloadController::temporaryUrl($file) }}" class="btn-secondary shrink-0 px-3 py-1"><x-icon name="download" class="size-4"/>{{ __('İndir') }}</a>
                                    @else
                                        <span class="chip shrink-0">{{ __('Güvenlik taraması') }}</span>
                                    @endif
                                </li>
                            @empty
                                <li class="text-xs text-muted">{{ __('Dosya bulunmuyor.') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $entitlements->links() }}</div>
    @endif
</x-layouts.account>
