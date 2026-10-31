<x-layouts.app :title="__('Test ödemesi')" :noindex="true">
    <div class="container-page max-w-lg py-12">
        <div class="mb-6 rounded-xl border-2 border-dashed border-filament-500 bg-filament-400/10 px-4 py-3 text-sm">
            <p class="font-bold">{{ __('TEST ÖDEME SAYFASI') }}</p>
            <p class="mt-1 text-muted">{{ __('Bu sayfa yalnızca geliştirme ortamında görünür ve gerçek para çekmez. Gerçek ödeme sağlayıcısı (iyzico, PayTR…) bağlandığında kullanıcı onların güvenli ödeme sayfasına yönlendirilir.') }}</p>
        </div>
        <div class="card p-6">
            <p class="text-sm text-muted">{{ __('Ödenecek tutar') }}</p>
            <p class="mt-1 font-display text-3xl font-extrabold tabular-nums">{{ app(\App\Services\Currency\PriceFormatter::class)->format($purchase->charge_amount_minor, $purchase->charge_currency) }}</p>
            <p class="mt-1 text-sm text-muted">{{ number_format($purchase->points, 0, ',', '.') }} POINT</p>

            @if ($purchase->status->value === 'pending')
                <div class="mt-6 grid gap-3">
                    <form method="POST" action="{{ route('sandbox.complete', $purchase) }}">@csrf
                        <input type="hidden" name="outcome" value="success">
                        <button class="btn-primary w-full py-3">{{ __('Ödemeyi başarılı tamamla') }}</button>
                    </form>
                    <form method="POST" action="{{ route('sandbox.complete', $purchase) }}">@csrf
                        <input type="hidden" name="outcome" value="failure">
                        <button class="btn-secondary w-full">{{ __('Ödemeyi başarısız yap') }}</button>
                    </form>
                </div>
            @else
                <a href="{{ route('top-up.result', $purchase) }}" class="btn-primary mt-6 w-full">{{ __('Sonucu görüntüle') }}</a>
            @endif
        </div>
    </div>
</x-layouts.app>
