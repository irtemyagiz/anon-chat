<x-layouts.app>
    <x-slot:head>
        <x-structured-data :data="[
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => route('home'),
            'potentialAction' => ['@type' => 'SearchAction', 'target' => route('products.index').'?q={search_term_string}', 'query-input' => 'required name=search_term_string'],
        ]"/>
        <x-structured-data :data="['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => config('app.name'), 'url' => route('home'), 'logo' => asset('favicon.svg')]"/>
    </x-slot:head>
    {{-- 1. Hero + 2. Gelişmiş arama --}}
    <section class="relative overflow-hidden border-b border-line">
        <div class="layer-lines pointer-events-none absolute inset-0 opacity-60 [mask-image:radial-gradient(ellipse_at_70%_40%,black,transparent_70%)]"></div>
        <div class="container-page relative grid items-center gap-12 py-16 lg:grid-cols-[1.1fr_0.9fr] lg:py-24">
            <div>
                <p class="eyebrow">{{ __('3D baskı tasarım pazaryeri') }}</p>
                <h1 class="mt-4 text-4xl leading-[1.05] font-extrabold text-ink sm:text-5xl lg:text-6xl">
                    {{ __('Fikirden nesneye,') }}<br><span class="text-katman-600 dark:text-katman-400">{{ __('katman katman.') }}</span>
                </h1>
                <p class="mt-5 max-w-xl text-lg text-muted">{{ __('Bağımsız tasarımcıların baskıya hazır modellerini keşfet. Puanla satın al, güvenle indir, yazıcında hayata geçir.') }}</p>

                <form action="{{ route('products.index') }}" method="GET" role="search" class="card mt-8 flex flex-col gap-2 p-2 sm:flex-row">
                    <label class="relative flex-1">
                        <span class="sr-only">{{ __('Ara') }}</span>
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-subtle"/>
                        <input type="search" name="q" placeholder="{{ __('Ne basmak istiyorsun?') }}" class="input border-0 bg-transparent py-3 pl-11 text-base shadow-none focus:ring-0">
                    </label>
                    <select name="kategori" class="input border-0 bg-surface-2 py-3 sm:w-40" aria-label="{{ __('Kategori') }}">
                        <option value="">{{ __('Tüm kategoriler') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}">{{ $category->localizedName() }}</option>
                        @endforeach
                    </select>
                    <select name="fiyat" class="input border-0 bg-surface-2 py-3 sm:w-28" aria-label="{{ __('Fiyat') }}">
                        <option value="">{{ __('Tümü') }}</option>
                        <option value="ucretsiz">{{ __('Ücretsiz') }}</option>
                        <option value="ucretli">{{ __('Ücretli') }}</option>
                    </select>
                    <button class="btn-primary px-6 py-3">{{ __('Ara') }}</button>
                </form>
            </div>

            <div class="relative mx-auto hidden w-full max-w-md lg:block" aria-hidden="true">
                {{-- Dekoratif katman yığını: baskı tablası üzerinde yükselen katmanlar --}}
                <div class="relative aspect-square">
                    @foreach ([['w-[88%]', 'bottom-[8%]', 'bg-katman-800 dark:bg-katman-700'], ['w-[78%]', 'bottom-[24%]', 'bg-katman-600 dark:bg-katman-500'], ['w-[66%]', 'bottom-[40%]', 'bg-katman-400'], ['w-[52%]', 'bottom-[56%]', 'bg-katman-200 dark:bg-katman-300'], ['w-[36%]', 'bottom-[72%]', 'bg-filament-400']] as [$width, $bottom, $color])
                        <div class="absolute left-1/2 h-[13%] -translate-x-1/2 rounded-2xl shadow-lg {{ $width }} {{ $bottom }} {{ $color }}"></div>
                    @endforeach
                    <div class="absolute bottom-0 left-1/2 h-[5%] w-full -translate-x-1/2 rounded-full bg-ink/10 blur-md"></div>
                </div>
            </div>
        </div>
    </section>

    @if ($banners->isNotEmpty())
        <section class="container-page pt-10" x-data="{ active: 0, count: {{ $banners->count() }} }" x-init="count > 1 && setInterval(() => active = (active + 1) % count, 6000)">
            <div class="relative overflow-hidden rounded-[2rem]">
                @foreach ($banners as $index => $banner)
                    <div x-show="active === {{ $index }}" x-transition.opacity @if ($index > 0) x-cloak @endif class="relative flex min-h-48 items-end bg-katman-800 p-6 text-white sm:p-10" @if ($banner->image_path) style="background-image: linear-gradient(to top, rgb(3 42 40 / .85), rgb(3 42 40 / .2)), url('{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url($banner->image_path) }}'); background-size: cover; background-position: center" @endif>
                        <div class="max-w-xl">
                            <h2 class="text-2xl font-extrabold sm:text-3xl">{{ $banner->title[app()->getLocale()] ?? $banner->title['tr'] }}</h2>
                            @if ($banner->subtitle)<p class="mt-2 text-katman-100">{{ $banner->subtitle[app()->getLocale()] ?? $banner->subtitle['tr'] ?? '' }}</p>@endif
                            @if ($banner->link_url)<a href="{{ $banner->link_url }}" class="btn-accent mt-4">{{ $banner->link_label['tr'] ?? __('İncele') }}</a>@endif
                        </div>
                    </div>
                @endforeach
                @if ($banners->count() > 1)
                    <div class="absolute right-5 bottom-5 flex gap-1.5">
                        @foreach ($banners as $index => $banner)<button type="button" @click="active = {{ $index }}" :class="active === {{ $index }} ? 'bg-white' : 'bg-white/40'" class="size-2.5 rounded-full" aria-label="{{ $index + 1 }}"></button>@endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- 3. Kategori kartları --}}
    <section class="container-page py-16">
        <x-section-heading :eyebrow="__('Keşfet')" :title="__('Kategoriler')" :href="route('categories.index')"/>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($categories->take(12) as $category)
                <a href="{{ route('categories.show', $category) }}" class="card group flex flex-col justify-between gap-6 p-4 transition hover:border-katman-300 dark:hover:border-katman-700">
                    @if ($category->image_path)
                        <img src="{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url($category->image_path) }}" alt="" class="size-10 rounded-xl object-cover" loading="lazy">
                    @else
                        <span class="flex size-10 items-center justify-center rounded-xl bg-surface-2 text-katman-700 transition group-hover:bg-katman-600 group-hover:text-white dark:text-katman-300">
                            <x-icon name="layers" class="size-5"/>
                        </span>
                    @endif
                    <span>
                        <span class="block text-sm font-bold text-ink">{{ $category->localizedName() }}</span>
                        <span class="text-xs text-subtle">{{ trans_choice(':count model|:count model', $category->products_count, ['count' => $category->products_count]) }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($catalogIsEmpty)
        {{-- Katalog henüz boş: örnek ürün göstermek yerine dürüst bir açılış ekranı --}}
        <section class="container-page">
            <x-empty-state icon="sparkles" :title="__('Katalog ilk tasarımlarını bekliyor')" :description="__('Katman yeni açıldı. İlk modeller onaylandığında öne çıkan, popüler, yeni ve ücretsiz tasarımlar burada listelenecek.')">
                <a href="{{ route('creator.apply') }}" class="btn-primary"><x-icon name="upload" class="size-4"/>{{ __('İlk tasarımı sen yükle') }}</a>
                <a href="{{ route('categories.index') }}" class="btn-secondary">{{ __('Kategorilere göz at') }}</a>
            </x-empty-state>
        </section>
    @else
        {{-- 4–7. Öne çıkan, popüler, yeni ve ücretsiz modeller --}}
        @foreach ([
            'featured' => [__('Editörün seçimi'), __('Öne çıkan modeller'), route('products.index')],
            'popular' => [__('Topluluk favorisi'), __('Popüler tasarımlar'), route('products.index', ['sirala' => 'populer'])],
            'latest' => [__('Taze çıktı'), __('Yeni eklenen modeller'), route('products.index', ['sirala' => 'yeni'])],
            'free' => [__('Hemen indir'), __('Ücretsiz modeller'), route('products.index', ['fiyat' => 'ucretsiz'])],
        ] as $key => [$eyebrow, $title, $href])
            @if ($sections[$key]->isNotEmpty())
                <section class="container-page py-10">
                    <x-section-heading :eyebrow="$eyebrow" :title="$title" :href="$href"/>
                    <x-product-grid :products="$sections[$key]"/>
                </section>
            @endif
        @endforeach
    @endif

    {{-- 8. Popüler tasarımcılar --}}
    @if ($designers->isNotEmpty())
        <section class="container-page py-10">
            <x-section-heading :eyebrow="__('Topluluk')" :title="__('Popüler tasarımcılar')" :href="route('designers.index')"/>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($designers as $designer)
                    @include('designers.partials.card', ['profile' => $designer])
                @endforeach
            </div>
        </section>
    @endif

    {{-- 9. Koleksiyonlar --}}
    @if ($collections->isNotEmpty())
        <section class="container-page py-10">
            <x-section-heading :eyebrow="__('Topluluk seçkisi')" :title="__('Koleksiyonlar')"/>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($collections as $collection)
                    <a href="{{ route('collections.show', [$collection->user->username, $collection->slug]) }}" class="card p-5 transition hover:border-katman-300 dark:hover:border-katman-700">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-surface-2 text-katman-700 dark:text-katman-300"><x-icon name="layers"/></span>
                        <span class="mt-4 block font-bold">{{ $collection->name }}</span>
                        <span class="text-xs text-muted">{{ $collection->user->first_name }} · {{ trans_choice(':count model|:count model', $collection->products_count, ['count' => $collection->products_count]) }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 10. Creator olmaya davet --}}
    <section class="container-page py-16">
        <div class="relative overflow-hidden rounded-[2rem] bg-katman-900 px-6 py-12 text-white sm:px-12 dark:bg-katman-950 dark:ring-1 dark:ring-katman-900">
            <div class="layer-lines pointer-events-none absolute inset-y-0 right-0 w-2/3 opacity-40 [mask-image:linear-gradient(to_left,black,transparent)]"></div>
            <div class="relative max-w-2xl">
                <p class="text-xs font-semibold tracking-[0.14em] text-filament-300 uppercase">{{ __('Creator programı') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold sm:text-4xl">{{ __('Tasarımlarını Paylaş, Kazanmaya Başla') }}</h2>
                <p class="mt-4 text-katman-100">{{ __('Kendi mağazanı aç, fiyatını USD olarak belirle, her satıştan kazanç elde et. Ücretsiz modellerle topluluğunu büyüt.') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('creator.apply') }}" class="btn-accent px-6 py-3">{{ __('Creator ol') }} <x-icon name="arrow-right" class="size-4"/></a>
                </div>
            </div>
        </div>
    </section>

    {{-- 11. Platform avantajları --}}
    <section class="container-page py-6">
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([
                ['coins', __('Basit puan sistemi'), __('1 USD = 100 POINT. Bir kez yükle, istediğin modeli tek tıkla al. Türkiye\'de fiyatlar TL olarak gösterilir.')],
                ['shield', __('Güvenli indirme'), __('Ücretli dosyalar herkese açık tutulmaz. Satın aldığın dosyalara yalnızca sen, kısa süreli güvenli bağlantılarla erişirsin.')],
                ['store', __('Tasarımcıya adil kazanç'), __('Her satışta kazancın büyük kısmı tasarımcıya gider. Şeffaf komisyon, düzenli hakediş.')],
            ] as [$icon, $title, $text])
                <div class="card p-6">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-katman-50 text-katman-700 dark:bg-katman-950 dark:text-katman-300"><x-icon :name="$icon"/></span>
                    <h3 class="mt-4 font-bold text-ink">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-muted">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.app>
