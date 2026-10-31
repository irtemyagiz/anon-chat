@props(['title'])
@php
    $menu = [
        ['admin.dashboard', 'layers', __('Dashboard'), 'admin.access'],
        ['admin.creators.index', 'store', __('Creator başvuruları'), 'creators.manage'],
        ['admin.products.index', 'cube', __('Ürün moderasyonu'), 'products.moderate'],
        ['admin.moderation.reports', 'shield', __('İçerik şikâyetleri'), 'products.moderate'],
        ['admin.moderation.comments', 'heart', __('Yorum moderasyonu'), 'comments.moderate'],
        ['admin.users.index', 'user', __('Kullanıcılar'), 'users.manage'],
        ['admin.payouts.index', 'wallet', __('Hakedişler'), 'payouts.manage'],
        ['admin.points.index', 'coins', __('Puan sistemi'), 'payments.manage'],
        ['admin.payments.index', 'wallet', __('Ödemeler'), 'payments.manage'],
        ['admin.exchange-rates.index', 'globe', __('Döviz kuru'), 'exchange_rates.manage'],
        ['admin.commissions.index', 'sparkles', __('Komisyon'), 'commissions.manage'],
        ['admin.categories.index', 'menu', __('Kategoriler'), 'categories.manage'],
        ['admin.file-formats.index', 'upload', __('Dosya formatları'), 'settings.manage'],
        ['admin.support.index', 'mail', __('Destek talepleri'), 'support.manage'],
        ['admin.content.pages', 'sparkles', __('İçerik'), 'content.manage'],
        ['admin.seo', 'globe', __('SEO'), 'content.manage'],
        ['admin.settings', 'menu', __('Sistem ayarları'), 'settings.manage'],
        ['admin.logs', 'shield', __('Loglar'), 'logs.view'],
    ];
@endphp
<x-layouts.app :title="$title.' · '.__('Yönetim')" :noindex="true">
    <div class="container-page grid gap-8 py-8 lg:grid-cols-[15rem_1fr]">
        <aside class="lg:sticky lg:top-32 lg:self-start">
            <p class="eyebrow mb-3 hidden lg:block">{{ __('Yönetim paneli') }}</p>
            <nav class="-mx-4 flex gap-1 overflow-x-auto px-4 pb-2 lg:mx-0 lg:flex-col lg:px-0" aria-label="{{ __('Yönetim menüsü') }}">
                @foreach ($menu as [$route, $icon, $label, $ability])
                    @can($ability)
                        @php $active = request()->routeIs(str_replace('.index', '.*', $route)); @endphp
                        <a href="{{ route($route) }}" @class([
                            'flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition',
                            'bg-ink text-canvas' => $active,
                            'text-muted hover:bg-surface-2 hover:text-ink' => ! $active,
                        ])><x-icon :name="$icon" class="size-4"/>{{ $label }}</a>
                    @endcan
                @endforeach
            </nav>
        </aside>
        <div class="min-w-0">
            <h1 class="mb-6 text-2xl font-extrabold sm:text-3xl">{{ $title }}</h1>
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                    @foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach
                </div>
            @endif
            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
