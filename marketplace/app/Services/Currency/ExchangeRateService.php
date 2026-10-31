<?php

namespace App\Services\Currency;

use App\Exceptions\MarketplaceException;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Currency\Providers\ExchangeRateProvider;
use App\Services\Currency\Providers\ExchangeRateProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * USD/TRY kur servisi.
 *
 *  - Kurlar sağlayıcıdan çekilir, her başarılı sonuç yeni satır olarak saklanır (geçmiş korunur).
 *  - Sağlayıcı hata verirse son başarılı kur kullanılmaya devam eder.
 *  - Önceki kurdan aşırı sapan değerler reddedilir (bozuk veri koruması).
 *  - Yönetici manuel kur girebilir; manuel kur da yeni satır olarak eklenir.
 *  - Ödeme anında kullanılan kur ödeme kaydına kopyalanır; geçmiş işlemler etkilenmez.
 */
class ExchangeRateService
{
    public const BASE = 'USD';

    /** Bir önceki kura göre izin verilen en büyük değişim (%25). */
    private const MAX_DEVIATION_BPS = 2500;

    public function __construct(
        private readonly ExchangeRateProvider $provider,
        private readonly AuditLogger $audit,
    ) {}

    public function current(string $quote = 'TRY'): ?ExchangeRate
    {
        $id = Cache::remember($this->cacheKey($quote), config('marketplace.exchange_rates.cache_ttl_seconds'), fn () => ExchangeRate::query()
            ->where('base_currency', self::BASE)
            ->where('quote_currency', $quote)
            ->latest('fetched_at')
            ->latest('id')
            ->value('id'));

        return $id ? ExchangeRate::query()->find($id) : null;
    }

    /**
     * Ödeme için kullanılabilecek, yeterince güncel kur. Yoksa ödeme engellenir.
     */
    public function currentForPayment(string $quote): ExchangeRate
    {
        $rate = $this->current($quote);
        $maxAgeHours = (int) config('marketplace.exchange_rates.max_age_hours');

        if ($rate === null || $rate->fetched_at->lt(now()->subHours($maxAgeHours))) {
            throw new MarketplaceException(__('Güncel döviz kuru alınamadığı için şu anda bu para birimiyle ödeme yapılamıyor.'));
        }

        return $rate;
    }

    /**
     * Sağlayıcıdan güncel kuru çeker. Başarısızlıkta null döner ve son kur korunur.
     */
    public function refresh(string $quote = 'TRY'): ?ExchangeRate
    {
        $startedAt = hrtime(true);

        try {
            $micros = $this->provider->fetchRateMicros(self::BASE, $quote);
            $this->guardAgainstOutlier($quote, $micros);
        } catch (ExchangeRateProviderException $exception) {
            $this->log($quote, false, null, $exception->getMessage(), $startedAt);
            Log::warning('Döviz kuru güncellenemedi.', ['quote' => $quote, 'error' => $exception->getMessage()]);

            return null;
        }

        $rate = ExchangeRate::forceCreate([
            'base_currency' => self::BASE,
            'quote_currency' => $quote,
            'rate_micros' => $micros,
            'source' => $this->provider->name(),
            'is_manual' => false,
            'fetched_at' => now(),
        ]);

        $this->log($quote, true, $micros, null, $startedAt);
        Cache::forget($this->cacheKey($quote));

        return $rate;
    }

    public function setManualRate(string $quote, string $decimalRate, User $admin): ExchangeRate
    {
        $previous = $this->current($quote);

        $rate = ExchangeRate::forceCreate([
            'base_currency' => self::BASE,
            'quote_currency' => $quote,
            'rate_micros' => DecimalRate::toMicros($decimalRate),
            'source' => 'manual',
            'is_manual' => true,
            'set_by' => $admin->id,
            'fetched_at' => now(),
        ]);

        Cache::forget($this->cacheKey($quote));

        $this->audit->record('exchange_rate.manual_set', $rate, [
            'rate' => $previous?->formattedRate(),
        ], ['rate' => $rate->formattedRate()], $admin);

        return $rate;
    }

    /**
     * USD sent tutarını hedef para biriminin en küçük birimine çevirir.
     */
    public function convertFromUsdCents(int $usdCents, string $currency, ?ExchangeRate $rate = null): int
    {
        if ($currency === self::BASE) {
            return $usdCents;
        }

        $rate ??= $this->current($currency);

        if ($rate === null) {
            throw new MarketplaceException("{$currency} için kur bilgisi bulunamadı.");
        }

        return DecimalRate::multiply($usdCents, $rate->rate_micros);
    }

    private function guardAgainstOutlier(string $quote, int $micros): void
    {
        $previous = $this->current($quote);

        if ($previous === null) {
            return;
        }

        $deviationBps = intdiv(abs($micros - $previous->rate_micros) * 10_000, $previous->rate_micros);

        if ($deviationBps > self::MAX_DEVIATION_BPS) {
            throw new ExchangeRateProviderException("Kur önceki değerden %{$this->percent($deviationBps)} saptı; güvenlik nedeniyle reddedildi.");
        }
    }

    private function percent(int $bps): string
    {
        return intdiv($bps, 100).'.'.str_pad((string) ($bps % 100), 2, '0', STR_PAD_LEFT);
    }

    private function log(string $quote, bool $succeeded, ?int $micros, ?string $error, int $startedAt): void
    {
        ExchangeRateLog::forceCreate([
            'source' => $this->provider->name(),
            'base_currency' => self::BASE,
            'quote_currency' => $quote,
            'succeeded' => $succeeded,
            'rate_micros' => $micros,
            'error_message' => $error,
            'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
        ]);
    }

    private function cacheKey(string $quote): string
    {
        return 'exchange_rate.current.'.self::BASE.'.'.$quote;
    }
}
