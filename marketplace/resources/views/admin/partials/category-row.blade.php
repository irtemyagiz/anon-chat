<form method="POST" action="{{ route('admin.categories.update', $category) }}" class="grid items-center gap-2 p-3 sm:grid-cols-[1fr_1fr_1fr_5rem_auto_auto]" style="padding-left: {{ 0.75 + $depth * 1.5 }}rem">
    @csrf @method('PUT')
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
    <button class="btn-secondary px-3 py-1.5 text-xs">{{ __('Kaydet') }}</button>
</form>
