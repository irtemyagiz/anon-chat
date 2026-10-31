<x-auth-card :title="__('Doğrulama kodu')" :subtitle="__('Yönetim paneline devam etmek için doğrulama uygulamanızdaki kodu girin.')">
    <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="space-y-4" x-data="{ recovery: false }">
        @csrf
        <div x-show="! recovery">
            <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="input text-center font-mono text-lg tracking-[0.4em]" placeholder="000000" autofocus>
        </div>
        <div x-show="recovery" x-cloak>
            <input name="recovery_code" class="input text-center font-mono" placeholder="XXXXX-XXXXX">
        </div>
        @error('code')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        <button class="btn-primary w-full">{{ __('Doğrula') }}</button>
        <button type="button" @click="recovery = ! recovery" class="btn-ghost w-full text-xs" x-text="recovery ? @js(__('Doğrulama kodu kullan')) : @js(__('Kurtarma kodu kullan'))"></button>
    </form>
</x-auth-card>
