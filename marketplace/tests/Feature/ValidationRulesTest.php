<?php

namespace Tests\Feature;

use App\Rules\Iban;
use App\Rules\TurkishIdentityNumber;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidationRulesTest extends TestCase
{
    protected bool $seed = false;

    /**
     * @return array<string, array{string, ?string, bool}>
     */
    public static function ibans(): array
    {
        return [
            'geçerli TR' => ['TR33 0006 1005 1978 6457 8413 26', 'TR', true],
            'kontrol basamağı hatalı' => ['TR330006100519786457841327', 'TR', false],
            'TR beklenirken DE' => ['DE89370400440532013000', 'TR', false],
            'geçerli DE' => ['DE89 3704 0044 0532 0130 00', null, true],
            'geçerli GB' => ['GB29NWBK60161331926819', null, true],
            'çöp' => ['12345', null, false],
        ];
    }

    #[DataProvider('ibans')]
    public function test_iban_rule(string $iban, ?string $country, bool $valid): void
    {
        $this->assertSame($valid, Validator::make(['iban' => $iban], ['iban' => [new Iban($country)]])->passes());
    }

    public function test_turkish_identity_number_rule(): void
    {
        $passes = fn (string $value) => Validator::make(['id' => $value], ['id' => [new TurkishIdentityNumber]])->passes();

        $this->assertTrue($passes('10000000146'));
        $this->assertFalse($passes('10000000147'));
        $this->assertFalse($passes('01234567890'));
        $this->assertFalse($passes('1234567890'));
    }
}
