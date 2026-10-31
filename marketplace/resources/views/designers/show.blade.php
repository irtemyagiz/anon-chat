<x-layouts.app :title="$profile->store_name" :description="Str::limit($profile->description, 155)">
    <div class="border-b border-line">
        <div class="layer-lines h-40 sm:h-56" @if ($profile->cover_path) style="background-image:url('{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url($profile->cover_path) }}');background-size:cover;background-position:center" @endif></div>
        <div class="container-page -mt-10 flex flex-col gap-4 pb-8 sm:flex-row sm:items-end">
            <span class="flex size-24 items-center justify-center rounded-3xl border-4 border-canvas bg-katman-100 font-display text-3xl font-extrabold text-katman-800 dark:bg-katman-900 dark:text-katman-200">{{ mb_strtoupper(mb_substr($profile->store_name, 0, 1)) }}</span>
            <div class="flex-1">
                <h1 class="text-3xl font-extrabold">{{ $profile->store_name }}</h1>
                <p class="text-muted">{{ $profile->designer_name }} · {{ trans_choice(':count takipçi|:count takipçi', $followersCount, ['count' => $followersCount]) }}</p>
            </div>
        </div>
        @if ($profile->description)
            <div class="container-page pb-8"><p class="max-w-3xl text-muted">{{ $profile->description }}</p></div>
        @endif
    </div>
    <div class="container-page py-10">
        @if ($products->isEmpty())
            <x-empty-state :title="__('Bu mağazada henüz model yok')" :description="__('Tasarımcı ilk modelini yayınladığında burada görünecek.')"/>
        @else
            <x-product-grid :products="$products"/>
            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </div>
</x-layouts.app>
