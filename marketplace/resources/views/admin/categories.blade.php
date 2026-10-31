<x-layouts.admin :title="__('Kategoriler')">
    <section class="card mb-6 p-5">
        <h2 class="font-bold">{{ __('Yeni kategori') }}</h2>
        <form method="POST" action="{{ route('admin.categories.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <input name="name_tr" class="input" placeholder="{{ __('Ad (TR)') }}" required>
            <input name="name_en" class="input" placeholder="{{ __('Ad (EN)') }}">
            <select name="parent_id" class="input"><option value="">{{ __('Üst kategori yok') }}</option>@foreach ($parents as $parent)<option value="{{ $parent->id }}">{{ $parent->localizedName() }}</option>@endforeach</select>
            <button class="btn-primary">{{ __('Ekle') }}</button>
            <input name="slug" class="input" placeholder="{{ __('SEO adresi (isteğe bağlı)') }}">
            <input name="meta_description_tr" class="input sm:col-span-2" placeholder="{{ __('Meta açıklama (isteğe bağlı)') }}">
            <input name="sort_order" type="number" class="input" placeholder="{{ __('Sıra') }}">
        </form>
    </section>

    @php
        $render = function ($parentId, $depth) use (&$render, $categories, $parents) {
            foreach ($categories->get($parentId, collect()) as $category) {
                echo view('admin.partials.category-row', ['category' => $category, 'depth' => $depth, 'parents' => $parents])->render();
                $render($category->id, $depth + 1);
            }
        };
    @endphp
    <div class="card divide-y divide-line">
        @php $render(0, 0); @endphp
    </div>
</x-layouts.admin>
