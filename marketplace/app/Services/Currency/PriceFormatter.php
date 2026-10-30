<?php

namespace App\Services\Currency;

use App\Exceptions\MarketplaceException;
use App\Services\Points\PointConverter;

/**
 * Ürün/paket fiyatını puan + bölgesel para birimi olarak biçimlendirir.
 */
class PriceFormatter
{
    public function __construct(private readonly ExchangeRateService $rates) {}

    /**
     * @return array{points: int, usd_cents: int, currency: string, amount_minor: int, formatted: string}
     */
    public function present(int $usdCents, string $currency): array
    {
        try {
            $amountMinor = $this->rates->convertFromUsdCents($usdCents, $currency);
        } catch (MarketplaceException) {
            $currency = ExchangeRateService::BASE;
            $amountMinor = $usdCents;
        }

        return [
            'points' => PointConverter::usdCentsToPoints($usdCents),
            'usd_cents' => $usdCents,
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'formatted' => $this->format($amountMinor, $currency),
        ];
    }

    public function format(int $amountMinor, string $currency): string
    {
        $whole = intdiv(abs($amountMinor), 100);
        $fraction = str_pad((string) (abs($amountMinor) % 100), 2, '0', STR_PAD_LEFT);
        $sign = $amountMinor < 0 ? '-' : '';

        return match ($currency) {
            'TRY' => $sign.number_format($whole, 0, ',', '.').','.$fraction.' TL',
            default => $sign.'$'.number_format($whole, 0, '.', ',').'.'.$fraction,
        };
    }
}
