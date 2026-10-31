@php $num = fn (int $n) => number_format($n, 0, ',', '.'); @endphp
<x-layouts.admin :title="__('Puan sistemi')">
    <div class="grid grid-cols-2 gap-4 xl:grid-cols-3">
        @foreach ([[__('Toplam yüklenen'), $num($economy['loaded']).' P'], [__('Kullanıcılardaki bakiye'), $num($economy['outstanding']).' P'], [__('Eksi bakiyeli cüzdan'), $num($economy['negative_wallets'])], [__('Yönetici düzeltmesi'), $num($economy['adjustments'])], [__('İade edilen'), $num($economy['refunded']).' P'], [__('İtiraz edilen'), $num($economy['disputed']).' P']] as [$label, $value])
            <div class="card p-5"><p class="text-xs text-muted">{{ $label }}</p><p class="mt-2 font-display text-2xl font-extrabold tabular-nums">{{ $value }}</p></div>
        @endforeach
    </div>

    <section class="card mt-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-end sm:justify-between">
        <div><h2 class="font-bold">{{ __('Minimum yükleme') }}</h2><p class="text-sm text-muted">{{ __('Şu an: :min POINT (= $:usd)', ['min' => $num($minimum), 'usd' => \App\Services\Points\PointConverter::formatCents($minimum)]) }}</p></div>
        <form method="POST" action="{{ route('admin.points.minimum') }}" class="flex gap-2">@csrf @method('PUT')
            <input name="minimum" type="number" min="100" step="100" value="{{ $minimum }}" class="input w-36"><button class="btn-primary shrink-0">{{ __('Kaydet') }}</button>
        </form>
    </section>

    <section class="card mt-6 p-5">
        <h2 class="font-bold">{{ __('Puan paketleri') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ __('Fiyat 1 USD = 100 POINT kuralıyla otomatik hesaplanır. Yüklemelerde kullanılmış paketler silinmez, pasifleştirilir.') }}</p>
        <div class="mt-4 divide-y divide-line">
            @foreach ($packages as $package)
                <form method="POST" action="{{ route('admin.points.packages.update', $package) }}" class="grid items-center gap-2 py-3 sm:grid-cols-[1fr_1fr_8rem_6rem_auto_auto]">
                    @csrf @method('PUT')
                    <input name="name_tr" value="{{ $package->name['tr'] ?? '' }}" class="input py-2" aria-label="{{ __('Ad (TR)') }}" required>
                    <input name="name_en" value="{{ $package->name['en'] ?? '' }}" class="input py-2" aria-label="{{ __('Ad (EN)') }}">
                    <input name="points" type="number" value="{{ $package->points }}" class="input py-2" aria-label="{{ __('Puan') }}" required>
                    <input name="sort_order" type="number" value="{{ $package->sort_order }}" class="input py-2" aria-label="{{ __('Sıra') }}">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($package->is_active) class="accent-katman-600">{{ __('Aktif') }}</label>
                    <span class="flex gap-1"><button class="btn-secondary px-3 py-1.5">{{ __('Kaydet') }}</button><button type="submit" form="delete-package-{{ $package->id }}" class="btn-ghost px-2 py-1.5" aria-label="{{ __('Sil') }}"><x-icon name="x" class="size-4"/></button></span>
                    <span class="text-xs text-subtle sm:col-span-6">${{ \App\Services\Points\PointConverter::formatCents($package->price_usd_cents) }} · {{ trans_choice(':count yükleme|:count yükleme', $package->purchases_count, ['count' => $package->purchases_count]) }}</span>
                </form>
                <form id="delete-package-{{ $package->id }}" method="POST" action="{{ route('admin.points.packages.destroy', $package) }}" class="hidden" x-data @submit="if (! confirm(@js(__('Paket kaldırılsın mı?')))) $event.preventDefault()">@csrf @method('DELETE')</form>
            @endforeach
        </div>
        <form method="POST" action="{{ route('admin.points.packages.store') }}" class="mt-4 grid gap-2 border-t border-line pt-4 sm:grid-cols-[1fr_1fr_8rem_auto]">
            @csrf
            <input name="name_tr" class="input py-2" placeholder="{{ __('Yeni paket adı (TR)') }}" required>
            <input name="name_en" class="input py-2" placeholder="{{ __('Ad (EN)') }}">
            <input name="points" type="number" min="100" class="input py-2" placeholder="{{ __('Puan') }}" required>
            <button class="btn-primary">{{ __('Paket ekle') }}</button>
        </form>
    </section>
</x-layouts.admin>
