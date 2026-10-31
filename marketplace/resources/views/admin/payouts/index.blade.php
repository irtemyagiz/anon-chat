@php $usd = fn (int $cents) => '$'.\App\Services\Points\PointConverter::formatCents($cents); @endphp
<x-layouts.admin :title="__('Hakedişler')">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        @include('admin.partials.status-tabs', ['route' => 'admin.payouts.index', 'current' => $status, 'tabs' => ['pending' => __('Bekleyen'), 'under_review' => __('İnceleniyor'), 'approved' => __('Onaylandı'), 'paid' => __('Ödendi'), 'rejected' => __('Reddedildi'), 'tumu' => __('Tümü')]])
        <a href="{{ route('admin.payouts.verifications') }}" class="btn-secondary px-3 py-1.5">{{ __('Satıcı doğrulamaları') }}</a>
    </div>
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (['pending' => __('Bekleyen'), 'approved' => __('Ödeme bekleyen'), 'paid' => __('Ödenen'), 'rejected' => __('Reddedilen')] as $key => $label)
            <div class="card p-4"><p class="text-xs text-muted">{{ $label }}</p><p class="mt-1 font-display text-xl font-extrabold tabular-nums">{{ $usd((int) ($totals[$key]->cents ?? 0)) }}</p><p class="text-xs text-subtle">{{ (int) ($totals[$key]->count ?? 0) }} {{ __('talep') }}</p></div>
        @endforeach
    </div>
    @if ($requests->isEmpty())
        <x-empty-state icon="wallet" :title="__('Bu durumda talep yok')"/>
    @else
        <div class="card divide-y divide-line">
            @foreach ($requests as $payoutRequest)
                <a href="{{ route('admin.payouts.show', $payoutRequest) }}" class="flex flex-wrap items-center justify-between gap-3 p-4 text-sm hover:bg-surface-2">
                    <span><span class="font-semibold">{{ $payoutRequest->creator->creatorProfile?->store_name }}</span> <span class="text-muted">· {{ $payoutRequest->creator->email }}</span>
                        <span class="block text-xs text-subtle">{{ $payoutRequest->created_at->format('d.m.Y H:i') }} · {{ $payoutRequest->method }} · {{ $payoutRequest->currency }} · {{ $payoutRequest->maskedAccount() }}</span></span>
                    <span class="flex items-center gap-3"><span class="font-semibold tabular-nums">{{ $usd($payoutRequest->amount_cents) }}</span><x-status-badge :status="match ($payoutRequest->status->value) { 'paid' => 'paid', 'rejected' => 'rejected', 'approved' => 'completed', default => 'pending' }"/></span>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $requests->links() }}</div>
    @endif
</x-layouts.admin>
