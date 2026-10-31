<x-layouts.admin :title="__('Sistem ayarları')">
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="card space-y-5 p-6">
        @csrf @method('PUT')
        <h2 class="font-bold">{{ __('Site') }}</h2>
        <div class="grid gap-5 sm:grid-cols-3">
            <x-field name="site_name" :label="__('Site adı')" :value="$values['site.name'] ?: config('app.name')" required/>
            <x-field name="contact_email" type="email" :label="__('İletişim e-postası')" :value="$values['site.contact_email']"/>
            <x-field name="support_email" type="email" :label="__('Destek e-postası')" :value="$values['site.support_email']"/>
        </div>
        <div class="flex items-center gap-4">
            @if ($values['site.logo_path'])<img src="{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url($values['site.logo_path']) }}" alt="" class="h-10">@endif
            <div class="flex-1"><label class="label" for="logo">{{ __('Logo (isteğe bağlı; boşsa Katman katman logosu kullanılır)') }}</label><input id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm"></div>
            @if ($values['site.logo_path'])<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_logo" value="1" class="accent-katman-600">{{ __('Logoyu kaldır') }}</label>@endif
        </div>
        <h2 class="pt-2 font-bold">{{ __('Dosyalar') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="max_file_size_mb" type="number" :label="__('Ürün dosyası başına en büyük boyut (MB)')" :value="$values['storage.max_file_size_mb']" min="1" max="2048" required/>
            <p class="self-end text-xs text-subtle">{{ __('Sunucunun PHP yükleme sınırı: :limit. Bu değerden büyük dosyalar için php.ini ayarlarını da artırın.', ['limit' => $serverUploadLimit]) }}</p>
        </div>
        <h2 class="pt-2 font-bold">{{ __('Bakım modu') }}</h2>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="maintenance" value="1" @checked($values['site.maintenance']) class="accent-katman-600">{{ __('Bakım modunu aç (yöneticiler siteyi kullanmaya devam eder)') }}</label>
        <x-field name="maintenance_message" :label="__('Bakım mesajı')" :value="$values['site.maintenance_message']"/>
        <button class="btn-primary">{{ __('Kaydet') }}</button>
    </form>

    <form method="POST" action="{{ route('admin.settings.mail') }}" class="card mt-6 space-y-5 p-6">
        @csrf @method('PUT')
        <h2 class="font-bold">{{ __('E-posta (SMTP)') }}</h2>
        <p class="text-xs text-muted">{{ __('Boş bırakılırsa .env dosyasındaki MAIL_* ayarları kullanılır. Şifre şifreli saklanır.') }}</p>
        <div class="grid gap-5 sm:grid-cols-3">
            <x-field name="host" :label="__('Sunucu')" :value="$values['mail.host']" placeholder="mail.alanadiniz.com" required/>
            <x-field name="port" type="number" :label="__('Port')" :value="$values['mail.port'] ?? 587" required/>
            <div><label class="label" for="encryption">{{ __('Şifreleme') }}</label><select id="encryption" name="encryption" class="input"><option value="tls" @selected($values['mail.encryption'] === 'tls')>TLS (587)</option><option value="ssl" @selected($values['mail.encryption'] === 'ssl')>SSL (465)</option><option value="" @selected(! $values['mail.encryption'])>{{ __('Yok') }}</option></select></div>
            <x-field name="username" :label="__('Kullanıcı adı')" :value="$values['mail.username']"/>
            <x-field name="password" type="password" :label="__('Şifre')" :hint="$hasMailPassword ? __('Kayıtlı şifre var; değiştirmek için yazın') : null"/>
            <span></span>
            <x-field name="from_address" type="email" :label="__('Gönderen adresi')" :value="$values['mail.from_address']" required/>
            <x-field name="from_name" :label="__('Gönderen adı')" :value="$values['mail.from_name'] ?? config('app.name')" required/>
        </div>
        <div class="flex gap-2"><button class="btn-primary">{{ __('Kaydet') }}</button><button type="submit" form="test-mail" class="btn-secondary">{{ __('Test e-postası gönder') }}</button></div>
    </form>
    <form id="test-mail" method="POST" action="{{ route('admin.settings.mail.test') }}" class="hidden">@csrf</form>
    <p class="mt-4 text-xs text-subtle">{{ __('Ödeme sağlayıcıları Ödemeler sayfasından, API anahtarları ise güvenlik nedeniyle yalnızca .env dosyasından yönetilir.') }}</p>
</x-layouts.admin>
