@php
    $status = $purchase->status->value;
    [$icon, $title, $text] = match ($status) {
        'paid' => ['check', __('Puanların yüklendi'), __(':points POINT cüzdanına eklendi.', ['points' => number_format($purchase->points, 0, ',', '.')])],
        'failed' => ['x', __('Ödeme tamamlanamadı'), __('Kartından ücret alınmadı ve puan yüklenmedi. Tekrar deneyebilirsin.')],
        'pending' => ['clock', __('Ödeme onayı bekleniyor'), __('Ödeme sağlayıcısından onay geldiğinde puanların otomatik olarak yüklenecek. Bu sayfayı yenileyebilirsin.')],
        default => ['shield', __('İşlem durumu: :status', ['status' => $status]), __('Ayrıntılar için destek ekibiyle iletişime geçebilirsin.')],
    };
@endphp
<x-layouts.app :title="__('Puan yükleme')" :noindex="true">
    <div class="container-page max-w-2xl py-12">
        <x-empty-state :icon="$icon" :title="$title" :description="$text">
            @if ($status === 'paid')
                <a href="{{ route('wallet') }}" class="btn-primary">{{ __('Cüzdanıma git') }}</a>
                <a href="{{ route('products.index') }}" class="btn-secondary">{{ __('Modelleri keşfet') }}</a>
            @elseif ($status === 'pending')
                <a href="{{ route('top-up.result', $purchase) }}" class="btn-primary">{{ __('Yenile') }}</a>
            @else
                <a href="{{ route('top-up.create') }}" class="btn-primary">{{ __('Tekrar dene') }}</a>
            @endif
        </x-empty-state>
        <dl class="card mt-6 divide-y divide-line px-6 text-sm">
            <div class="flex justify-between py-3"><dt class="text-muted">{{ __('Puan') }}</dt><dd class="font-medium tabular-nums">{{ number_format($purchase->points, 0, ',', '.') }} POINT</dd></div>
            <div class="flex justify-between py-3"><dt class="text-muted">{{ __('Tutar') }}</dt><dd class="font-medium tabular-nums">{{ app(\App\Services\Currency\PriceFormatter::class)->format($purchase->charge_amount_minor, $purchase->charge_currency) }}</dd></div>
            @if ($purchase->exchange_rate_micros)
                <div class="flex justify-between py-3"><dt class="text-muted">{{ __('Uygulanan kur') }}</dt><dd class="font-medium tabular-nums">1 USD = {{ number_format($purchase->exchange_rate_micros / 1_000_000, 4, ',', '.') }} TL</dd></div>
            @endif
            <div class="flex justify-between py-3"><dt class="text-muted">{{ __('İşlem no') }}</dt><dd class="font-mono text-xs">{{ $purchase->uuid }}</dd></div>
        </dl>
    </div>
</x-layouts.app>
