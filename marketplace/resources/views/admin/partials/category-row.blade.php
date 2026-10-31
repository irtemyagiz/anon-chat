<form method="POST" action="{{ route('admin.categories.update', $category) }}" enctype="multipart/form-data" class="p-3" style="padding-left: {{ 0.75 + $depth * 1.5 }}rem" x-data="{ details: false }">
    @csrf @method('PUT')
    <div class="grid items-center gap-2 sm:grid-cols-[1fr_1fr_1fr_5rem_auto_auto_auto]">
        <input name="name_tr" value="{{ $category->name['tr'] ?? '' }}" class="input py-1.5" aria-label="{{ __('Ad (TR)') }}" required>
        <input name="name_en" value="{{ $category->name['en'] ?? '' }}" class="input py-1.5" aria-label="{{ __('Ad (EN)') }}">
        <select name="parent_id" class="input py-1.5" aria-label="{{ __('Üst kategori') }}">
            <option value="">{{ __('Üst kategori yok') }}</option>
            @foreach ($parents as $parent)
                @unless ($parent->id === $category->id)<option value="{{ $parent->id }}" @selected($category->parent_id === $parent->id)>{{ $parent->localizedName() }}</option>@endunless
            @endforeach
        </select>
        <input name="sort_order" type="number" value="{{ $category->sort_order }}" class="input py-1.5" aria-label="{{ __('Sıra') }}">
        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="accent-katman-600">{{ __('Aktif') }} · {{ $category->products_count }}</label>
        <button type="button" @click="details = ! details" class="btn-ghost px-2 py-1.5 text-xs">{{ __('Detaylar') }}</button>
        <button class="btn-secondary px-3 py-1.5 text-xs">{{ __('Kaydet') }}</button>
    </div>
    <div x-show="details" x-cloak class="mt-3 grid gap-2 rounded-xl bg-surface-2 p-3 sm:grid-cols-2">
        <textarea name="description_tr" rows="2" class="input py-1.5" placeholder="{{ __('Açıklama (TR)') }}">{{ $category->description['tr'] ?? '' }}</textarea>
        <textarea name="description_en" rows="2" class="input py-1.5" placeholder="{{ __('Açıklama (EN)') }}">{{ $category->description['en'] ?? '' }}</textarea>
        <input name="meta_title_tr" value="{{ $category->meta_title['tr'] ?? '' }}" class="input py-1.5" placeholder="{{ __('Meta başlık (TR)') }}">
        <input name="meta_title_en" value="{{ $category->meta_title['en'] ?? '' }}" class="input py-1.5" placeholder="{{ __('Meta başlık (EN)') }}">
        <input name="meta_description_tr" value="{{ $category->meta_description['tr'] ?? '' }}" class="input py-1.5" placeholder="{{ __('Meta açıklama (TR)') }}">
        <input name="meta_description_en" value="{{ $category->meta_description['en'] ?? '' }}" class="input py-1.5" placeholder="{{ __('Meta açıklama (EN)') }}">
        <div class="flex items-center gap-3 sm:col-span-2">
            @if ($category->image_path)<img src="{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url($category->image_path) }}" alt="" class="h-10 rounded-lg"><label class="flex items-center gap-1 text-xs"><input type="checkbox" name="remove_image" value="1" class="accent-katman-600">{{ __('Görseli kaldır') }}</label>@endif
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="input py-1 file:mr-3 file:rounded-lg file:border-0 file:bg-surface file:px-3 file:py-1 file:text-xs">
        </div>
    </div>
</form>
