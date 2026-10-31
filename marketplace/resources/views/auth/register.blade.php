<x-auth-card :title="__('Hesap oluştur')" :subtitle="__('Modelleri satın al, ücretsiz tasarımları indir, favori tasarımcılarını takip et.')">
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        @include('auth.partials.account-fields')
        @include('auth.partials.terms')
        <button class="btn-primary w-full py-3">{{ __('Kayıt ol') }}</button>
    </form>
    <x-slot:footer>
        {{ __('Zaten hesabın var mı?') }} <a href="{{ route('login') }}" class="font-semibold text-katman-700 hover:underline dark:text-katman-300">{{ __('Giriş yap') }}</a>
        <span class="mx-1">·</span>
        <a href="{{ route('creator.apply') }}" class="font-semibold text-katman-700 hover:underline dark:text-katman-300">{{ __('Creator olarak kayıt ol') }}</a>
    </x-slot:footer>
</x-auth-card>
