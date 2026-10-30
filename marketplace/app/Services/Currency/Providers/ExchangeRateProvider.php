<?php

namespace App\Services\Currency\Providers;

interface ExchangeRateProvider
{
    /**
     * Sağlayıcının kısa adı (kur kaydında "source" olarak saklanır).
     */
    public function name(): string;

    /**
     * 1 birim $base para biriminin $quote cinsinden değerini milyonda bir
     * hassasiyetle tamsayı olarak döndürür (40.123456 => 40123456).
     *
     * @throws ExchangeRateProviderException
     */
    public function fetchRateMicros(string $base, string $quote): int;
}
