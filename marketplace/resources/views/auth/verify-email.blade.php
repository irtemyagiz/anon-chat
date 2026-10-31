<x-auth-card :title="__('E-posta adresini doğrula')" :subtitle="__(':email adresine bir doğrulama bağlantısı gönderdik.', ['email' => auth()->user()->email])">
    <div class="space-y-5 text-sm text-muted">
        <p>{{ __('Puan yükleyebilmek ve satın alma yapabilmek için e-posta adresini doğrulaman gerekiyor. E-posta gelmediyse spam klasörünü kontrol et veya tekrar gönder.') }}</p>
        @if (app()->isLocal() && config('mail.default') === 'log')
            {{-- Yalnızca yerel geliştirme: e-posta gönderilmediği için bağlantı burada gösterilir --}}
            <div class="rounded-xl border border-filament-400/50 bg-filament-400/10 p-3 text-xs text-ink">
                <p class="font-semibold">{{ __('Geliştirme ortamı') }}</p>
                <p class="mt-1">{{ __('E-postalar gönderilmiyor (MAIL_MAILER=log). Doğrulamak için:') }}</p>
                <a href="{{ URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => auth()->id(), 'hash' => sha1(auth()->user()->getEmailForVerification())]) }}" class="mt-2 inline-block font-semibold text-katman-700 underline dark:text-katman-300">{{ __('E-postamı doğrula') }}</a>
            </div>
        @endif
        <form method="POST" action="{{ route('verification.send') }}">@csrf
            <button class="btn-primary w-full py-3"><x-icon name="mail" class="size-4"/>{{ __('Doğrulama bağlantısını tekrar gönder') }}</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="btn-ghost w-full">{{ __('Çıkış yap') }}</button>
        </form>
    </div>
</x-auth-card>
