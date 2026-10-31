<x-layouts.account :title="__('Profil ve ayarlar')">
    <form method="POST" action="{{ route('account.settings.update') }}" enctype="multipart/form-data" class="card space-y-5 p-6">
        @csrf @method('PUT')
        <div class="flex items-center gap-4">
            @if ($user->avatar_path)
                <img src="{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url($user->avatar_path) }}" alt="" class="size-16 rounded-full object-cover">
            @else
                <span class="flex size-16 items-center justify-center rounded-full bg-katman-100 font-display text-2xl font-bold text-katman-800 dark:bg-katman-900 dark:text-katman-200">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}</span>
            @endif
            <div class="flex-1">
                <label class="label" for="avatar">{{ __('Profil fotoğrafı') }}</label>
                <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm">
                @error('avatar')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="first_name" :label="__('Ad')" :value="$user->first_name" required/>
            <x-field name="last_name" :label="__('Soyad')" :value="$user->last_name" required/>
        </div>
        <p class="text-sm text-muted">{{ __('Kullanıcı adı') }}: <span class="font-medium text-ink">{{ '@'.$user->username }}</span> · {{ __('E-posta') }}: <span class="font-medium text-ink">{{ $user->email }}</span></p>
        <div>
            <label for="bio" class="label">{{ __('Hakkımda') }}</label>
            <textarea id="bio" name="bio" rows="3" maxlength="500" class="input">{{ old('bio', $user->bio) }}</textarea>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="label" for="country_code">{{ __('Fatura ülkesi') }}</label>
                <select id="country_code" name="country_code" class="input" required>
                    @foreach (config('countries') as $code => $names)<option value="{{ $code }}" @selected(old('country_code', $user->country_code) === $code)>{{ $names[0] }}</option>@endforeach
                </select>
                <p class="mt-1.5 text-xs text-subtle">{{ __('Ödeme para birimi bu ülkeye göre belirlenir.') }}</p>
            </div>
            <div>
                <label class="label" for="display_currency">{{ __('Fiyat gösterimi') }}</label>
                <select id="display_currency" name="display_currency" class="input">
                    <option value="">{{ __('Ülkeye göre otomatik') }}</option>
                    <option value="TRY" @selected($user->display_currency === 'TRY')>TL (₺)</option>
                    <option value="USD" @selected($user->display_currency === 'USD')>USD ($)</option>
                </select>
            </div>
        </div>
        <button class="btn-primary">{{ __('Kaydet') }}</button>
    </form>

    <form method="POST" action="{{ route('account.settings.password') }}" class="card mt-6 space-y-5 p-6">
        @csrf @method('PUT')
        <h2 class="font-bold">{{ __('Şifre değiştir') }}</h2>
        <x-field name="current_password" type="password" :label="__('Mevcut şifre')" autocomplete="current-password" required/>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="password" type="password" :label="__('Yeni şifre')" autocomplete="new-password" required/>
            <x-field name="password_confirmation" type="password" :label="__('Yeni şifre (tekrar)')" autocomplete="new-password" required/>
        </div>
        <button class="btn-secondary">{{ __('Şifreyi güncelle') }}</button>
    </form>

    <form method="POST" action="{{ route('account.notifications.preferences') }}" class="card mt-6 space-y-4 p-6">
        @csrf @method('PUT')
        <div>
            <h2 class="font-bold">{{ __('E-posta bildirimleri') }}</h2>
            <p class="text-sm text-muted">{{ __('Tüm bildirimler site içinde görünür. Hangilerinin e-posta ile de gönderileceğini seç.') }}</p>
        </div>
        @foreach (\App\Notifications\MarketplaceNotification::CATEGORIES as $category => $definition)
            @continue($category === 'admin' && ! $user->can('admin.access'))
            @continue($category === 'sales' || $category === 'payouts' ? $user->creatorProfile === null : false)
            <label class="flex items-center gap-3 text-sm">
                <input type="checkbox" name="mail[]" value="{{ $category }}" @checked(\App\Notifications\MarketplaceNotification::wantsMail($user, $category)) class="accent-katman-600">
                {{ __($definition['label']) }}
            </label>
        @endforeach
        <p class="text-xs text-subtle">{{ __('Hesap güvenliği e-postaları (doğrulama, şifre sıfırlama) her zaman gönderilir.') }}</p>
        <button class="btn-secondary">{{ __('Tercihleri kaydet') }}</button>
    </form>
</x-layouts.account>
