{{-- Canlı önerili arama kutusu. $formClass: form sınıfları, $inputClass: giriş sınıfları, $placeholder --}}
<form action="{{ route('products.index') }}" method="GET" role="search" class="{{ $formClass }}"
      x-data="searchSuggest(@js(route('search.suggestions')), @js((string) request('q')))"
      @submit="submit($event)" @click.outside="close()" @keydown.escape="close()">
    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle"/>
    <input type="search" name="q" x-model="q" @input="input()" @focus="q.trim().length >= 2 && items.length && (open = true)"
           @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
           placeholder="{{ $placeholder }}" class="{{ $inputClass }}" aria-label="{{ __('Ara') }}" autocomplete="off"
           role="combobox" aria-autocomplete="list" :aria-expanded="open.toString()">
    <div x-cloak x-show="open" x-transition.opacity class="absolute inset-x-0 top-full z-50 mt-2 max-h-[70vh] overflow-y-auto rounded-2xl border border-line bg-surface p-2 text-sm shadow-xl" role="listbox">
        <template x-if="items.length === 0">
            <p class="px-3 py-4 text-center text-muted">{{ __('Eşleşen sonuç yok. Enter ile tüm katalogda arayın.') }}</p>
        </template>
        <template x-if="groups.products.length">
            <div>
                <p class="px-3 pt-2 pb-1 text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('Modeller') }}</p>
                <template x-for="item in groups.products" :key="item.url">
                    <a :href="item.url" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-surface-2" :class="indexOf(item) === active && 'bg-surface-2'" role="option">
                        <span class="size-9 shrink-0 overflow-hidden rounded-lg bg-surface-2">
                            <template x-if="item.image"><img :src="item.image" alt="" class="size-full object-cover"></template>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-ink" x-text="item.title"></span>
                            <span class="block truncate text-xs text-muted" x-text="item.creator"></span>
                        </span>
                    </a>
                </template>
            </div>
        </template>
        <template x-if="groups.designers.length">
            <div>
                <p class="px-3 pt-3 pb-1 text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('Tasarımcılar') }}</p>
                <template x-for="item in groups.designers" :key="item.url">
                    <a :href="item.url" class="flex items-center gap-2 rounded-xl px-3 py-2 hover:bg-surface-2" :class="indexOf(item) === active && 'bg-surface-2'" role="option">
                        <x-icon name="store" class="size-4 text-subtle"/><span x-text="item.name"></span>
                    </a>
                </template>
            </div>
        </template>
        <template x-if="groups.categories.length">
            <div>
                <p class="px-3 pt-3 pb-1 text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('Kategoriler') }}</p>
                <template x-for="item in groups.categories" :key="item.url">
                    <a :href="item.url" class="flex items-center gap-2 rounded-xl px-3 py-2 hover:bg-surface-2" :class="indexOf(item) === active && 'bg-surface-2'" role="option">
                        <x-icon name="layers" class="size-4 text-subtle"/><span x-text="item.name"></span>
                    </a>
                </template>
            </div>
        </template>
        <template x-if="groups.tags.length">
            <div class="flex flex-wrap gap-2 px-3 pt-3 pb-2">
                <template x-for="item in groups.tags" :key="item.url">
                    <a :href="item.url" class="chip hover:text-ink" :class="indexOf(item) === active && 'ring-2 ring-katman-500'" x-text="'#' + item.name" role="option"></a>
                </template>
            </div>
        </template>
    </div>
</form>
