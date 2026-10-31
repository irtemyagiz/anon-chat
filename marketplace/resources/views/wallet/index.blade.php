<x-layouts.app :title="__('Cüzdanım')" :noindex="true">
    <div class="container-page py-12">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Puan cüzdanı') }}</p>
                <h1 class="mt-1 text-3xl font-extrabold">{{ __('Cüzdanım') }}</h1>
            </div>
            <a href="{{ route('top-up.create') }}" class="btn-primary"><x-icon name="coins" class="size-4"/>{{ __('Puan yükle') }}</a>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <div class="card relative overflow-hidden bg-katman-900 p-6 text-white sm:col-span-1 dark:bg-katman-950">
                <div class="layer-lines pointer-events-none absolute inset-0 opacity-40"></div>
                <p class="relative text-sm text-katman-100">{{ __('Kullanılabilir puan') }}</p>
                <p class="relative mt-2 font-display text-4xl font-extrabold tabular-nums">{{ number_format($wallet->availableBalance(), 0, ',', '.') }}</p>
                <p class="relative mt-1 text-sm text-katman-200">≈ {{ app(\App\Services\Currency\PriceFormatter::class)->present($wallet->availableBalance(), app(\App\Services\Currency\CurrencyResolver::class)->displayCurrency(auth()->user(), request()))['formatted'] }}</p>
            </div>
            <div class="card p-6">
                <p class="text-sm text-muted">{{ __('Toplam yüklenen') }}</p>
                <p class="mt-2 font-display text-2xl font-bold tabular-nums">{{ number_format($wallet->total_loaded, 0, ',', '.') }} <span class="text-sm text-muted">POINT</span></p>
            </div>
            <div class="card p-6">
                <p class="text-sm text-muted">{{ __('Toplam harcanan') }}</p>
                <p class="mt-2 font-display text-2xl font-bold tabular-nums">{{ number_format($wallet->total_spent, 0, ',', '.') }} <span class="text-sm text-muted">POINT</span></p>
            </div>
        </div>
        @if ((int) $wallet->balance < 0)
            <p class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ __('Bir ödeme itirazı nedeniyle bakiyen eksiye düştü (:points POINT). Yeni yüklemeler önce bu tutarı kapatır.', ['points' => number_format((int) $wallet->balance, 0, ',', '.')]) }}</p>
        @endif

        <h2 class="mt-12 mb-4 text-xl font-bold">{{ __('İşlem geçmişi') }}</h2>
        @if ($entries->isEmpty())
            <x-empty-state icon="wallet" :title="__('Henüz işlem yok')" :description="__('Puan yüklediğinde ve model satın aldığında hareketler burada listelenir.')">
                <a href="{{ route('top-up.create') }}" class="btn-primary">{{ __('İlk yüklemeni yap') }}</a>
            </x-empty-state>
        @else
            <div class="card overflow-x-auto">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead class="border-b border-line text-left text-xs text-muted uppercase">
                        <tr><th class="px-5 py-3 font-semibold">{{ __('Tarih') }}</th><th class="px-5 py-3 font-semibold">{{ __('İşlem') }}</th><th class="px-5 py-3 text-right font-semibold">{{ __('Tutar') }}</th><th class="px-5 py-3 text-right font-semibold">{{ __('Bakiye') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="px-5 py-3 whitespace-nowrap text-muted">{{ $entry->created_at->format('d.m.Y H:i') }}</td>
                                <td class="px-5 py-3">
                                    <span class="font-medium">{{ __($entry->transaction->type->label()) }}</span>
                                    @if ($entry->transaction->description && app()->getLocale() === 'tr')<span class="block text-xs text-subtle">{{ $entry->transaction->description }}</span>@endif
                                    <span class="block font-mono text-[0.7rem] text-subtle">{{ Str::limit($entry->transaction->reference, 13, '') }}</span>
                                </td>
                                <td @class(['px-5 py-3 text-right font-semibold tabular-nums whitespace-nowrap', 'text-katman-700 dark:text-katman-300' => $entry->amount > 0])>{{ $entry->amount > 0 ? '+' : '' }}{{ number_format($entry->amount, 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right text-muted tabular-nums">{{ number_format($entry->balance_after, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $entries->links() }}</div>
        @endif
    </div>
</x-layouts.app>
