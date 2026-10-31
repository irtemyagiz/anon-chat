<x-layouts.admin :title="__('Döviz kuru')">
    <div class="grid gap-4 md:grid-cols-3">
        <div class="card p-5 md:col-span-1">
            <p class="text-xs text-muted">{{ __('Güncel USD/TRY') }}</p>
            <p class="mt-2 font-display text-3xl font-extrabold tabular-nums">{{ $current?->formattedRate() ?? '—' }}</p>
            <p class="mt-1 text-xs text-subtle">{{ $current ? __(':source · :time', ['source' => $current->source, 'time' => $current->fetched_at->format('d.m.Y H:i')]) : __('Henüz kur yok') }}</p>
            @if ($current && $current->fetched_at->lt(now()->subHours($maxAge)))
                <p class="mt-2 text-xs font-semibold text-red-600 dark:text-red-400">{{ __(':hours saatten eski: TL ile ödeme engelli.', ['hours' => $maxAge]) }}</p>
            @endif
        </div>
        <div class="card space-y-3 p-5">
            <h2 class="font-bold">{{ __('Otomatik güncelleme') }}</h2>
            <p class="text-sm text-muted">{{ __('Sağlayıcı: :provider · her :minutes dakikada bir', ['provider' => $provider, 'minutes' => $interval]) }}</p>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.exchange-rates.auto') }}">@csrf<button class="btn-secondary px-3 py-1.5">{{ $autoUpdate ? __('Otomatiği kapat') : __('Otomatiği aç') }}</button></form>
                <form method="POST" action="{{ route('admin.exchange-rates.refresh') }}">@csrf<button class="btn-primary px-3 py-1.5">{{ __('Şimdi güncelle') }}</button></form>
            </div>
            <p class="text-xs"><x-status-badge :status="$autoUpdate ? 'completed' : 'unpublished'"/></p>
        </div>
        <div class="card space-y-3 p-5">
            <h2 class="font-bold">{{ __('Manuel kur') }}</h2>
            <p class="text-xs text-muted">{{ __('Manuel kur, bir sonraki otomatik güncellemeye kadar geçerlidir. Kalıcı olması için otomatiği kapatın.') }}</p>
            <form method="POST" action="{{ route('admin.exchange-rates.manual') }}" class="flex gap-2" x-data @submit="if (! confirm(@js(__('Manuel kur kaydedilsin mi? Tüm TL fiyatları değişecek.')))) $event.preventDefault()">@csrf
                <input name="rate" class="input" placeholder="41.8500" required><button class="btn-primary shrink-0">{{ __('Kaydet') }}</button>
            </form>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card p-5">
            <h2 class="font-bold">{{ __('Kur geçmişi') }}</h2>
            <ul class="mt-3 divide-y divide-line text-sm">
                @forelse ($history as $rate)
                    <li class="flex items-center justify-between py-2"><span class="tabular-nums">{{ $rate->formattedRate() }}</span><span class="text-xs text-muted">{{ $rate->is_manual ? __('Manuel').' ('.$rate->setter?->username.')' : $rate->source }} · {{ $rate->fetched_at->format('d.m.Y H:i') }}</span></li>
                @empty
                    <li class="py-2 text-muted">{{ __('Kayıt yok.') }}</li>
                @endforelse
            </ul>
            <div class="mt-4">{{ $history->links() }}</div>
        </section>
        <section class="card p-5">
            <h2 class="font-bold">{{ __('Sağlayıcı kayıtları') }}</h2>
            <ul class="mt-3 divide-y divide-line text-sm">
                @forelse ($logs as $log)
                    <li class="py-2"><div class="flex items-center justify-between"><x-status-badge :status="$log->succeeded ? 'completed' : 'failed'"/><span class="text-xs text-muted">{{ $log->created_at->format('d.m.Y H:i') }} · {{ $log->duration_ms }} ms</span></div>@if ($log->error_message)<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ Str::limit($log->error_message, 160) }}</p>@endif</li>
                @empty
                    <li class="py-2 text-muted">{{ __('Kayıt yok.') }}</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.admin>
