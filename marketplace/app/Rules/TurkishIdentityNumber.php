<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * T.C. kimlik numarası sağlama algoritması (11 hane, ilk hane 0 olamaz).
 */
class TurkishIdentityNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = (string) $value;

        if (! preg_match('/^[1-9]\d{10}$/', $digits)) {
            $fail(__('T.C. kimlik numarası 11 haneli olmalıdır.'));

            return;
        }

        $d = array_map('intval', str_split($digits));
        $tenth = ((($d[0] + $d[2] + $d[4] + $d[6] + $d[8]) * 7) - ($d[1] + $d[3] + $d[5] + $d[7])) % 10;
        $tenth = ($tenth + 10) % 10;
        $eleventh = array_sum(array_slice($d, 0, 10)) % 10;

        if ($d[9] !== $tenth || $d[10] !== $eleventh) {
            $fail(__('T.C. kimlik numarası geçersiz.'));
        }
    }
}
