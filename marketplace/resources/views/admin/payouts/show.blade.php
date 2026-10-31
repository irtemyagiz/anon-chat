@php
    $usd = fn (int $cents) => '$'.\App\Services\Points\PointConverter::formatCents($cents);
    $fmt = app(\App\Services\Currency\PriceFormatter::class);
    $status = $payoutRequest->status->value;
@endphp
<x-layouts.admin :title="__('Hakediş talebi')">
    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <section class="card p-5">
                <div class="flex items-center justify-between"><span class="font-display text-3xl font-extrabold tabular-nums">{{ $usd($payoutRequest->amount_cents) }}</span><x-status-badge :status="match ($status) { 'paid' => 'paid', 'rejected' => 'rejected', 'approved' => 'completed', default => 'pending' }"/></div>
                <dl class="mt-4 grid gap-x-6 text-sm sm:grid-cols-2">
                    @foreach ([__('Creator') => $payoutRequest->creator->creatorProfile?->store_name.' ('.$payoutRequest->creator->email.')', __('Yöntem') => $payoutRequest->method, __('Ödeme para birimi') => $payoutRequest->currency, __('Talep tarihi') => $payoutRequest->created_at->format('d.m.Y H:i')] as $label => $value)
                        <div class="flex justify-between gap-4 border-b border-line py-2"><dt class="text-muted">{{ $label }}</dt><dd class="text-right">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </section>
            <section class="card p-5">
                <h2 class="font-bold">{{ __('Ödeme bilgileri') }}</h2>
                <p class="mt-1 text-xs text-subtle">{{ __('Bu sayfanın her görüntülenmesi denetim kaydına yazılır.') }}</p>
                <dl class="mt-3 text-sm">
                    @foreach ($payoutRequest->payout_details as $key => $value)
                        <div class="flex justify-between gap-4 border-b border-line py-2"><dt class="text-muted">{{ ['account_holder' => __('Hesap sahibi'), 'iban' => 'IBAN', 'swift' => 'SWIFT/BIC', 'bank_name' => __('Banka'), 'email' => __('E-posta')][$key] ?? $key }}</dt><dd class="font-mono text-right select-all">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </section>
            <section class="card p-5">
                <h2 class="font-bold">{{ __('Satıcı doğrulaması') }}</h2>
                @if ($verification)
                    <p class="mt-2 text-sm">{{ $verification->legal_name }} · {{ $verification->entity_type }} · {{ $verification->country_code }} <x-status-badge :status="match ($verification->status->value) { 'approved' => 'completed', 'rejected' => 'rejected', default => 'pending' }"/></p>
                @else
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ __('Doğrulama yok.') }}</p>
                @endif
            </section>
            @if ($payoutRequest->payout)
                <section class="card p-5 text-sm">
                    <h2 class="font-bold">{{ __('Ödeme kaydı') }}</h2>
                    <p class="mt-2">{{ $fmt->format($payoutRequest->payout->paid_amount_minor, $payoutRequest->payout->paid_currency) }} · {{ __('ref.') }} {{ $payoutRequest->payout->reference }} · {{ $payoutRequest->payout->paid_at->format('d.m.Y H:i') }}@if ($payoutRequest->payout->exchange_rate_micros) · {{ __('kur') }} {{ number_format($payoutRequest->payout->exchange_rate_micros / 1_000_000, 4, ',', '.') }}@endif</p>
                </section>
            @endif
        </div>
        <aside class="space-y-4">
            @if (in_array($status, ['pending', 'under_review'], true))
                <form method="POST" action="{{ route('admin.payouts.decide', $payoutRequest) }}" class="card p-5">@csrf
                    <input type="hidden" name="decision" value="approve"><button class="btn-primary w-full">{{ __('Talebi onayla') }}</button>
                </form>
            @endif
            @if ($status === 'approved')
                <form method="POST" action="{{ route('admin.payouts.decide', $payoutRequest) }}" class="card space-y-3 p-5" x-data @submit="if (! confirm(@js(__('Ödeme yapıldı olarak işaretlensin mi? Bu işlem bakiyeden düşer.')))) $event.preventDefault()">@csrf
                    <h2 class="font-bold">{{ __('Ödendi olarak işaretle') }}</h2>
                    <input type="hidden" name="decision" value="paid">
                    <label class="block text-xs text-muted">{{ __('Ödenen tutar (:currency)', ['currency' => $payoutRequest->currency]) }}<input name="paid_amount" value="{{ \App\Services\Points\PointConverter::formatCents($suggestedMinor) }}" class="input mt-1" required></label>
                    @if ($rate)<label class="block text-xs text-muted">{{ __('Uygulanan kur (USD/TRY)') }}<input name="exchange_rate" value="{{ $rate->formattedRate() }}" class="input mt-1"></label>@endif
                    <label class="block text-xs text-muted">{{ __('Banka / işlem referansı') }}<input name="reference" class="input mt-1" required maxlength="191"></label>
                    <button class="btn-primary w-full">{{ __('Ödendi') }}</button>
                </form>
            @endif
            @if (in_array($status, ['pending', 'under_review', 'approved'], true))
                <form method="POST" action="{{ route('admin.payouts.decide', $payoutRequest) }}" class="card space-y-3 p-5">@csrf
                    <input type="hidden" name="decision" value="reject">
                    <textarea name="note" rows="2" class="input" placeholder="{{ __('Ret gerekçesi (creator görür)') }}" required></textarea>
                    <button class="btn-secondary w-full">{{ __('Reddet ve bakiyeyi serbest bırak') }}</button>
                </form>
            @endif
        </aside>
    </div>
</x-layouts.admin>
