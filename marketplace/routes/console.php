<?php

use App\Enums\CreatorStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\UploadSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Currency\ExchangeRateService;
use App\Services\Earnings\CreatorEarningService;
use App\Services\Products\ProductManager;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('marketplace:refresh-exchange-rates {--force : Otomatik güncelleme kapalı olsa da çalıştır}', function (ExchangeRateService $rates, SettingsService $settings): int {
    if (! $this->option('force') && ! $settings->boolean('exchange_rates.auto_update', true)) {
        $this->info('Otomatik kur güncelleme kapalı.');

        return 0;
    }

    $rate = $rates->refresh('TRY');

    if ($rate === null) {
        $this->error('Kur güncellenemedi; son başarılı kur kullanılmaya devam ediyor.');

        return 1;
    }

    $this->info("USD/TRY = {$rate->formattedRate()} ({$rate->source})");

    return 0;
})->purpose('USD/TRY kurunu sağlayıcıdan günceller');

Artisan::command('marketplace:release-earnings', function (CreatorEarningService $earnings): void {
    $this->info($earnings->releaseMatured().' kazanç kullanılabilir bakiyeye aktarıldı.');
})->purpose('Bekleme süresi dolan creator kazançlarını serbest bırakır');

$exchangeRateInterval = max(5, (int) config('marketplace.exchange_rates.update_interval_minutes'));

Schedule::command('marketplace:refresh-exchange-rates')
    ->cron($exchangeRateInterval < 60 ? "*/{$exchangeRateInterval} * * * *" : '0 */'.min(23, intdiv($exchangeRateInterval, 60)).' * * *')
    ->withoutOverlapping();

Schedule::command('marketplace:release-earnings')->hourly()->withoutOverlapping();

Artisan::command('marketplace:prune-uploads', function (): void {
    $expired = UploadSession::query()->where('expires_at', '<=', now())->get();
    $expired->each->discard();

    /** Oturumu olmayan artık parça dosyaları (ör. veritabanı geri yüklendiyse) da silinir. */
    $known = UploadSession::query()->pluck('id')->flip();
    $orphans = collect(Storage::disk('local')->files('chunks'))
        ->filter(fn (string $path) => ! $known->has(basename($path, '.part')) && Storage::disk('local')->lastModified($path) < now()->subDay()->getTimestamp());
    Storage::disk('local')->delete($orphans->all());

    $this->info($expired->count().' süresi dolan yükleme oturumu ve '.$orphans->count().' artık parça dosyası silindi.');
})->purpose('Tamamlanmamış ve süresi dolmuş parçalı yüklemeleri temizler');

Schedule::command('marketplace:prune-uploads')->hourly()->withoutOverlapping();

Artisan::command('marketplace:make-admin {email : Yönetici yapılacak kullanıcının e-posta adresi}', function (string $email): int {
    $user = User::query()->where('email', Str::lower($email))->first();

    if ($user === null) {
        $this->error('Bu e-posta adresiyle kayıtlı kullanıcı bulunamadı.');

        return 1;
    }

    $user->roles()->syncWithoutDetaching([Role::query()->where('name', Role::Admin)->value('id')]);
    app(AuditLogger::class)->record('user.role_granted', $user, null, ['role' => Role::Admin], context: ['via' => 'console']);
    $this->info("{$user->email} artık yönetici.");

    return 0;
})->purpose('Mevcut bir kullanıcıya yönetici rolü verir');

Artisan::command('marketplace:approve-creator {email : Onaylanacak creator başvurusunun e-posta adresi}', function (string $email): int {
    $profile = User::query()->where('email', Str::lower($email))->first()?->creatorProfile;

    if ($profile === null) {
        $this->error('Bu kullanıcının creator başvurusu yok.');

        return 1;
    }

    $profile->forceFill(['status' => CreatorStatus::Approved, 'approved_at' => now()])->save();
    app(AuditLogger::class)->record('creator.approved', $profile, null, ['status' => 'approved'], context: ['via' => 'console']);
    $this->info("{$profile->store_name} mağazası onaylandı.");

    return 0;
})->purpose('Creator başvurusunu onaylar (yönetim paneli tamamlanana kadar)');

Artisan::command('marketplace:approve-product {slug : Onaylanacak ürünün SEO adresi}', function (string $slug, ProductManager $products): int {
    $product = Product::query()->where('slug', $slug)->first();

    if ($product === null) {
        $this->error('Ürün bulunamadı.');

        return 1;
    }

    $products->approve($product);
    $this->info("{$product->title} yayına alındı.");

    return 0;
})->purpose('İncelemedeki ürünü yayınlar (yönetim paneli tamamlanana kadar)');
