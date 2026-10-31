<x-layouts.admin :title="$user->full_name">
    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <section class="card p-5">
                <dl class="grid gap-x-6 text-sm sm:grid-cols-2">
                    @foreach ([__('Kullanıcı adı') => '@'.$user->username, __('E-posta') => $user->email.($user->hasVerifiedEmail() ? ' ✓' : ' ('.__('doğrulanmadı').')'), __('Ülke') => config('countries.'.$user->country_code.'.0', $user->country_code ?? '—'), __('Kayıt') => $user->created_at->format('d.m.Y H:i'), __('Son giriş') => $user->last_login_at?->format('d.m.Y H:i') ?? '—', __('Creator') => $user->creatorProfile ? $user->creatorProfile->store_name.' ('.['pending' => __('bekliyor'), 'approved' => __('onaylı'), 'rejected' => __('reddedildi'), 'suspended' => __('askıda')][$user->creatorProfile->status->value].')' : '—', __('2FA') => $user->two_factor_confirmed_at ? __('Etkin') : __('Kapalı')] as $label => $value)
                        <div class="flex justify-between gap-4 border-b border-line py-2"><dt class="text-muted">{{ $label }}</dt><dd class="text-right">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($user->suspension_reason)<p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ __('Askı gerekçesi') }}: {{ $user->suspension_reason }}</p>@endif
            </section>

            <section class="card p-5">
                <div class="flex items-center justify-between"><h2 class="font-bold">{{ __('Puan hareketleri') }}</h2><span class="text-sm">{{ __('Bakiye') }}: <b class="tabular-nums">{{ number_format((int) $wallet->balance, 0, ',', '.') }}</b> · {{ __('Yüklenen') }}: {{ number_format($wallet->total_loaded, 0, ',', '.') }} · {{ __('Harcanan') }}: {{ number_format($wallet->total_spent, 0, ',', '.') }}</span></div>
                <ul class="mt-3 divide-y divide-line text-sm">
                    @forelse ($entries as $entry)
                        <li class="flex items-center justify-between gap-3 py-2"><span><span class="font-medium">{{ $entry->transaction->type->label() }}</span><span class="block text-xs text-subtle">{{ $entry->created_at->format('d.m.Y H:i') }} · {{ $entry->transaction->description }}</span></span><span class="tabular-nums {{ $entry->amount > 0 ? 'text-katman-700 dark:text-katman-300' : '' }}">{{ $entry->amount > 0 ? '+' : '' }}{{ number_format($entry->amount, 0, ',', '.') }}</span></li>
                    @empty
                        <li class="py-2 text-muted">{{ __('Hareket yok.') }}</li>
                    @endforelse
                </ul>
            </section>

            <section class="card p-5">
                <h2 class="font-bold">{{ __('Satın alma geçmişi') }}</h2>
                <ul class="mt-3 divide-y divide-line text-sm">
                    @forelse ($orders as $order)
                        <li class="flex items-center justify-between gap-3 py-2"><span>{{ $order->items->pluck('product_title')->implode(', ') }}<span class="block text-xs text-subtle">{{ $order->number }} · {{ $order->created_at->format('d.m.Y H:i') }}</span></span><span class="tabular-nums">{{ number_format($order->total_points, 0, ',', '.') }} P <x-status-badge :status="$order->status"/></span></li>
                    @empty
                        <li class="py-2 text-muted">{{ __('Satın alma yok.') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card space-y-3 p-5" x-data="{ status: '{{ $user->status->value }}' }">
                <h2 class="font-bold">{{ __('Hesap durumu') }}</h2>
                <form method="POST" action="{{ route('admin.users.status', $user) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <select name="status" x-model="status" class="input">
                        <option value="active">{{ __('Aktif') }}</option><option value="suspended">{{ __('Askıya alınmış') }}</option><option value="banned">{{ __('Engellenmiş') }}</option>
                    </select>
                    <input x-show="status !== 'active'" name="reason" class="input" placeholder="{{ __('Gerekçe') }}" maxlength="255">
                    <button class="btn-secondary w-full">{{ __('Durumu kaydet') }}</button>
                </form>
            </section>

            @if (auth()->user()->isAdmin())
                <section class="card space-y-3 p-5">
                    <h2 class="font-bold">{{ __('Roller') }}</h2>
                    <form method="POST" action="{{ route('admin.users.roles', $user) }}" class="space-y-2">
                        @csrf @method('PUT')
                        @foreach (['admin' => __('Yönetici'), 'moderator' => __('Moderatör')] as $role => $label)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="roles[]" value="{{ $role }}" @checked($user->hasRole($role)) class="accent-katman-600">{{ $label }}</label>
                        @endforeach
                        <p class="text-xs text-subtle">{{ __('Creator rolü başvuru onayıyla verilir. Her değişiklik denetim kaydına yazılır.') }}</p>
                        <button class="btn-secondary w-full">{{ __('Rolleri kaydet') }}</button>
                    </form>
                </section>
            @endif

            @can('wallets.adjust')
                <section class="card space-y-3 p-5">
                    <h2 class="font-bold">{{ __('Puan düzeltmesi') }}</h2>
                    <form method="POST" action="{{ route('admin.users.adjust', $user) }}" class="space-y-3" x-data @submit="if (! confirm(@js(__('Puan düzeltmesi kaydedilsin mi? Bu işlem geri alınamaz, yalnızca ters kayıtla düzeltilebilir.')))) $event.preventDefault()">
                        @csrf
                        <input type="hidden" name="request_id" value="{{ Str::uuid() }}">
                        <input name="points" type="number" class="input" placeholder="{{ __('Örn. 250 veya -100') }}" required>
                        <textarea name="reason" rows="2" class="input" placeholder="{{ __('Gerekçe (en az 10 karakter, denetim kaydına yazılır)') }}" required minlength="10"></textarea>
                        <button class="btn-primary w-full">{{ __('Düzeltmeyi kaydet') }}</button>
                    </form>
                </section>
            @endcan
        </aside>
    </div>
</x-layouts.admin>
