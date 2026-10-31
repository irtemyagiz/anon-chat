@props(['title' => null, 'description' => null, 'noindex' => false])
@php
    $pageTitle = $title ? $title.' · '.config('app.name') : config('app.name').' — '.__('3D baskı tasarım pazaryeri');
    $isDark = $themePreference === 'dark';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themePreference }}" @class(['dark' => $isDark])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description ?? __('Bağımsız tasarımcıların 3D baskıya hazır STL, 3MF ve OBJ modellerini keşfet, puanla satın al veya ücretsiz indir.') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @if ($noindex)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    {{-- "system" tercihinde ilk boyamadan önce işletim sistemi temasını uygula --}}
    <script>
        (function () {
            var pref = document.documentElement.dataset.theme;
            if (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen" data-authenticated="{{ auth()->check() ? '1' : '0' }}">
<a href="#icerik" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-3 focus:py-2">{{ __('İçeriğe geç') }}</a>

<header x-data="{ mobile: false }" class="sticky top-0 z-40 border-b border-line bg-canvas/85 backdrop-blur-md">
    <div class="container-page flex h-16 items-center gap-4">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ config('app.name') }}"><x-logo/></a>

        <form action="{{ route('products.index') }}" method="GET" role="search" class="relative hidden max-w-md flex-1 md:block">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle"/>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Model, etiket veya tasarımcı ara…') }}" class="input rounded-full pl-9" aria-label="{{ __('Ara') }}">
        </form>

        <div class="ml-auto flex items-center gap-1">
            @auth
                <a href="{{ route('wallet') }}" class="hidden items-center gap-2 rounded-full border border-line bg-surface px-3 py-1.5 text-sm font-semibold sm:inline-flex" title="{{ __('Puan bakiyesi') }}">
                    <x-icon name="coins" class="size-4 text-filament-500"/>
                    <span class="tabular-nums">{{ number_format($walletBalance ?? 0, 0, ',', '.') }}</span>
                    <span class="text-[0.65rem] font-bold tracking-wide text-katman-700 dark:text-katman-300">POINT</span>
                </a>
            @endauth

            <button type="button" @click="$store.theme.toggle()" class="btn-ghost size-10 rounded-full p-0" aria-label="{{ __('Temayı değiştir') }}">
                <x-icon name="moon" class="size-5 dark:hidden"/>
                <x-icon name="sun" class="hidden size-5 dark:block"/>
            </button>

            @auth
                <div x-data="{ open: false }" class="relative hidden md:block" @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open" class="btn-ghost rounded-full px-2" :aria-expanded="open">
                        <span class="flex size-8 items-center justify-center rounded-full bg-katman-100 text-sm font-bold text-katman-800 dark:bg-katman-900 dark:text-katman-200">{{ mb_strtoupper(mb_substr(auth()->user()->first_name, 0, 1)) }}</span>
                        <x-icon name="chevron-down" class="size-4"/>
                    </button>
                    <div x-cloak x-show="open" x-transition.origin.top.right @click.outside="open = false" class="card absolute right-0 mt-2 w-60 p-2">
                        <div class="px-3 py-2">
                            <p class="truncate text-sm font-semibold">{{ auth()->user()->full_name }}</p>
                            <p class="truncate text-xs text-muted">{{ '@'.auth()->user()->username }}</p>
                        </div>
                        <div class="my-1 border-t border-line"></div>
                        <a href="{{ route('account.library') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="download" class="size-4"/>{{ __('Kütüphanem') }}</a>
                        <a href="{{ route('account.favorites') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="sparkles" class="size-4"/>{{ __('Favorilerim') }}</a>
                        <a href="{{ route('account.collections') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="layers" class="size-4"/>{{ __('Koleksiyonlarım') }}</a>
                        <a href="{{ route('wallet') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="wallet" class="size-4"/>{{ __('Cüzdanım') }}</a>
                        <a href="{{ route('top-up.create') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="coins" class="size-4"/>{{ __('Puan yükle') }}</a>
                        @can('admin.access')
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="shield" class="size-4"/>{{ __('Yönetim paneli') }}</a>
                        @endcan
                        @if (auth()->user()->creatorProfile)
                            <a href="{{ auth()->user()->isApprovedCreator() ? route('creator.dashboard') : route('creator.status') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="store" class="size-4"/>{{ __('Creator paneli') }}</a>
                        @endif
                        <a href="{{ route('account.settings') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="user" class="size-4"/>{{ __('Profil ve ayarlar') }}</a>
                        <div class="my-1 border-t border-line"></div>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-2"><x-icon name="logout" class="size-4"/>{{ __('Çıkış yap') }}</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn-ghost hidden md:inline-flex">{{ __('Giriş Yap') }}</a>
                <a href="{{ route('register') }}" class="btn-primary hidden md:inline-flex">{{ __('Kayıt Ol') }}</a>
            @endauth

            <button type="button" @click="mobile = true" class="btn-ghost size-10 rounded-full p-0 lg:hidden" aria-label="{{ __('Menüyü aç') }}">
                <x-icon name="menu"/>
            </button>
        </div>
    </div>

    <nav class="container-page hidden h-12 items-center gap-1 text-sm font-medium lg:flex" aria-label="{{ __('Ana menü') }}">
        <div x-data="{ open: false }" class="relative" @mouseleave="open = false" @keydown.escape.window="open = false">
            <button type="button" @click="open = !open" @mouseenter="open = true" class="btn-ghost px-3 py-2" :aria-expanded="open">
                {{ __('Kategoriler') }} <x-icon name="chevron-down" class="size-4"/>
            </button>
            <div x-cloak x-show="open" x-transition class="card absolute left-0 z-50 mt-1 grid w-[34rem] grid-cols-2 gap-1 p-3">
                @foreach ($navCategories as $navCategory)
                    <a href="{{ route('categories.show', $navCategory['slug']) }}" class="rounded-lg px-3 py-2 text-sm text-ink hover:bg-surface-2">{{ $navCategory['name'] }}</a>
                @endforeach
                <a href="{{ route('categories.index') }}" class="col-span-2 mt-1 rounded-lg px-3 py-2 text-sm font-semibold text-katman-700 hover:bg-surface-2 dark:text-katman-300">{{ __('Tüm kategoriler') }} →</a>
            </div>
        </div>
        <a href="{{ route('products.index', ['fiyat' => 'ucretsiz']) }}" class="btn-ghost px-3 py-2">{{ __('Ücretsiz Modeller') }}</a>
        <a href="{{ route('products.index', ['sirala' => 'populer']) }}" class="btn-ghost px-3 py-2">{{ __('Popüler Modeller') }}</a>
        <a href="{{ route('products.index', ['sirala' => 'yeni']) }}" class="btn-ghost px-3 py-2">{{ __('Yeni Modeller') }}</a>
        <a href="{{ route('designers.index') }}" class="btn-ghost px-3 py-2">{{ __('Tasarımcılar') }}</a>
        <a href="{{ route('upload') }}" class="btn-secondary ml-auto py-1.5"><x-icon name="upload" class="size-4"/>{{ __('Tasarım Yükle') }}</a>
    </nav>

    {{-- Mobil menü --}}
    <div x-cloak x-show="mobile" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
        <div x-show="mobile" x-transition.opacity class="absolute inset-0 bg-black/40" @click="mobile = false"></div>
        <div x-show="mobile" x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:leave="transition duration-150" x-transition:leave-end="translate-x-full"
             class="absolute inset-y-0 right-0 flex w-80 max-w-[88vw] flex-col overflow-y-auto bg-surface p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <x-logo/>
                <button type="button" @click="mobile = false" class="btn-ghost size-10 rounded-full p-0" aria-label="{{ __('Menüyü kapat') }}"><x-icon name="x"/></button>
            </div>
            <form action="{{ route('products.index') }}" method="GET" class="relative mt-5">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle"/>
                <input type="search" name="q" placeholder="{{ __('Ara…') }}" class="input pl-9" aria-label="{{ __('Ara') }}">
            </form>
            @auth
                <a href="{{ route('wallet') }}" class="mt-4 flex items-center justify-between rounded-xl bg-surface-2 px-4 py-3 text-sm font-semibold">
                    <span class="flex items-center gap-2"><x-icon name="coins" class="size-4 text-filament-500"/>{{ __('Puan bakiyesi') }}</span>
                    <span class="tabular-nums">{{ number_format($walletBalance ?? 0, 0, ',', '.') }} POINT</span>
                </a>
            @endauth
            <nav class="mt-4 flex flex-col gap-1 text-sm font-medium">
                <a href="{{ route('categories.index') }}" class="rounded-lg px-3 py-2.5 hover:bg-surface-2">{{ __('Kategoriler') }}</a>
                <a href="{{ route('products.index', ['fiyat' => 'ucretsiz']) }}" class="rounded-lg px-3 py-2.5 hover:bg-surface-2">{{ __('Ücretsiz Modeller') }}</a>
                <a href="{{ route('products.index', ['sirala' => 'populer']) }}" class="rounded-lg px-3 py-2.5 hover:bg-surface-2">{{ __('Popüler Modeller') }}</a>
                <a href="{{ route('products.index', ['sirala' => 'yeni']) }}" class="rounded-lg px-3 py-2.5 hover:bg-surface-2">{{ __('Yeni Modeller') }}</a>
                <a href="{{ route('designers.index') }}" class="rounded-lg px-3 py-2.5 hover:bg-surface-2">{{ __('Tasarımcılar') }}</a>
                <a href="{{ route('upload') }}" class="rounded-lg px-3 py-2.5 hover:bg-surface-2">{{ __('Tasarım Yükle') }}</a>
                @auth
                    <a href="{{ route('account.library') }}" class="rounded-lg px-3 py-2.5 hover:bg-surface-2">{{ __('Kütüphanem') }}</a>
                    <a href="{{ route('account.settings') }}" class="rounded-lg px-3 py-2.5 hover:bg-surface-2">{{ __('Profil ve ayarlar') }}</a>
                @endauth
            </nav>
            <div class="mt-auto flex flex-col gap-2 pt-6">
                @auth
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-secondary w-full">{{ __('Çıkış yap') }}</button></form>
                @else
                    <a href="{{ route('login') }}" class="btn-secondary">{{ __('Giriş Yap') }}</a>
                    <a href="{{ route('register') }}" class="btn-primary">{{ __('Kayıt Ol') }}</a>
                @endauth
            </div>
        </div>
    </div>
</header>

<x-flash/>

<main id="icerik">
    {{ $slot }}
</main>

<footer class="mt-24 border-t border-line bg-surface">
    <div class="container-page grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <x-logo/>
            <p class="mt-4 max-w-sm text-sm text-muted">{{ __('Bağımsız tasarımcıların 3D baskıya hazır modellerini keşfet. Puanla satın al, güvenle indir, katman katman üret.') }}</p>
        </div>
        <div>
            <h3 class="text-sm font-bold">{{ __('Keşfet') }}</h3>
            <ul class="mt-3 space-y-2 text-sm text-muted">
                <li><a href="{{ route('categories.index') }}" class="hover:text-ink">{{ __('Kategoriler') }}</a></li>
                <li><a href="{{ route('products.index', ['fiyat' => 'ucretsiz']) }}" class="hover:text-ink">{{ __('Ücretsiz Modeller') }}</a></li>
                <li><a href="{{ route('designers.index') }}" class="hover:text-ink">{{ __('Tasarımcılar') }}</a></li>
                <li><a href="{{ route('creator.apply') }}" class="hover:text-ink">{{ __('Creator ol') }}</a></li>
            </ul>
        </div>
        <div>
            <h3 class="text-sm font-bold">{{ __('Tercihler') }}</h3>
            <form method="POST" action="{{ route('preferences.update') }}" class="mt-3 space-y-3" x-data>
                @csrf
                <label class="block text-xs text-muted">{{ __('Para birimi') }}
                    <select name="currency" class="input mt-1 py-2" @change="$el.form.submit()">
                        <option value="TRY" @selected($displayCurrency === 'TRY')>TL (₺)</option>
                        <option value="USD" @selected($displayCurrency === 'USD')>USD ($)</option>
                    </select>
                </label>
{{-- Dil seçici, İngilizce çeviriler (lang/en.json) tamamlandığında eklenecek. Altyapı hazır: ApplyUserPreferences. --}}
                <noscript><button class="btn-secondary w-full">{{ __('Kaydet') }}</button></noscript>
            </form>
        </div>
    </div>
    <div class="border-t border-line">
        <div class="container-page flex flex-col gap-2 py-5 text-xs text-subtle sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ date('Y') }} {{ config('app.name') }}. {{ __('Tüm hakları saklıdır.') }}</p>
            <p>{{ __('1 USD = 100 POINT · TL fiyatlar güncel kurdan hesaplanır.') }}</p>
        </div>
    </div>
</footer>
</body>
</html>
