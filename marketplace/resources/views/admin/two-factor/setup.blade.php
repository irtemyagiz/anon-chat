<x-auth-card :title="__('İki aşamalı doğrulama')" :subtitle="__('Yönetim paneline erişmek için iki aşamalı doğrulama zorunludur.')">
    @if ($recoveryCodes)
        <div class="space-y-4 text-sm">
            <p class="font-semibold text-katman-700 dark:text-katman-300">{{ __('İki aşamalı doğrulama etkinleştirildi.') }}</p>
            <p class="text-muted">{{ __('Kurtarma kodlarını güvenli bir yere kaydedin. Telefonunuza erişemezseniz bu kodlarla giriş yapabilirsiniz; her kod bir kez kullanılır ve bu sayfa bir daha gösterilmez.') }}</p>
            <div class="grid grid-cols-2 gap-2 rounded-xl bg-surface-2 p-4 font-mono text-sm">
                @foreach ($recoveryCodes as $code)<span>{{ $code }}</span>@endforeach
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn-primary w-full">{{ __('Kodları kaydettim, panele git') }}</a>
        </div>
    @else
        <ol class="space-y-5 text-sm">
            <li>
                <p class="font-semibold">1. {{ __('Doğrulama uygulamasıyla QR kodu okutun') }}</p>
                <p class="mt-1 text-muted">{{ __('Google Authenticator, Microsoft Authenticator, 1Password veya benzeri bir uygulama kullanabilirsiniz.') }}</p>
                <div x-data="qrCode(@js($uri))" class="mt-3 flex justify-center rounded-xl bg-white p-4"><canvas x-ref="canvas" aria-label="{{ __('QR kod') }}"></canvas></div>
                <p class="mt-3 text-xs text-muted">{{ __('QR okutamıyorsanız bu anahtarı elle girin:') }}</p>
                <p class="mt-1 rounded-lg bg-surface-2 px-3 py-2 font-mono text-xs break-all select-all">{{ trim(chunk_split($secret, 4, ' ')) }}</p>
            </li>
            <li>
                <p class="font-semibold">2. {{ __('Uygulamadaki 6 haneli kodu girin') }}</p>
                <form method="POST" action="{{ route('admin.two-factor.confirm') }}" class="mt-3 space-y-3">
                    @csrf
                    <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="input text-center font-mono text-lg tracking-[0.4em]" placeholder="000000" required autofocus>
                    @error('code')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    <button class="btn-primary w-full">{{ __('Doğrula ve etkinleştir') }}</button>
                </form>
            </li>
        </ol>
    @endif
</x-auth-card>
