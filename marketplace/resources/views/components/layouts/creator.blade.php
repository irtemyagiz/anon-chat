@props(['title'])
@php
    $menu = [
        ['creator.dashboard', 'layers', __('Genel Bakış')],
        ['creator.products.index', 'cube', __('Ürünlerim')],
        ['creator.products.create', 'upload', __('Yeni Ürün Ekle')],
        ['creator.statistics', 'chart', __('İstatistikler')],
        ['creator.sales', 'coins', __('Satışlar')],
        ['creator.earnings', 'wallet', __('Gelirlerim')],
        ['creator.payouts', 'coins', __('Hakedişler')],
        ['creator.coupons.index', 'tag', __('Kuponlar')],
        ['creator.reviews', 'heart', __('Yorumlar')],
        ['creator.followers', 'user', __('Takipçiler')],
        ['creator.store.edit', 'store', __('Mağaza Ayarları')],
    ];
@endphp
<x-layouts.app :title="$title" :noindex="true">
    <div class="container-page grid gap-8 py-8 lg:grid-cols-[15rem_1fr]">
        <aside class="lg:sticky lg:top-32 lg:self-start">
            <div class="mb-4 hidden lg:block">
                <p class="eyebrow">{{ __('Creator paneli') }}</p>
                <p class="mt-1 truncate font-display text-lg font-bold">{{ auth()->user()->creatorProfile->store_name }}</p>
            </div>
            <nav class="-mx-4 flex gap-1 overflow-x-auto px-4 pb-2 lg:mx-0 lg:flex-col lg:px-0" aria-label="{{ __('Creator menüsü') }}">
                @foreach ($menu as [$route, $icon, $label])
                    <a href="{{ route($route) }}" @class([
                        'flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition',
                        'bg-katman-600 text-white dark:bg-katman-500 dark:text-katman-950' => request()->routeIs($route),
                        'text-muted hover:bg-surface-2 hover:text-ink' => ! request()->routeIs($route),
                    ])><x-icon :name="$icon" class="size-4"/>{{ $label }}</a>
                @endforeach
                <a href="{{ route('designers.show', auth()->user()->creatorProfile) }}" class="flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-muted hover:bg-surface-2 hover:text-ink lg:mt-3 lg:border-t lg:border-line lg:pt-4">
                    <x-icon name="arrow-right" class="size-4"/>{{ __('Mağazamı görüntüle') }}
                </a>
            </nav>
        </aside>
        <div class="min-w-0">
            <h1 class="mb-6 text-2xl font-extrabold sm:text-3xl">{{ $title }}</h1>
            @if ($errors->any() && ! $errors->hasAny(array_keys(request()->except('_token', '_method'))))
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                    @foreach ($errors->only(['uploads', 'submit', 'logo']) as $message)<p>{{ $message }}</p>@endforeach
                </div>
            @endif
            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
