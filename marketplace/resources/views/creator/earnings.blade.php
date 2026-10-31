@php $usd = fn (int $cents) => ($cents < 0 ? '-' : '').'$'.\App\Services\Points\PointConverter::formatCents(abs($cents)); @endphp
<x-layouts.creator :title="__('Gelirlerim')">
    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        @foreach ([
            [__('Bekleyen'), (int) $balance->pending_cents, __(':days günlük iade süresi dolmamış', ['days' => $holdDays])],
            [__('Kullanılabilir'), (int) $balance->available_cents - (int) $balance->reserved_cents, __('Ödeme talebi oluşturulabilir')],
            [__('Ödenen'), (int) $balance->paid_cents, __('Hesabına aktarılan')],
            [__('İade nedeniyle geri alınan'), (int) $balance->reversed_cents, __('İade edilen satışlar')],
        ] as [$label, $cents, $hint])
            <div class="card p-5">
                <p class="text-xs font-medium text-muted">{{ $label }}</p>
                <p class="mt-2 font-display text-2xl font-extrabold tabular-nums">{{ $usd($cents) }}</p>
                <p class="mt-1 text-xs text-subtle">{{ $hint }}</p>
            </div>
        @endforeach
    </div>
    <p class="mt-4 rounded-xl bg-surface-2 px-4 py-3 text-sm text-muted">{{ __('Creator kazançları puan cüzdanından ayrı tutulur ve USD bazlı hakediştir.') }} <a href="{{ route('creator.payouts') }}" class="font-semibold text-katman-700 hover:underline dark:text-katman-300">{{ __('Hakediş talebi oluştur') }} →</a></p>

    <h2 class="mt-10 mb-4 text-lg font-bold">{{ __('Kazanç hareketleri') }}</h2>
    @if ($earnings->isEmpty())
        <x-empty-state icon="wallet" :title="__('Henüz kazanç yok')" :description="__('İlk satışından sonra kazançların burada görünür.')"/>
    @else
        <div class="card overflow-x-auto">
            <table class="w-full min-w-[36rem] text-sm">
                <thead class="border-b border-line text-left text-xs text-muted uppercase">
                    <tr><th class="px-5 py-3 font-semibold">{{ __('Tarih') }}</th><th class="px-5 py-3 font-semibold">{{ __('Ürün') }}</th><th class="px-5 py-3 text-right font-semibold">{{ __('Net') }}</th><th class="px-5 py-3 font-semibold">{{ __('Kullanılabilir olma') }}</th><th class="px-5 py-3 font-semibold">{{ __('Durum') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($earnings as $earning)
                        <tr>
                            <td class="px-5 py-3 whitespace-nowrap text-muted">{{ $earning->created_at->format('d.m.Y') }}</td>
                            <td class="px-5 py-3">{{ $earning->orderItem->product_title }}</td>
                            <td class="px-5 py-3 text-right font-semibold tabular-nums">{{ $usd($earning->net_cents) }}</td>
                            <td class="px-5 py-3 text-muted">{{ $earning->available_at->format('d.m.Y') }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$earning->status"/></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $earnings->links() }}</div>
    @endif
</x-layouts.creator>
