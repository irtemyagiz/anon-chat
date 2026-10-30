<?php

namespace App\Services\Currency;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Görüntüleme ve ödeme para birimini belirler.
 *
 * Görüntüleme: kullanıcı tercihi > kullanıcı ülkesi > oturum tercihi > IP ülke ipucu > USD.
 * IP yalnızca bir ipucudur (VPN yanıltabilir); kullanıcı her zaman manuel seçim yapabilir.
 *
 * Ödeme: IP'ye değil, kullanıcının hesabında kayıtlı fatura ülkesine göre belirlenir.
 */
class CurrencyResolver
{
    public const SESSION_KEY = 'display_currency';

    public function displayCurrency(?User $user, ?Request $request = null): string
    {
        $candidates = [
            $user?->display_currency,
            $this->currencyForCountry($user?->country_code),
            $request?->hasSession() ? $request->session()->get(self::SESSION_KEY) : null,
            $this->currencyForCountry($this->countryHint($request)),
        ];

        foreach ($candidates as $currency) {
            if ($currency !== null && $this->isSupported($currency)) {
                return $currency;
            }
        }

        return config('marketplace.currency.base');
    }

    public function paymentCurrency(User $user): string
    {
        return $this->currencyForCountry($user->country_code) ?? config('marketplace.currency.base');
    }

    public function isSupported(string $currency): bool
    {
        return in_array($currency, config('marketplace.currency.supported'), true);
    }

    public function currencyForCountry(?string $countryCode): ?string
    {
        if ($countryCode === null) {
            return null;
        }

        return config('marketplace.currency.country_currencies')[strtoupper($countryCode)] ?? null;
    }

    /**
     * CDN/proxy tarafından eklenen ülke başlığı (Cloudflare: CF-IPCountry).
     */
    private function countryHint(?Request $request): ?string
    {
        $country = $request?->header('CF-IPCountry');

        return is_string($country) && preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
    }
}
