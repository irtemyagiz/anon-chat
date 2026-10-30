<?php

namespace App\Services\Currency\Providers;

use App\Services\Currency\DecimalRate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Avrupa Merkez Bankası referans kurlarını yayınlayan Frankfurter API'si.
 * API anahtarı gerektirmez. Kurlar iş günlerinde günde bir kez güncellenir.
 *
 * @see https://frankfurter.dev
 */
class FrankfurterProvider implements ExchangeRateProvider
{
    public function __construct(private readonly string $baseUrl, private readonly int $timeout = 10) {}

    public function name(): string
    {
        return 'frankfurter';
    }

    public function fetchRateMicros(string $base, string $quote): int
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->get(rtrim($this->baseUrl, '/').'/latest', ['from' => $base, 'to' => $quote]);
        } catch (ConnectionException $exception) {
            throw new ExchangeRateProviderException('Kur sağlayıcısına bağlanılamadı: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            throw new ExchangeRateProviderException("Kur sağlayıcısı HTTP {$response->status()} döndürdü.");
        }

        /** Float hassasiyet kaybını önlemek için değer ham JSON metninden okunur. */
        if (! preg_match('/"'.preg_quote($quote, '/').'"\s*:\s*([0-9]+(?:\.[0-9]+)?)/', $response->body(), $matches)) {
            throw new ExchangeRateProviderException("Yanıtta {$quote} kuru bulunamadı.");
        }

        return DecimalRate::toMicros($matches[1]);
    }
}
