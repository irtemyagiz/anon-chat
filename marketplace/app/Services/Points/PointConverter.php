<?php

namespace App\Services\Points;

use InvalidArgumentException;

/**
 * Puan ↔ USD dönüşümü. KURAL: 1 USD = 100 POINT.
 *
 * USD tutarları her zaman sent cinsinden tamsayı olarak işlenir; float
 * kullanılmaz. Bu oranla 1 sent tam olarak 1 puana karşılık gelir.
 */
final class PointConverter
{
    public const POINTS_PER_USD = 100;

    public const CENTS_PER_USD = 100;

    public static function usdCentsToPoints(int $cents): int
    {
        self::assertNotNegative($cents);

        return intdiv($cents * self::POINTS_PER_USD, self::CENTS_PER_USD);
    }

    public static function pointsToUsdCents(int $points): int
    {
        self::assertNotNegative($points);

        return intdiv($points * self::CENTS_PER_USD, self::POINTS_PER_USD);
    }

    /**
     * "3.99", "10", "2.5" gibi ondalık USD metnini sente çevirir.
     * İkiden fazla ondalık basamak reddedilir (yuvarlama yapılmaz).
     */
    public static function parseUsdToCents(string $amount): int
    {
        $amount = trim(str_replace(',', '.', $amount));

        if (! preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException("Geçersiz USD tutarı: {$amount}");
        }

        $fraction = str_pad($matches[2] ?? '0', 2, '0');

        return ((int) $matches[1]) * self::CENTS_PER_USD + (int) $fraction;
    }

    /**
     * Sent değerini "3.99" biçiminde ondalık metne çevirir.
     */
    public static function formatCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function assertNotNegative(int $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Tutar negatif olamaz.');
        }
    }
}
