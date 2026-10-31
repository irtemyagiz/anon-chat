@php $disk = Storage::disk(config('marketplace.storage.public_media_disk')); @endphp
<x-layouts.admin :title="__('Ana sayfa bannerları')">
    <p class="mb-4 text-sm text-muted">{{ __('Aktif bannerlar ana sayfada arama alanının altında kaydırmalı olarak gösterilir.') }}</p>
    @foreach ([...$banners, null] as $banner)
        <form method="POST" action="{{ $banner ? route('admin.content.banners.update', $banner) : route('admin.content.banners.store') }}" enctype="multipart/form-data" class="card mb-4 grid gap-3 p-5 sm:grid-cols-2">
            @csrf @if ($banner) @method('PUT') @endif
            <p class="font-bold sm:col-span-2">{{ $banner ? __('Banner #:id', ['id' => $banner->id]) : __('Yeni banner') }}</p>
            <input name="title_tr" value="{{ $banner?->title['tr'] }}" class="input" placeholder="{{ __('Başlık (TR)') }}" required>
            <input name="title_en" value="{{ $banner?->title['en'] }}" class="input" placeholder="{{ __('Başlık (EN)') }}">
            <input name="subtitle_tr" value="{{ $banner?->subtitle['tr'] ?? '' }}" class="input sm:col-span-2" placeholder="{{ __('Alt başlık (isteğe bağlı)') }}">
            <input name="link_url" value="{{ $banner?->link_url }}" class="input" placeholder="{{ __('/modeller?fiyat=ucretsiz veya https://…') }}">
            <input name="link_label_tr" value="{{ $banner?->link_label['tr'] ?? '' }}" class="input" placeholder="{{ __('Buton metni') }}">
            <label class="text-xs text-muted">{{ __('Başlangıç') }}<input type="datetime-local" name="starts_at" value="{{ $banner?->starts_at?->format('Y-m-d\TH:i') }}" class="input mt-1"></label>
            <label class="text-xs text-muted">{{ __('Bitiş') }}<input type="datetime-local" name="ends_at" value="{{ $banner?->ends_at?->format('Y-m-d\TH:i') }}" class="input mt-1"></label>
            <div class="flex items-center gap-3">
                @if ($banner?->image_path)<img src="{{ $disk->url($banner->image_path) }}" alt="" class="h-12 rounded-lg object-cover">@endif
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm">
            </div>
            <div class="flex items-center gap-3">
                <input name="sort_order" type="number" value="{{ $banner?->sort_order ?? 0 }}" class="input w-24" aria-label="{{ __('Sıra') }}">
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($banner?->is_active ?? true) class="accent-katman-600">{{ __('Aktif') }}</label>
                <button class="btn-primary px-4 py-2">{{ __('Kaydet') }}</button>
                @if ($banner)<button type="submit" form="delete-banner-{{ $banner->id }}" class="btn-ghost text-red-600">{{ __('Sil') }}</button>@endif
            </div>
        </form>
        @if ($banner)<form id="delete-banner-{{ $banner->id }}" method="POST" action="{{ route('admin.content.banners.destroy', $banner) }}" class="hidden">@csrf @method('DELETE')</form>@endif
    @endforeach
</x-layouts.admin>
