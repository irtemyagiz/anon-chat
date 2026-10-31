@php
    $user = auth()->user();
    $owns = $user?->ownsProduct($product) ?? false;
    $balance = $user ? app(\App\Services\Wallet\WalletService::class)->balanceOf($user) : 0;
    $isOwnProduct = $user?->id === $product->creator_id;
    $store = $product->creator->creatorProfile;
    $mediaDisk = Storage::disk(config('marketplace.storage.public_media_disk'));
    $activeLicenses = $product->licenses->where('is_active', true)->sortByDesc('is_default')->values();
    $files = $product->files()->where('is_active', true)->get();
@endphp
<x-layouts.app :title="$product->meta_title ?: $product->title" :description="$product->meta_description ?: $product->short_description">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->title,
            'description' => $product->short_description,
            'brand' => ['@type' => 'Brand', 'name' => $store?->store_name],
            'offers' => ['@type' => 'Offer', 'priceCurrency' => 'USD', 'price' => \App\Services\Points\PointConverter::formatCents($product->price_cents), 'availability' => 'https://schema.org/InStock'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    </x-slot:head>

    <div class="container-page py-8">
        <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="{{ __('Sayfa yolu') }}">
            <a href="{{ route('home') }}" class="hover:text-ink">{{ __('Ana sayfa') }}</a> /
            <a href="{{ route('categories.show', $product->category) }}" class="hover:text-ink">{{ $product->category->localizedName() }}</a> /
            <span class="text-ink">{{ $product->title }}</span>
        </nav>

        @error('purchase')
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ $message }}</div>
        @enderror

        <div class="grid gap-8 lg:grid-cols-[1.4fr_1fr]">
            {{-- Sol: görseller, 3D önizleme, video --}}
            @php
                $gallery = $product->images->map(fn ($image) => Storage::disk($image->disk)->url($image->path))
                    ->whenEmpty(fn ($urls) => $product->cover_image_path ? collect([$mediaDisk->url($product->cover_image_path)]) : $urls)
                    ->values();
                $previewUrl = $product->preview_model_path ? $mediaDisk->url($product->preview_model_path) : null;
                $videoUrl = $product->videoEmbedUrl();
            @endphp
            <div x-data="{ tab: 'images', active: 0, zoom: false, images: @js($gallery) }" @keydown.escape.window="zoom = false">
                @if ($previewUrl || $videoUrl)
                    <div class="mb-3 inline-flex rounded-xl border border-line bg-surface p-1 text-sm font-medium" role="tablist">
                        <button type="button" role="tab" @click="tab = 'images'" :class="tab === 'images' ? 'bg-surface-2 text-ink' : 'text-muted'" class="rounded-lg px-3 py-1.5">{{ __('Görseller') }}</button>
                        @if ($previewUrl)
                            <button type="button" role="tab" @click="tab = 'model'; $dispatch('open-viewer')" :class="tab === 'model' ? 'bg-surface-2 text-ink' : 'text-muted'" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5"><x-icon name="cube" class="size-4"/>{{ __('3D önizleme') }}</button>
                        @endif
                        @if ($videoUrl)
                            <button type="button" role="tab" @click="tab = 'video'" :class="tab === 'video' ? 'bg-surface-2 text-ink' : 'text-muted'" class="rounded-lg px-3 py-1.5">{{ __('Video') }}</button>
                        @endif
                    </div>
                @endif

                <div x-show="tab === 'images'">
                    <div class="card relative aspect-[4/3] overflow-hidden">
                        <template x-if="images.length">
                            <button type="button" @click="zoom = true" class="size-full cursor-zoom-in" aria-label="{{ __('Görseli büyüt') }}">
                                <img :src="images[active]" alt="{{ $product->title }}" class="size-full object-contain">
                            </button>
                        </template>
                        <template x-if="! images.length">
                            <div class="layer-lines flex size-full items-center justify-center text-subtle"><x-icon name="cube" class="size-16"/></div>
                        </template>
                    </div>
                    <div x-show="images.length > 1" class="mt-3 flex gap-2 overflow-x-auto">
                        <template x-for="(src, index) in images" :key="index">
                            <button type="button" @click="active = index" :class="active === index ? 'ring-2 ring-katman-500' : 'opacity-70'" class="size-20 shrink-0 overflow-hidden rounded-xl border border-line">
                                <img :src="src" alt="" class="size-full object-cover" loading="lazy">
                            </button>
                        </template>
                    </div>
                </div>

                @if ($previewUrl)
                    <div x-show="tab === 'model'" x-cloak x-data="modelViewer(@js($previewUrl), @js(strtolower(pathinfo($product->preview_model_path, PATHINFO_EXTENSION))))" @open-viewer.window="open()" data-viewer-root class="card relative aspect-[4/3] overflow-hidden bg-surface">
                        <div x-ref="canvas" class="absolute inset-0 [&_canvas]:size-full"></div>
                        <div x-show="state === 'loading'" class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-sm text-muted">
                            <x-icon name="cube" class="size-10 animate-pulse"/>
                            <span>{{ __('Model yükleniyor…') }} <span x-text="progress ? progress + '%' : ''"></span></span>
                        </div>
                        <div x-show="state === 'error'" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-muted">{{ __('3D önizleme yüklenemedi. Tarayıcınız WebGL desteklemiyor olabilir.') }}</div>
                        <div x-show="state === 'ready'" class="absolute inset-x-0 bottom-0 flex flex-wrap justify-center gap-1.5 p-3">
                            @foreach ([
                                ['viewer.center()', 'layers', __('Ortala')],
                                ['viewer.zoom(0.8)', 'search', __('Yakınlaştır')],
                                ['viewer.zoom(1.25)', 'search', __('Uzaklaştır')],
                                ['viewer.cycleLighting()', 'sun', __('Işık')],
                                ['viewer.cycleBackground()', 'moon', __('Arka plan')],
                                ['viewer.toggleGrid()', 'menu', __('Tabla')],
                                ['viewer.fullscreen()', 'arrow-right', __('Tam ekran')],
                            ] as [$action, $icon, $label])
                                <button type="button" @click="{{ $action }}" class="inline-flex items-center gap-1 rounded-lg bg-surface/90 px-2.5 py-1.5 text-xs font-medium text-ink shadow ring-1 ring-line backdrop-blur hover:bg-surface">{{ $label }}</button>
                            @endforeach
                        </div>
                        <p x-show="state === 'ready'" class="pointer-events-none absolute top-3 left-3 rounded-lg bg-surface/80 px-2 py-1 text-[0.7rem] text-muted backdrop-blur">{{ __('Döndürmek için sürükle · yakınlaştırmak için kaydır') }}</p>
                    </div>
                @endif

                @if ($videoUrl)
                    <div x-show="tab === 'video'" x-cloak class="card aspect-video overflow-hidden">
                        <template x-if="tab === 'video'">
                            <iframe src="{{ $videoUrl }}" class="size-full" title="{{ $product->title }}" allow="fullscreen; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" loading="lazy"></iframe>
                        </template>
                    </div>
                @endif

                {{-- Tam ekran görsel --}}
                <div x-show="zoom" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4" @click.self="zoom = false" role="dialog" aria-modal="true">
                    <img :src="images[active]" alt="{{ $product->title }}" class="max-h-full max-w-full object-contain">
                    <button type="button" @click="zoom = false" class="absolute top-4 right-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="{{ __('Kapat') }}"><x-icon name="x" class="size-6"/></button>
                    <button type="button" x-show="images.length > 1" @click="active = (active - 1 + images.length) % images.length" class="absolute left-4 rounded-full bg-white/10 px-3 py-2 text-white hover:bg-white/20" aria-label="{{ __('Önceki') }}">‹</button>
                    <button type="button" x-show="images.length > 1" @click="active = (active + 1) % images.length" class="absolute right-4 rounded-full bg-white/10 px-3 py-2 text-white hover:bg-white/20" aria-label="{{ __('Sonraki') }}">›</button>
                </div>
            </div>

            {{-- Sağ: satın alma --}}
            <aside class="lg:sticky lg:top-32 lg:self-start">
                <div class="card p-6">
                    <h1 class="text-2xl font-extrabold sm:text-3xl">{{ $product->title }}</h1>
                    @if ($store)
                        <a href="{{ route('designers.show', $store) }}" class="mt-2 inline-flex items-center gap-2 text-sm text-muted hover:text-ink">
                            <x-icon name="store" class="size-4"/> {{ $store->store_name }}
                        </a>
                    @endif
                    @if ($product->short_description)
                        <p class="mt-4 text-sm text-muted">{{ $product->short_description }}</p>
                    @endif

                    <div class="mt-6 border-t border-line pt-6">
                        @if ($owns)
                            <p class="flex items-center gap-2 font-semibold text-katman-700 dark:text-katman-300"><x-icon name="check" class="size-5"/>{{ __('Satın alındı') }}</p>
                        @elseif ($isOwnProduct)
                            <p class="text-sm text-muted">{{ __('Bu senin modelin.') }}</p>
                        @else
                            <form method="POST" action="{{ route('products.purchase', $product) }}" x-data="{ confirming: false }" class="space-y-4">
                                @csrf
                                <input type="hidden" name="request_id" value="{{ Str::uuid() }}">
                                @if ($activeLicenses->count() > 1)
                                    <fieldset class="space-y-2">
                                        <legend class="label">{{ __('Lisans') }}</legend>
                                        @foreach ($activeLicenses as $productLicense)
                                            <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-line p-3 has-[:checked]:border-katman-500 has-[:checked]:bg-katman-50 dark:has-[:checked]:bg-katman-950">
                                                <span class="flex items-center gap-2 text-sm"><input type="radio" name="license" value="{{ $productLicense->id }}" @checked($loop->first) class="accent-katman-600">{{ $productLicense->license->name[app()->getLocale()] ?? $productLicense->license->code }}</span>
                                                <x-price :cents="$productLicense->price_cents" size="sm"/>
                                            </label>
                                        @endforeach
                                    </fieldset>
                                @else
                                    <x-price :cents="$product->price_cents" size="lg"/>
                                @endif

                                @guest
                                    <a href="{{ route('login') }}" class="btn-primary w-full py-3">{{ $product->is_free ? __('İndirmek için giriş yap') : __('Satın almak için giriş yap') }}</a>
                                @else
                                    @if ($product->is_free)
                                        <button class="btn-primary w-full py-3"><x-icon name="download" class="size-4"/>{{ __('Kütüphaneme ekle') }}</button>
                                    @else
                                        <button type="button" x-show="!confirming" @click="confirming = true" class="btn-primary w-full py-3">{{ __('Satın al') }}</button>
                                        <div x-cloak x-show="confirming" class="space-y-2 rounded-xl bg-surface-2 p-3 text-sm">
                                            <p>{{ __('Puan bakiyenden düşülecek. Onaylıyor musun?') }}</p>
                                            <p class="text-xs text-muted">{{ __('Mevcut bakiye: :points POINT', ['points' => number_format($balance, 0, ',', '.')]) }}</p>
                                            <div class="flex gap-2">
                                                <button class="btn-primary flex-1">{{ __('Onayla') }}</button>
                                                <button type="button" @click="confirming = false" class="btn-secondary">{{ __('Vazgeç') }}</button>
                                            </div>
                                        </div>
                                    @endif
                                @endguest
                            </form>
                        @endif
                    </div>

                    @if (($owns || $isOwnProduct) && $files->isNotEmpty())
                        <div class="mt-6 border-t border-line pt-6">
                            <h2 class="text-sm font-bold">{{ __('Dosyalar') }}</h2>
                            <ul class="mt-3 space-y-2">
                                @foreach ($files as $file)
                                    <li class="flex items-center justify-between gap-3 rounded-xl border border-line px-3 py-2 text-sm">
                                        <span class="min-w-0 truncate">{{ $file->original_name }} <span class="text-xs text-subtle">· {{ Number::fileSize($file->size_bytes) }}</span></span>
                                        @if ($file->isDownloadable())
                                            <a href="{{ \App\Http\Controllers\DownloadController::temporaryUrl($file) }}" class="btn-secondary shrink-0 px-3 py-1.5"><x-icon name="download" class="size-4"/>{{ __('İndir') }}</a>
                                        @else
                                            <span class="chip shrink-0"><x-icon name="clock" class="size-3.5"/>{{ __('Güvenlik taraması') }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </aside>
        </div>

        {{-- Alt: açıklama ve teknik bilgiler --}}
        <div class="mt-12 grid gap-8 lg:grid-cols-[1.4fr_1fr]">
            <section class="card p-6">
                <h2 class="text-lg font-bold">{{ __('Açıklama') }}</h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed whitespace-pre-line text-muted">{{ $product->description ?: __('Tasarımcı henüz açıklama eklemedi.') }}</div>
                @if ($product->tags->isNotEmpty())
                    <div class="mt-6 flex flex-wrap gap-2">
                        @foreach ($product->tags as $tag)
                            <a href="{{ route('products.index', ['q' => $tag->name]) }}" class="chip hover:text-ink">#{{ $tag->name }}</a>
                        @endforeach
                    </div>
                @endif
            </section>
            <section class="card p-6">
                <h2 class="text-lg font-bold">{{ __('Teknik bilgiler') }}</h2>
                <dl class="mt-4 divide-y divide-line text-sm">
                    @foreach (array_filter([
                        __('Sürüm') => $product->current_version,
                        __('Dosya formatları') => $files->pluck('extension')->map(fn ($ext) => strtoupper($ext))->unique()->implode(', '),
                        __('Filament') => $product->filament_type,
                        __('Destek gerekir mi') => $product->requires_supports === null ? null : ($product->requires_supports ? __('Evet') : __('Hayır')),
                        __('Baskı süresi') => $product->print_time_minutes ? __(':h sa :m dk', ['h' => intdiv($product->print_time_minutes, 60), 'm' => $product->print_time_minutes % 60]) : null,
                        __('Boyutlar (mm)') => $product->dimension_x_mm ? "{$product->dimension_x_mm} × {$product->dimension_y_mm} × {$product->dimension_z_mm}" : null,
                        __('Yazıcı uyumluluğu') => $product->printer_compatibility,
                    ]) as $label => $value)
                        <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">{{ $label }}</dt><dd class="text-right font-medium">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </section>
        </div>

        @if ($product->versions->isNotEmpty())
            <section class="card mt-8 p-6">
                <h2 class="text-lg font-bold">{{ __('Güncelleme geçmişi') }}</h2>
                <ol class="mt-4 space-y-3 text-sm">
                    @foreach ($product->versions as $version)
                        <li class="flex gap-4"><span class="w-16 shrink-0 font-semibold">v{{ $version->version }}</span><span class="text-muted">{{ $version->released_at->format('d.m.Y') }}@if ($version->changelog) — {{ $version->changelog }}@endif</span></li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if ($similar->isNotEmpty())
            <section class="mt-12">
                <x-section-heading :title="__('Benzer modeller')" :href="route('categories.show', $product->category)"/>
                <x-product-grid :products="$similar"/>
            </section>
        @endif
    </div>
</x-layouts.app>
