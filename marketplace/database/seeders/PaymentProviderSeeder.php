<?php

namespace Database\Seeders;

use App\Models\PaymentProvider;
use Illuminate\Database\Seeder;

/**
 * Ödeme sağlayıcı kayıtları. Canlı sağlayıcılar kapalı (is_enabled = false)
 * olarak eklenir; API anahtarları ve sözleşme tamamlanınca panelden açılır.
 * Sandbox yalnızca production dışı ortamlarda etkinleştirilir.
 */
class PaymentProviderSeeder extends Seeder
{
    public function run(): void
    {
        PaymentProvider::query()->updateOrCreate(['code' => 'sandbox'], [
            'name' => 'Sandbox (Test)',
            'mode' => 'sandbox',
            'is_enabled' => ! app()->isProduction(),
            'supported_currencies' => ['USD', 'TRY'],
            'sort_order' => 0,
        ]);

        $providers = [
            'iyzico' => ['name' => 'iyzico', 'currencies' => ['TRY'], 'countries' => ['TR']],
            'paytr' => ['name' => 'PayTR', 'currencies' => ['TRY'], 'countries' => ['TR']],
            'stripe' => ['name' => 'Stripe', 'currencies' => ['USD'], 'countries' => null],
        ];

        $order = 10;

        foreach ($providers as $code => $provider) {
            PaymentProvider::query()->firstOrCreate(['code' => $code], [
                'name' => $provider['name'],
                'mode' => 'sandbox',
                'is_enabled' => false,
                'supported_currencies' => $provider['currencies'],
                'supported_countries' => $provider['countries'],
                'sort_order' => $order += 10,
            ]);
        }
    }
}
