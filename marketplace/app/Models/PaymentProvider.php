<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Yönetim panelinden açılıp kapatılabilen ödeme sağlayıcısı kaydı.
 * API anahtarları burada değil, .env dosyasında tutulur.
 */
#[Fillable(['code', 'name', 'mode', 'is_enabled', 'supported_currencies', 'supported_countries', 'sort_order'])]
class PaymentProvider extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'supported_currencies' => 'array',
            'supported_countries' => 'array',
        ];
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array($currency, $this->supported_currencies, true);
    }
}
