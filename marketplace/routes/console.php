<?php

use App\Services\Currency\ExchangeRateService;
use App\Services\Earnings\CreatorEarningService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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
