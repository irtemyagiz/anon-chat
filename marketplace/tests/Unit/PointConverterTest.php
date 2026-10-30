<?php

namespace Tests\Unit;

use App\Services\Currency\DecimalRate;
use App\Services\Points\PointConverter;
use App\Services\Sales\CommissionResolver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PointConverterTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function usdToPoints(): array
    {
        return [
            '1 USD' => ['1', 100],
            '2 USD' => ['2', 200],
            '2.50 USD' => ['2.50', 250],
            '3.99 USD' => ['3.99', 399],
            '5 USD' => ['5', 500],
            '10 USD' => ['10', 1000],
            '25 USD' => ['25', 2500],
            '50 USD' => ['50', 5000],
            '100 USD' => ['100', 10000],
        ];
    }

    #[DataProvider('usdToPoints')]
    public function test_usd_converts_to_points_at_fixed_rate(string $usd, int $expectedPoints): void
    {
        $cents = PointConverter::parseUsdToCents($usd);

        $this->assertSame($expectedPoints, PointConverter::usdCentsToPoints($cents));
        $this->assertSame($cents, PointConverter::pointsToUsdCents($expectedPoints));
    }

    public function test_more_than_two_decimals_is_rejected_instead_of_rounded(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PointConverter::parseUsdToCents('3.999');
    }

    public function test_negative_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PointConverter::usdCentsToPoints(-1);
    }

    public function test_decimal_rate_parsing_and_multiplication_avoid_floats(): void
    {
        $this->assertSame(40_123_457, DecimalRate::toMicros('40.1234567'));
        $this->assertSame(40_000, DecimalRate::multiply(1000, DecimalRate::toMicros('40')));
        $this->assertSame(1, DecimalRate::multiply(1, DecimalRate::toMicros('0.5')));
    }

    public function test_commission_split_keeps_rounding_remainder_on_platform(): void
    {
        $this->assertSame(['creator' => 800, 'platform' => 200], CommissionResolver::split(1000, 2000));
        $this->assertSame(['creator' => 319, 'platform' => 80], CommissionResolver::split(399, 2000));
    }
}
