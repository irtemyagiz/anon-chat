@php
    $num = fn (int $n) => number_format($n, 0, ',', '.');
    $usd = fn (int $cents) => '$'.\App\Services\Points\PointConverter::formatCents($cents);
@endphp
<x-layouts.admin :title="__('Dashboard')">
    @if (array_sum($queues) > 0)
        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['creators', __('creator başvurusu bekliyor'), 'admin.creators.index', 'creators.manage'],
                ['products', __('ürün inceleme bekliyor'), 'admin.products.index', 'products.moderate'],
                ['payouts', __('hakediş talebi bekliyor'), null, 'payouts.manage'],
            ] as [$key, $label, $route, $ability])
                @if ($queues[$key] > 0)
                    @can($ability)
                        <a @if ($route) href="{{ route($route) }}" @endif class="flex items-center gap-3 rounded-xl border border-filament-400/50 bg-filament-400/10 px-4 py-3 text-sm">
                            <span class="font-display text-2xl font-extrabold">{{ $queues[$key] }}</span><span>{{ $label }}</span>
                        </a>
                    @endcan
                @endif
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        @foreach ([
            [__('Toplam kullanıcı'), $num($stats['users'])],
            [__('Onaylı creator'), $num($stats['creators'])],
            [__('Yayındaki ürün'), $num($stats['products'])],
            [__('Toplam satış'), $num($stats['sales'])],
            [__('Toplam puan yükleme'), $num($stats['points_loaded']).' P'],
            [__('Toplam puan harcama'), $num($stats['points_spent']).' P'],
            [__('Platform geliri'), $usd($stats['platform_revenue_cents'])],
            [__('Bekleyen hakediş'), $usd($stats['pending_payout_cents'])],
            [__('Kullanıcılardaki puan'), $num($stats['outstanding_points']).' P'],
            [__('Aktif kullanıcı (30 gün)'), $num($stats['active_users'])],
            [__('Yeni kayıt (7 gün)'), $num($stats['new_users'])],
            [__('USD/TRY'), $rate ? $rate->formattedRate() : '—'],
        ] as [$label, $value])
            <div class="card p-5">
                <p class="text-xs font-medium text-muted">{{ $label }}</p>
                <p class="mt-2 font-display text-2xl font-extrabold tabular-nums">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    <p class="mt-4 text-xs text-subtle">{{ __('"Kullanıcılardaki puan", yüklenmiş ancak henüz harcanmamış puanların toplamıdır ve muhasebede yükümlülük olarak izlenmelidir.') }}</p>
</x-layouts.admin>
