<?php

namespace App\Services\Currency;

use InvalidArgumentException;

/**
 * Ondalık kur metni ↔ milyonda bir tamsayı dönüşümü (float kullanmadan).
 */
final class DecimalRate
{
    public const SCALE = 1_000_000;

    /**
     * "40.1234567" => 40123457 (6. basamaktan sonrası yarım yukarı yuvarlanır).
     */
    public static function toMicros(string $decimal): int
    {
        $decimal = trim(str_replace(',', '.', $decimal));

        if (! preg_match('/^(\d{1,9})(?:\.(\d+))?$/', $decimal, $matches)) {
            throw new InvalidArgumentException("Geçersiz kur değeri: {$decimal}");
        }

        $fraction = $matches[2] ?? '';
        $micros = (int) $matches[1] * self::SCALE + (int) str_pad(substr($fraction, 0, 6), 6, '0');

        if (strlen($fraction) > 6 && (int) $fraction[6] >= 5) {
            $micros++;
        }

        if ($micros <= 0) {
            throw new InvalidArgumentException('Kur sıfırdan büyük olmalıdır.');
        }

        return $micros;
    }

    /**
     * $amountMinor * kur, yarım yukarı yuvarlanmış tamsayı sonuç.
     */
    public static function multiply(int $amountMinor, int $rateMicros): int
    {
        return intdiv($amountMinor * $rateMicros + intdiv(self::SCALE, 2), self::SCALE);
    }
}
