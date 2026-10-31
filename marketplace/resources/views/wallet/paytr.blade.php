<x-layouts.app :title="__('Güvenli ödeme')" :noindex="true">
    <div class="container-page max-w-3xl py-10">
        <h1 class="font-display text-2xl font-bold">{{ __('Güvenli ödeme') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __(':points POINT yüklemesi için ödeme PayTR güvenli ödeme sayfasında yapılır. Kart bilgilerin sitemize iletilmez ve saklanmaz.', ['points' => number_format($purchase->points, 0, ',', '.')]) }}</p>
        <div class="card mt-6 overflow-hidden">
            <iframe src="{{ $iframeUrl }}" title="{{ __('PayTR ödeme formu') }}" class="h-[46rem] w-full border-0" allow="payment" referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
        <p class="mt-4 text-xs text-subtle">{{ __('Ödeme tamamlandığında puanlar, PayTR onayı geldikten sonra otomatik olarak yüklenir.') }} <a href="{{ route('top-up.result', $purchase) }}" class="underline">{{ __('İşlem durumunu gör') }}</a></p>
    </div>
</x-layouts.app>
