<?php

namespace App\Services\Payments;

use App\Exceptions\MarketplaceException;
use App\Models\PaymentProvider;

/**
 * Sağlayıcı koduna göre adaptör oluşturur. Bir sağlayıcının kullanılabilmesi için
 * hem config('marketplace.payments.gateways') içinde tanımlı olması hem de yönetim
 * panelinden (payment_providers.is_enabled) etkinleştirilmiş olması gerekir.
 */
class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function gateway(string $code): PaymentGateway
    {
        if (isset($this->resolved[$code])) {
            return $this->resolved[$code];
        }

        $config = config("marketplace.payments.gateways.{$code}");

        if ($config === null) {
            throw new MarketplaceException("Ödeme sağlayıcısı yapılandırılmamış: {$code}");
        }

        $options = collect($config)->except('class')->all();

        return $this->resolved[$code] = app()->make($config['class'], $options);
    }

    public function enabledProvider(string $code): PaymentProvider
    {
        $provider = PaymentProvider::query()->where('code', $code)->where('is_enabled', true)->first();

        if ($provider === null || config("marketplace.payments.gateways.{$code}") === null) {
            throw new MarketplaceException('Seçilen ödeme yöntemi şu anda kullanılamıyor.');
        }

        return $provider;
    }
}
