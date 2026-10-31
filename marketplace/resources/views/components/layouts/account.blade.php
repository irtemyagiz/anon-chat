@props(['title'])
@php
    $menu = [
        ['account.library', 'download', __('Kütüphanem')],
        ['account.favorites', 'sparkles', __('Favorilerim')],
        ['account.collections', 'layers', __('Koleksiyonlarım')],
        ['account.following', 'store', __('Takip ettiklerim')],
        ['wallet', 'wallet', __('Cüzdanım')],
        ['account.notifications', 'mail', __('Bildirimler')],
        ['support.index', 'shield', __('Destek')],
        ['account.settings', 'user', __('Profil ve ayarlar')],
    ];
@endphp
<x-layouts.app :title="$title" :noindex="true">
    <div class="container-page grid gap-8 py-8 lg:grid-cols-[14rem_1fr]">
        <nav class="-mx-4 flex gap-1 overflow-x-auto px-4 pb-2 lg:sticky lg:top-32 lg:mx-0 lg:flex-col lg:self-start lg:px-0" aria-label="{{ __('Hesap menüsü') }}">
            @foreach ($menu as [$route, $icon, $label])
                <a href="{{ route($route) }}" @class([
                    'flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition',
                    'bg-katman-600 text-white dark:bg-katman-500 dark:text-katman-950' => request()->routeIs($route),
                    'text-muted hover:bg-surface-2 hover:text-ink' => ! request()->routeIs($route),
                ])><x-icon :name="$icon" class="size-4"/>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="min-w-0">
            <h1 class="mb-6 text-2xl font-extrabold sm:text-3xl">{{ $title }}</h1>
            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
