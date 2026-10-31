@php $usd = fn (int $cents) => '$'.\App\Services\Points\PointConverter::formatCents($cents); @endphp
<x-layouts.creator :title="__('Satışlar')">
    @if ($items->isEmpty())
        <x-empty-state icon="coins" :title="__('Henüz satış yok')" :description="__('Ürünlerin satıldığında her satış, komisyon ve senin payınla birlikte burada listelenir.')"/>
    @else
        <div class="card overflow-x-auto">
            <table class="w-full min-w-[44rem] text-sm">
                <thead class="border-b border-line text-left text-xs text-muted uppercase">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Tarih') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Ürün') }}</th>
                        <th class="px-5 py-3 text-right font-semibold">{{ __('Satış') }}</th>
                        <th class="px-5 py-3 text-right font-semibold">{{ __('Komisyon') }}</th>
                        <th class="px-5 py-3 text-right font-semibold">{{ __('Senin payın') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Durum') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($items as $item)
                        <tr>
                            <td class="px-5 py-3 whitespace-nowrap text-muted">{{ $item->created_at->format('d.m.Y H:i') }}</td>
                            <td class="px-5 py-3"><span class="font-medium">{{ $item->product_title }}</span><span class="block text-xs text-subtle">{{ $item->license->name[app()->getLocale()] ?? $item->license->code }} · {{ $item->order->number }}</span></td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format($item->price_points, 0, ',', '.') }} P<span class="block text-xs text-subtle">{{ $usd($item->creator_amount_cents + $item->platform_amount_cents) }}</span></td>
                            <td class="px-5 py-3 text-right text-muted tabular-nums">%{{ rtrim(rtrim(number_format($item->commission_bps / 100, 2, ',', ''), '0'), ',') }}<span class="block text-xs">{{ $usd($item->platform_amount_cents) }}</span></td>
                            <td class="px-5 py-3 text-right font-semibold tabular-nums">{{ $usd($item->creator_amount_cents) }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$item->status->value === 'refunded' ? 'refunded' : ($item->earning?->status ?? 'completed')"/></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $items->links() }}</div>
    @endif
</x-layouts.creator>
