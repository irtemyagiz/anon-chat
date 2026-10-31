<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ISO 13616 IBAN doğrulaması (mod-97). İsteğe bağlı olarak ülke kodu zorunlu tutulabilir.
 */
class Iban implements ValidationRule
{
    public function __construct(private readonly ?string $country = null) {}

    public static function normalize(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', $value));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iban = self::normalize((string) $value);

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)
            || ($this->country !== null && ! str_starts_with($iban, $this->country))
            || ($this->country === 'TR' && strlen($iban) !== 26)) {
            $fail(__('Geçerli bir IBAN girin.'));

            return;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = preg_replace_callback('/[A-Z]/', fn (array $m) => (string) (ord($m[0]) - 55), $rearranged);
        $remainder = 0;

        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        if ($remainder !== 1) {
            $fail(__('IBAN kontrol basamakları hatalı.'));
        }
    }
}
