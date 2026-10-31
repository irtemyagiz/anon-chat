<x-layouts.app :title="__('Kategoriler')">
    <div class="container-page py-12">
        <x-section-heading :eyebrow="__('Keşfet')" :title="__('Tüm kategoriler')"/>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($categories as $category)
                <div class="card p-5">
                    <a href="{{ route('categories.show', $category) }}" class="group flex items-center justify-between gap-4">
                        <span class="flex items-center gap-3">
                            <span class="flex size-11 items-center justify-center rounded-xl bg-surface-2 text-katman-700 transition group-hover:bg-katman-600 group-hover:text-white dark:text-katman-300"><x-icon name="layers"/></span>
                            <span>
                                <span class="block font-bold text-ink">{{ $category->localizedName() }}</span>
                                <span class="text-xs text-subtle">{{ trans_choice(':count model|:count model', $category->products_count, ['count' => $category->products_count]) }}</span>
                            </span>
                        </span>
                        <x-icon name="arrow-right" class="size-4 text-subtle transition group-hover:translate-x-0.5 group-hover:text-ink"/>
                    </a>
                    @if ($category->children->isNotEmpty())
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($category->children as $child)
                                <a href="{{ route('categories.show', $child) }}" class="chip hover:text-ink">{{ $child->localizedName() }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
