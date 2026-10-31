<x-auth-card :title="__('Yeni şifre belirle')">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" type="email" :label="__('E-posta')" :value="$email" autocomplete="email" required/>
        <x-field name="password" type="password" :label="__('Yeni şifre')" autocomplete="new-password" required/>
        <x-field name="password_confirmation" type="password" :label="__('Yeni şifre (tekrar)')" autocomplete="new-password" required/>
        <button class="btn-primary w-full py-3">{{ __('Şifreyi güncelle') }}</button>
    </form>
</x-auth-card>
