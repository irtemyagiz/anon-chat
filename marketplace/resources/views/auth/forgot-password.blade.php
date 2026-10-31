<x-auth-card :title="__('Şifreni sıfırla')" :subtitle="__('E-posta adresini gir, sana sıfırlama bağlantısı gönderelim.')">
    @if (session('status'))<p class="mb-4 text-sm text-katman-700 dark:text-katman-300">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-field name="email" type="email" :label="__('E-posta')" autocomplete="email" required autofocus/>
        <button class="btn-primary w-full py-3">{{ __('Bağlantı gönder') }}</button>
    </form>
    <x-slot:footer><a href="{{ route('login') }}" class="font-semibold text-katman-700 hover:underline dark:text-katman-300">{{ __('Girişe dön') }}</a></x-slot:footer>
</x-auth-card>
