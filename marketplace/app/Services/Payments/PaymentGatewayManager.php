<?php

namespace App\Services\Payments;

use App\Exceptions\MarketplaceException;
use App\Models\PaymentProvider;
use Illuminate\Support\Str;
use LogicException;

/**
 * Sağlayıcı koduna göre adaptör oluşturur. Bir sağlayıcının kullanılabilmesi için
 * config('marketplace.payments.gateways') içinde tanımlı olması, API anahtarlarının
 * .env dosyasında girilmiş olması ve yönetim panelinden (payment_providers.is_enabled)
 * etkinleştirilmiş olması gerekir.
 */
class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    /**
     * @throws MarketplaceException Sağlayıcı tanımlı değilse
     * @throws LogicException API anahtarları eksikse
     */
    public function gateway(string $code): PaymentGateway
    {
        if (isset($this->resolved[$code])) {
            return $this->resolved[$code];
        }

        $config = config("marketplace.payments.gateways.{$code}");

        if (! is_array($config) || ! isset($config['class'])) {
            throw new MarketplaceException(__('Seçilen ödeme yöntemi şu anda kullanılamıyor.'));
        }

        /** Yapılandırma anahtarları (api_key) kurucu parametrelerine (apiKey) eşlenir. */
        $options = collect($config)->except(['class', 'env'])->mapWithKeys(fn ($value, string $key) => [Str::camel($key) => $value])->all();

        return $this->resolved[$code] = app()->make($config['class'], $options);
    }

    /**
     * Adaptör tanımlı ve gerekli API anahtarları girilmiş mi?
     */
    public function isConfigured(string $code): bool
    {
        try {
            $this->gateway($code);

            return true;
        } catch (MarketplaceException|LogicException) {
            return false;
        }
    }

    /**
     * Yönetim panelinde gösterilecek, eksik olan .env değişkenleri.
     *
     * @return list<string>
     */
    public function missingSettings(string $code): array
    {
        $config = config("marketplace.payments.gateways.{$code}");

        if (! is_array($config)) {
            return [];
        }

        return array_values(array_filter($config['env'] ?? [], fn (string $variable, string $key) => blank($config[$key] ?? null), ARRAY_FILTER_USE_BOTH));
    }

    public function enabledProvider(string $code): PaymentProvider
    {
        $provider = PaymentProvider::query()->where('code', $code)->where('is_enabled', true)->first();

        if ($provider === null || ! $this->isConfigured($code)) {
            throw new MarketplaceException(__('Seçilen ödeme yöntemi şu anda kullanılamıyor.'));
        }

        return $provider;
    }
}
