<x-layouts.app :title="__('Tasarımlarını Paylaş, Kazanmaya Başla')" :description="__('Katman creator programına katıl: mağazanı aç, 3D modellerini sat, her satıştan kazanç elde et.')">
    <div class="container-page grid gap-10 py-12 lg:grid-cols-[0.9fr_1.1fr] lg:py-16">
        <div class="lg:pt-6">
            <p class="eyebrow">{{ __('Creator programı') }}</p>
            <h1 class="mt-3 text-4xl font-extrabold sm:text-5xl">{{ __('Tasarımlarını Paylaş, Kazanmaya Başla') }}</h1>
            <p class="mt-5 text-lg text-muted">{{ __('Kendi mağazanı aç, modellerini yükle, fiyatını USD olarak belirle. Alıcılar puanla öder, sen kazancını hakediş olarak alırsın.') }}</p>
            <ul class="mt-8 space-y-4">
                @foreach ([
                    [__('Kendi mağazan'), __('Logo, kapak görseli, açıklama ve sosyal bağlantılarla sana özel vitrin.')],
                    [__('Esnek fiyatlandırma'), __('Fiyatı USD girersin; 3,99 USD = 399 POINT. Ücretsiz model de yayınlayabilirsin.')],
                    [__('Şeffaf kazanç'), __('Her satışın komisyonu ve senin payın ayrı ayrı görünür. Hakedişini düzenli talep edebilirsin.')],
                    [__('Aynı hesapla alışveriş'), __('Creator hesabın aynı zamanda alıcı hesabıdır; başka tasarımcıların modellerini de alabilirsin.')],
                ] as [$title, $text])
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-katman-600 text-white"><x-icon name="check" class="size-3.5"/></span>
                        <span><span class="block font-semibold text-ink">{{ $title }}</span><span class="text-sm text-muted">{{ $text }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card p-6 sm:p-8">
            <form method="POST" action="{{ route('creator.apply') }}" class="space-y-5">
                @csrf
                @guest
                    <h2 class="text-lg font-bold">{{ __('Hesap bilgileri') }}</h2>
                    @include('auth.partials.account-fields', ['countryRequired' => true])
                    <div class="border-t border-line pt-5"></div>
                @else
                    <p class="rounded-xl bg-surface-2 px-4 py-3 text-sm text-muted">{{ __(':name olarak giriş yaptın. Hesabın creator hesabına yükseltilecek.', ['name' => auth()->user()->full_name]) }}</p>
                @endguest
                <h2 class="text-lg font-bold">{{ __('Mağaza bilgileri') }}</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="designer_name" :label="__('Tasarımcı adı')" required/>
                    <x-field name="store_name" :label="__('Mağaza adı')" required/>
                </div>
                <div>
                    <label for="description" class="label">{{ __('Profil açıklaması') }}</label>
                    <textarea id="description" name="description" rows="4" maxlength="1000" class="input" placeholder="{{ __('Ne tür modeller tasarlıyorsun?') }}">{{ old('description') }}</textarea>
                    @error('description')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                @include('auth.partials.terms')
                <button class="btn-primary w-full py-3">{{ __('Başvuruyu gönder') }}</button>
                <p class="text-center text-xs text-subtle">{{ __('Başvurun ekibimiz tarafından incelenir. Onaylandığında ürün yüklemeye başlayabilirsin.') }}</p>
            </form>
        </div>
    </div>
</x-layouts.app>
