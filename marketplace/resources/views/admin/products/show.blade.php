@php $mediaDisk = Storage::disk(config('marketplace.storage.public_media_disk')); @endphp
<x-layouts.admin :title="$product->title">
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-status-badge :status="$product->status"/>
        <span class="text-sm text-muted">{{ $product->creator->creatorProfile?->store_name }} · <a href="{{ route('admin.users.show', $product->creator) }}" class="hover:underline">{{ $product->creator->email }}</a></span>
        @if ($product->isPublished())<a href="{{ route('products.show', $product) }}" class="btn-ghost px-3 py-1.5" target="_blank" rel="noopener">{{ __('Sitede görüntüle') }}</a>@endif
    </div>

    <div class="card mb-6 p-5" x-data="{ decision: null }">
        <div class="flex flex-wrap gap-2">
            @if ($product->status->value === 'pending_review')
                <form method="POST" action="{{ route('admin.products.decide', $product) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn-primary">{{ __('Onayla ve yayınla') }}</button></form>
                <button type="button" @click="decision = 'reject'" class="btn-secondary">{{ __('Reddet') }}</button>
            @endif
            @if (in_array($product->status->value, ['published', 'pending_review'], true))
                <button type="button" @click="decision = 'unpublish'" class="btn-secondary">{{ __('Yayından kaldır') }}</button>
            @endif
            @if ($product->isPublished())
                <form method="POST" action="{{ route('admin.products.feature', $product) }}">@csrf<button class="btn-ghost">{{ $product->is_featured ? __('Öne çıkanlardan kaldır') : __('Öne çıkar') }}</button></form>
            @endif
        </div>
        <form x-show="decision" x-cloak method="POST" action="{{ route('admin.products.decide', $product) }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
            @csrf <input type="hidden" name="decision" :value="decision">
            <input name="note" class="input" required maxlength="255" placeholder="{{ __('Gerekçe (creator görür)') }}">
            <button class="btn-primary shrink-0">{{ __('Kaydet') }}</button>
        </form>
        @if ($product->moderation_note)<p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ __('Son not') }}: {{ $product->moderation_note }}</p>@endif
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card p-5">
            <h2 class="font-bold">{{ __('Görseller') }}</h2>
            <div class="mt-3 grid grid-cols-3 gap-2">
                @forelse ($product->images as $image)
                    <a href="{{ $mediaDisk->url($image->path) }}" target="_blank" rel="noopener" class="aspect-square overflow-hidden rounded-xl border border-line"><img src="{{ $mediaDisk->url($image->path) }}" alt="" class="size-full object-cover" loading="lazy"></a>
                @empty
                    <p class="col-span-3 text-sm text-muted">{{ __('Görsel yok.') }}</p>
                @endforelse
            </div>
            @if ($product->preview_model_path)<p class="mt-3 text-sm text-muted">{{ __('3D önizleme dosyası var') }} ({{ strtoupper(pathinfo($product->preview_model_path, PATHINFO_EXTENSION)) }})</p>@endif
        </section>
        <section class="card p-5">
            <h2 class="font-bold">{{ __('Dosyalar') }}</h2>
            <ul class="mt-3 divide-y divide-line text-sm">
                @forelse ($product->files as $file)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <span class="min-w-0 truncate @unless ($file->is_active) text-subtle line-through @endunless">{{ $file->original_name }} <span class="text-xs text-subtle">· {{ Number::fileSize($file->size_bytes) }} · {{ $file->mime_type }}</span></span>
                        <x-status-badge :status="$file->scan_status"/>
                    </li>
                @empty
                    <li class="py-2 text-muted">{{ __('Dosya yok.') }}</li>
                @endforelse
            </ul>
            <p class="mt-3 text-xs text-subtle">{{ __('SHA-256 özetleri ve dosya yolları yalnızca sunucuda tutulur; ürün dosyaları herkese açık değildir.') }}</p>
        </section>
        <section class="card p-5 xl:col-span-2">
            <h2 class="font-bold">{{ __('Bilgiler') }}</h2>
            <dl class="mt-3 grid gap-x-6 text-sm sm:grid-cols-2">
                @foreach ([__('Kategori') => $product->category->localizedName(), __('Fiyat') => $product->is_free ? __('Ücretsiz') : $product->price_cents.' POINT ($'.\App\Services\Points\PointConverter::formatCents($product->price_cents).')', __('Lisanslar') => $product->licenses->where('is_active', true)->map(fn ($l) => ($l->license->name['tr'] ?? $l->license->code).': '.$l->price_cents.' P')->implode(', '), __('Etiketler') => $product->tags->pluck('name')->implode(', '), __('Kısa açıklama') => $product->short_description] as $label => $value)
                    <div class="flex justify-between gap-4 border-b border-line py-2"><dt class="text-muted">{{ $label }}</dt><dd class="text-right">{{ $value ?: '—' }}</dd></div>
                @endforeach
            </dl>
            <div class="mt-4 rounded-xl bg-surface-2 p-4 text-sm whitespace-pre-line">{{ $product->description ?: '—' }}</div>
        </section>
    </div>
</x-layouts.admin>
