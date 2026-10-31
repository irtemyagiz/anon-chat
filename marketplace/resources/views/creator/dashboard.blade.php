@php $usd = fn (int $cents) => '$'.\App\Services\Points\PointConverter::formatCents($cents); @endphp
<x-layouts.creator :title="__('Genel Bakış')">
    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        @foreach ([
            [__('Toplam satış'), number_format($stats['sales'], 0, ',', '.')],
            [__('Toplam gelir'), $usd($stats['revenue_cents'])],
            [__('Bekleyen gelir'), $usd((int) $balance->pending_cents)],
            [__('Kullanılabilir gelir'), $usd((int) $balance->available_cents)],
            [__('Toplam indirme'), number_format($stats['downloads'], 0, ',', '.')],
            [__('Toplam görüntülenme'), number_format($stats['views'], 0, ',', '.')],
            [__('Takipçi'), number_format($stats['followers'], 0, ',', '.')],
            [__('Aktif ürün'), number_format($stats['active_products'], 0, ',', '.')],
        ] as [$label, $value])
            <div class="card p-5">
                <p class="text-xs font-medium text-muted">{{ $label }}</p>
                <p class="mt-2 font-display text-2xl font-extrabold tabular-nums">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-subtle">{{ __('Gelirler USD olarak gösterilir. Satış kazancı iade/itiraz riskine karşı :days gün bekledikten sonra kullanılabilir olur.', ['days' => config('marketplace.earnings.hold_days')]) }}</p>

    <section class="card mt-8 p-6">
        <div class="flex items-center justify-between">
            <h2 class="font-bold">{{ __('Son 30 gün satışlar') }}</h2>
            <span class="text-xs text-subtle">{{ __('Günlük satış adedi') }}</span>
        </div>
        @if ($chart->sum('count') === 0)
            <p class="mt-6 rounded-xl bg-surface-2 px-4 py-8 text-center text-sm text-muted">{{ __('Son 30 günde satış yok. İlk satışın gerçekleştiğinde grafik burada oluşacak.') }}</p>
        @else
            <div class="mt-6 flex h-40 items-end gap-1" role="img" aria-label="{{ __('Son 30 gün satış grafiği') }}">
                @foreach ($chart as $day)
                    <div class="group relative flex-1">
                        <div class="w-full rounded-t bg-katman-500/80 transition group-hover:bg-katman-600" style="height: {{ max(2, round($day['count'] / $chartMax * 150)) }}px"></div>
                        <span class="pointer-events-none absolute bottom-full left-1/2 mb-1 hidden -translate-x-1/2 rounded-md bg-ink px-2 py-1 text-[0.65rem] whitespace-nowrap text-canvas group-hover:block">{{ $day['date']->format('d.m') }} · {{ $day['count'] }} · {{ $usd($day['cents']) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <section class="card p-6">
            <h2 class="font-bold">{{ __('En çok satan ürünler') }}</h2>
            @forelse ($topProducts as $product)
                <div class="flex items-center justify-between gap-3 border-b border-line py-3 text-sm last:border-0">
                    <a href="{{ route('creator.products.edit', $product) }}" class="truncate font-medium hover:underline">{{ $product->title }}</a>
                    <span class="shrink-0 text-muted">{{ trans_choice(':count satış|:count satış', $product->sales_count, ['count' => $product->sales_count]) }}</span>
                </div>
            @empty
                <p class="mt-4 text-sm text-muted">{{ __('Henüz satış yok.') }}</p>
            @endforelse
        </section>
        <section class="card p-6">
            <div class="flex items-center justify-between">
                <h2 class="font-bold">{{ __('Bekleyen işler') }}</h2>
                <a href="{{ route('creator.products.create') }}" class="btn-primary px-3 py-1.5"><x-icon name="upload" class="size-4"/>{{ __('Yeni ürün') }}</a>
            </div>
            @forelse ($attention as $product)
                <div class="flex items-center justify-between gap-3 border-b border-line py-3 text-sm last:border-0">
                    <a href="{{ route('creator.products.edit', $product) }}" class="min-w-0">
                        <span class="block truncate font-medium hover:underline">{{ $product->title }}</span>
                        @if ($product->moderation_note)<span class="block truncate text-xs text-red-600 dark:text-red-400">{{ $product->moderation_note }}</span>@endif
                    </a>
                    <x-status-badge :status="$product->status"/>
                </div>
            @empty
                <p class="mt-4 text-sm text-muted">{{ __('Taslak veya reddedilen ürünün yok.') }}</p>
            @endforelse
        </section>
    </div>
</x-layouts.creator>
