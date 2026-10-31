<x-layouts.app :title="__('Puan yükle')" :noindex="true">
    <div class="container-page max-w-5xl py-12">
        <p class="eyebrow">{{ __('Puan cüzdanı') }}</p>
        <h1 class="mt-1 text-3xl font-extrabold">{{ __('Puan yükle') }}</h1>
        <p class="mt-2 text-muted">{{ __('1 USD = 100 POINT. Minimum yükleme :min POINT. Ödeme para biriminiz: :currency.', ['min' => number_format($minimum, 0, ',', '.'), 'currency' => $currency === 'TRY' ? 'TL' : 'USD']) }}</p>

        @if ($shortfall > 0)
            <div class="mt-6 rounded-xl border border-filament-400/50 bg-filament-400/10 px-4 py-3 text-sm">
                {{ __('Satın almak istediğin model için :points POINT daha gerekiyor.', ['points' => number_format($shortfall, 0, ',', '.')]) }}
                @if ($suggested) {{ __('Önerilen paket: :name.', ['name' => $suggested->name[app()->getLocale()] ?? $suggested->code]) }} @endif
            </div>
        @endif

        @error('package')
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ $message }}</div>
        @enderror

        @if ($providers->isEmpty())
            <x-empty-state class="mt-8" icon="shield" :title="__('Ödeme yöntemi henüz etkin değil')" :description="__('Puan yükleme, ödeme sağlayıcısı entegrasyonu tamamlandığında açılacak.')"/>
        @else
            <form method="POST" action="{{ route('top-up.store') }}" class="mt-8" x-data="{ selected: '{{ old('package', $suggested?->id ?? $packages->first()['package']->id ?? '') }}' }">
                @csrf
                <fieldset>
                    <legend class="sr-only">{{ __('Paket seçin') }}</legend>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        @foreach ($packages as ['package' => $package, 'price' => $price])
                            <label class="card relative cursor-pointer p-5 transition has-[:checked]:border-katman-500 has-[:checked]:ring-2 has-[:checked]:ring-katman-500/30">
                                <input type="radio" name="package" value="{{ $package->id }}" x-model="selected" class="sr-only">
                                @if ($suggested?->is($package))
                                    <span class="absolute -top-2.5 left-4 rounded-full bg-filament-400 px-2 py-0.5 text-[0.65rem] font-bold text-katman-950">{{ __('Önerilen') }}</span>
                                @endif
                                <span class="block text-sm font-semibold text-muted">{{ $package->name[app()->getLocale()] ?? $package->code }}</span>
                                <span class="mt-2 block font-display text-3xl font-extrabold tabular-nums">{{ number_format($package->totalPoints(), 0, ',', '.') }}</span>
                                <span class="text-xs font-bold tracking-wide text-katman-700 dark:text-katman-300">POINT</span>
                                <span class="mt-4 block border-t border-line pt-3 text-lg font-bold tabular-nums">{{ $price['formatted'] }}</span>
                                @if ($currency !== 'USD')<span class="text-xs text-subtle">${{ \App\Services\Points\PointConverter::formatCents($package->price_usd_cents) }}</span>@endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <label class="flex-1">
                        <span class="label">{{ __('Ödeme yöntemi') }}</span>
                        <select name="provider" class="input sm:max-w-xs">
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->code }}">{{ $provider->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn-primary px-8 py-3">{{ __('Ödemeye geç') }} <x-icon name="arrow-right" class="size-4"/></button>
                </div>
                <p class="mt-4 text-xs text-subtle">{{ __('TL tutarı ödeme anındaki kurla sabitlenir. Puanlar ödeme sağlayıcısı ödemeyi onayladıktan sonra hesabına yüklenir. Puanlar nakde çevrilemez ve başka kullanıcılara aktarılamaz.') }}</p>
            </form>
        @endif
    </div>
</x-layouts.app>
