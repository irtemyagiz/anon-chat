<x-layouts.app :title="$category->localizedName()" :description="$category->meta_description[app()->getLocale()] ?? null">
    <div class="container-page py-12">
        <nav class="mb-4 flex items-center gap-2 text-sm text-muted" aria-label="{{ __('Sayfa yolu') }}">
            <a href="{{ route('home') }}" class="hover:text-ink">{{ __('Ana sayfa') }}</a> /
            <a href="{{ route('categories.index') }}" class="hover:text-ink">{{ __('Kategoriler') }}</a>
            @if ($category->parent) / <a href="{{ route('categories.show', $category->parent) }}" class="hover:text-ink">{{ $category->parent->localizedName() }}</a>@endif
        </nav>
        <x-section-heading :eyebrow="__('Kategori')" :title="$category->localizedName()"/>
        @if ($category->children->isNotEmpty())
            <div class="mb-6 flex flex-wrap gap-2">
                @foreach ($category->children as $child)
                    <a href="{{ route('categories.show', $child) }}" class="chip hover:text-ink">{{ $child->localizedName() }}</a>
                @endforeach
            </div>
        @endif
        @include('partials.catalog-filters', ['action' => route('categories.show', $category), 'filters' => $filters])

        @if ($products->isEmpty())
            <x-empty-state icon="layers" :title="__('Bu kategoride henüz model yok')" :description="__('Bu kategoriye ilk tasarımı yükleyen sen ol.')">
                <a href="{{ route('upload') }}" class="btn-primary"><x-icon name="upload" class="size-4"/>{{ __('Tasarım yükle') }}</a>
                <a href="{{ route('categories.index') }}" class="btn-secondary">{{ __('Diğer kategoriler') }}</a>
            </x-empty-state>
        @else
            <x-product-grid :products="$products"/>
            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </div>
</x-layouts.app>
