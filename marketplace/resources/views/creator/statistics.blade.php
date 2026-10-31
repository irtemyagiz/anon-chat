@php
    $usd = fn (int $cents) => '$'.\App\Services\Points\PointConverter::formatCents($cents);
    $num = fn (int $value) => number_format($value, 0, ',', '.');
    $periodLabels = ['gunluk' => __('Son 30 gün'), 'haftalik' => __('Son 12 hafta'), 'aylik' => __('Son 12 ay')];
    $chartData = array_map(fn (array $bucket) => [
        'label' => $bucket['label'],
        'sales' => $bucket['sales'],
        'revenue' => $bucket['revenue_cents'],
        'revenueText' => $usd($bucket['revenue_cents']),
        'downloads' => $bucket['downloads'],
        'followers' => $bucket['followers'],
    ], $series);
@endphp
<x-layouts.creator :title="__('İstatistikler')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="flex gap-1 rounded-xl bg-surface-2 p-1 text-sm" aria-label="{{ __('Dönem') }}">
            @foreach ($periodLabels as $value => $label)
                <a href="{{ route('creator.statistics', ['donem' => $value]) }}" @class([
                    'rounded-lg px-3 py-1.5 font-semibold transition',
                    'bg-surface text-ink shadow-sm' => $period === $value,
                    'text-muted hover:text-ink' => $period !== $value,
                ]) @if ($period === $value) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <p class="text-xs text-subtle">{{ __('Tarihler UTC saatine göredir. Gelir, komisyon düşülmüş senin payındır.') }}</p>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            [__('Satış'), $num($totals['sales'])],
            [__('Gelir'), $usd($totals['revenue_cents'])],
            [__('İndirme'), $num($totals['downloads'])],
            [__('Yeni takipçi'), $num($totals['followers'])],
        ] as [$label, $value])
            <div class="card p-5">
                <p class="text-sm text-muted">{{ $label }}</p>
                <p class="mt-1 font-display text-2xl font-bold tabular-nums">{{ $value }}</p>
                <p class="text-xs text-subtle">{{ $periodLabels[$period] }}</p>
            </div>
        @endforeach
    </div>

    <section class="card mt-6 p-6" x-data="{ metric: 'sales', data: @js($chartData), get max() { return Math.max(1, ...this.data.map(d => d[this.metric])) } }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold">{{ __('Grafik') }}</h2>
            <div class="flex flex-wrap gap-1 text-sm" role="group" aria-label="{{ __('Gösterilen değer') }}">
                @foreach (['sales' => __('Satış'), 'revenue' => __('Gelir'), 'downloads' => __('İndirme'), 'followers' => __('Takipçi')] as $metric => $label)
                    <button type="button" @click="metric = '{{ $metric }}'" class="rounded-lg px-3 py-1.5 font-semibold" :class="metric === '{{ $metric }}' ? 'bg-katman-600 text-white dark:bg-katman-500 dark:text-katman-950' : 'text-muted hover:bg-surface-2'" :aria-pressed="(metric === '{{ $metric }}').toString()">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        @if (array_sum(array_map(fn ($b) => $b['sales'] + $b['downloads'] + $b['followers'], $series)) === 0)
            <p class="mt-6 rounded-xl bg-surface-2 px-4 py-8 text-center text-sm text-muted">{{ __('Bu dönemde henüz hareket yok. Satış, indirme veya takip gerçekleştiğinde grafik burada oluşacak.') }}</p>
        @else
            <div class="mt-6 flex h-48 items-end gap-1" role="img" aria-label="{{ __('İstatistik grafiği') }}">
                <template x-for="bucket in data" :key="bucket.label">
                    <div class="group relative flex h-full flex-1 flex-col justify-end">
                        <div class="w-full rounded-t bg-katman-500/80 transition group-hover:bg-katman-600" :style="`height: ${Math.max(2, Math.round(bucket[metric] / max * 180))}px`"></div>
                        <span class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 rounded-md bg-ink px-2 py-1 text-[0.65rem] whitespace-nowrap text-canvas group-hover:block"
                              x-text="`${bucket.label} · ${metric === 'revenue' ? bucket.revenueText : bucket[metric]}`"></span>
                    </div>
                </template>
            </div>
            <div class="mt-2 flex justify-between text-[0.65rem] text-subtle">
                <span>{{ $series[0]['label'] }}</span><span>{{ end($series)['label'] }}</span>
            </div>
        @endif
    </section>

    <div class="mt-6 grid gap-6 2xl:grid-cols-[2fr_1fr]">
        <section class="card overflow-x-auto">
            <h2 class="px-5 pt-5 text-lg font-bold">{{ __('Ürün performansı') }}</h2>
            @if ($products->isEmpty())
                <p class="p-5 text-sm text-muted">{{ __('Henüz ürünün yok.') }}</p>
            @else
                <table class="mt-3 w-full min-w-[40rem] text-sm">
                    <thead class="border-b border-line text-left text-xs text-muted uppercase">
                        <tr>
                            <th class="px-5 py-3 font-semibold">{{ __('Ürün') }}</th>
                            <th class="px-3 py-3 text-right font-semibold">{{ __('Görüntülenme') }}</th>
                            <th class="px-3 py-3 text-right font-semibold">{{ __('Beğeni') }}</th>
                            <th class="px-3 py-3 text-right font-semibold">{{ __('İndirme') }}</th>
                            <th class="px-3 py-3 text-right font-semibold">{{ __('Satış') }}</th>
                            <th class="px-3 py-3 text-right font-semibold">{{ __('Dönüşüm') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('Gelir') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($products as $product)
                            <tr>
                                <td class="px-5 py-3"><a href="{{ route('creator.products.edit', $product->uuid) }}" class="font-medium hover:underline">{{ $product->title }}</a></td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ $num($product->views_count) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ $num($product->likes_count) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ $num($product->downloads_count) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ $num($product->sales_count) }}</td>
                                <td class="px-3 py-3 text-right text-muted tabular-nums">%{{ number_format($product->conversion_bps / 100, 1, ',', '') }}</td>
                                <td class="px-5 py-3 text-right font-semibold tabular-nums">{{ $usd($product->revenue_cents) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="px-5 py-3 text-xs text-subtle">{{ __('Dönüşüm: satış / görüntülenme. Görüntülenme oturum başına bir kez sayılır; kendi ziyaretlerin sayılmaz.') }}</p>
            @endif
        </section>
        <section class="card p-5">
            <h2 class="text-lg font-bold">{{ __('En çok görüntülenenler') }}</h2>
            @if ($mostViewed->isEmpty())
                <p class="mt-3 text-sm text-muted">{{ __('Henüz görüntülenme yok.') }}</p>
            @else
                <ol class="mt-3 space-y-2 text-sm">
                    @foreach ($mostViewed as $product)
                        <li class="flex items-center justify-between gap-3"><span class="truncate">{{ $loop->iteration }}. {{ $product->title }}</span><span class="shrink-0 text-muted tabular-nums">{{ $num($product->views_count) }}</span></li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</x-layouts.creator>
