<x-auth-card :title="__('Giriş yap')" :subtitle="__('Tekrar hoş geldin.')">
    @if (session('status'))<p class="mb-4 text-sm text-katman-700 dark:text-katman-300">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <x-field name="email" type="email" :label="__('E-posta')" autocomplete="email" required autofocus/>
        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="label">{{ __('Şifre') }}</label>
                <a href="{{ route('password.request') }}" class="mb-1.5 text-xs font-medium text-katman-700 hover:underline dark:text-katman-300">{{ __('Şifremi unuttum') }}</a>
            </div>
            <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
        </div>
        <label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="remember" value="1" class="accent-katman-600">{{ __('Beni hatırla') }}</label>
        <button class="btn-primary w-full py-3">{{ __('Giriş yap') }}</button>
    </form>
    <x-slot:footer>{{ __('Hesabın yok mu?') }} <a href="{{ route('register') }}" class="font-semibold text-katman-700 hover:underline dark:text-katman-300">{{ __('Kayıt ol') }}</a></x-slot:footer>
</x-auth-card>
