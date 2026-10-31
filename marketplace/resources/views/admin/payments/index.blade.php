@php $fmt = app(\App\Services\Currency\PriceFormatter::class); @endphp
<x-layouts.admin :title="__('Ödemeler')">
    <section class="card mb-6 p-5">
        <div class="flex items-center justify-between"><h2 class="font-bold">{{ __('Ödeme sağlayıcıları') }}</h2><a href="{{ route('admin.payments.webhooks') }}" class="btn-ghost px-3 py-1.5">{{ __('Webhook kayıtları') }} →</a></div>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($providers as $provider)
                <div class="rounded-xl border border-line p-4">
                    <div class="flex items-center justify-between"><span class="font-semibold">{{ $provider->name }}</span><x-status-badge :status="$provider->is_enabled ? 'completed' : 'unpublished'"/></div>
                    <p class="mt-1 text-xs text-muted">{{ implode(', ', $provider->supported_currencies) }} · {{ $provider->mode }}</p>
                    @php $gatewayManager = app(\App\Services\Payments\PaymentGatewayManager::class); @endphp
                    @if (! config("marketplace.payments.gateways.{$provider->code}"))
                        <p class="mt-1 text-xs text-subtle">{{ __('Entegrasyon henüz kurulmadı') }}</p>
                    @elseif (! $gatewayManager->isConfigured($provider->code))
                        <p class="mt-1 text-xs text-amber-700 dark:text-filament-300">{{ __('.env dosyasında eksik: :vars', ['vars' => implode(', ', $gatewayManager->missingSettings($provider->code))]) }}</p>
                    @endif
                    @if ($provider->code !== 'sandbox')
                        <p class="mt-1 font-mono text-[0.65rem] break-all text-subtle">{{ __('Bildirim adresi') }}: {{ route('webhooks.payments', $provider->code) }}</p>
                    @endif
                    <form method="POST" action="{{ route('admin.payments.providers.toggle', $provider) }}" class="mt-3">@csrf<button class="btn-secondary w-full px-3 py-1.5 text-xs">{{ $provider->is_enabled ? __('Kapat') : __('Aç') }}</button></form>
                </div>
            @endforeach
        </div>
    </section>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        @include('admin.partials.status-tabs', ['route' => 'admin.payments.index', 'current' => $status ?? '', 'tabs' => ['' => __('Tümü'), 'paid' => __('Ödendi'), 'pending' => __('Bekliyor'), 'failed' => __('Başarısız'), 'refunded' => __('İade'), 'disputed' => __('İtiraz')]])
        <form method="GET" class="mb-4 flex gap-2"><input name="q" value="{{ request('q') }}" class="input py-1.5" placeholder="{{ __('İşlem no veya e-posta') }}"><button class="btn-secondary py-1.5">{{ __('Ara') }}</button></form>
    </div>
    <div class="card overflow-x-auto">
        <table class="w-full min-w-[52rem] text-sm">
            <thead class="border-b border-line text-left text-xs text-muted uppercase"><tr><th class="px-4 py-3">{{ __('Tarih') }}</th><th class="px-4 py-3">{{ __('Kullanıcı') }}</th><th class="px-4 py-3 text-right">{{ __('Puan') }}</th><th class="px-4 py-3 text-right">{{ __('Tutar') }}</th><th class="px-4 py-3">{{ __('Sağlayıcı') }}</th><th class="px-4 py-3">{{ __('Durum') }}</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($purchases as $purchase)
                    <tr x-data="{ refund: false }">
                        <td class="px-4 py-3 whitespace-nowrap text-muted">{{ $purchase->created_at->format('d.m.Y H:i') }}<span class="block font-mono text-[0.65rem] text-subtle">{{ $purchase->uuid }}</span></td>
                        <td class="px-4 py-3">{{ $purchase->user->email }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($purchase->points, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $fmt->format($purchase->charge_amount_minor, $purchase->charge_currency) }}@if ($purchase->exchange_rate_micros)<span class="block text-[0.65rem] text-subtle">{{ __('kur') }} {{ number_format($purchase->exchange_rate_micros / 1_000_000, 4, ',', '.') }}</span>@endif</td>
                        <td class="px-4 py-3 text-muted">{{ $purchase->provider }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$purchase->status"/>@if ($purchase->failure_reason)<span class="block text-[0.65rem] text-red-600">{{ $purchase->failure_reason }}</span>@endif</td>
                        <td class="px-4 py-3 text-right">
                            @if ($purchase->status->value === 'paid')
                                <button type="button" @click="refund = ! refund" class="btn-ghost px-2 py-1 text-xs">{{ __('İade et') }}</button>
                                <form x-show="refund" x-cloak method="POST" action="{{ route('admin.payments.refund', $purchase) }}" class="mt-2 flex gap-1">@csrf<input name="reason" class="input py-1 text-xs" placeholder="{{ __('Gerekçe') }}" required minlength="5"><button class="btn-primary px-2 py-1 text-xs">{{ __('Onayla') }}</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-muted">{{ __('Ödeme kaydı yok.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $purchases->links() }}</div>

    @if ($disputes->isNotEmpty())
        <section class="card mt-6 p-5">
            <h2 class="font-bold">{{ __('Ödeme itirazları (chargeback)') }}</h2>
            <ul class="mt-3 divide-y divide-line text-sm">
                @foreach ($disputes as $dispute)
                    <li class="flex justify-between gap-3 py-2"><span>{{ $dispute->pointPurchase->user->email }} · {{ $dispute->reason ?? '—' }}</span><span class="text-muted">{{ $fmt->format($dispute->amount_minor, $dispute->currency) }} · {{ number_format($dispute->points_reversed, 0, ',', '.') }} P {{ __('geri alındı') }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.admin>
