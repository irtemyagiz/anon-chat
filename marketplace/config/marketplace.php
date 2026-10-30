<?php

use App\Services\Payments\Gateways\SandboxGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Puan ekonomisi
    |--------------------------------------------------------------------------
    |
    | 1 USD = 100 POINT sabit kuraldır ve yapılandırılamaz (App\Services\Points\PointConverter).
    | Aşağıdaki değerler varsayılanlardır; yönetim paneli system_settings
    | tablosu üzerinden bunları geçersiz kılabilir.
    |
    */

    'points' => [
        'minimum_top_up' => (int) env('MARKETPLACE_MINIMUM_TOP_UP_POINTS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Komisyon ve hakediş
    |--------------------------------------------------------------------------
    |
    | Oranlar baz puan (bps) cinsindendir: 2000 = %20.
    | hold_days: satış kazancının "bekleyen"den "kullanılabilir"e geçme süresi
    | (iade ve ödeme itirazı riskine karşı).
    |
    */

    'commission' => [
        'default_rate_bps' => (int) env('MARKETPLACE_COMMISSION_BPS', 2000),
    ],

    'earnings' => [
        'hold_days' => (int) env('MARKETPLACE_EARNING_HOLD_DAYS', 14),
        'minimum_payout_cents' => (int) env('MARKETPLACE_MINIMUM_PAYOUT_CENTS', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Para birimleri ve döviz kuru
    |--------------------------------------------------------------------------
    */

    'currency' => [
        'base' => 'USD',
        'supported' => ['USD', 'TRY'],
        'country_currencies' => [
            'TR' => 'TRY',
        ],
    ],

    'exchange_rates' => [
        'provider' => env('EXCHANGE_RATE_PROVIDER', 'frankfurter'),
        'cache_ttl_seconds' => (int) env('EXCHANGE_RATE_CACHE_TTL', 900),
        'update_interval_minutes' => (int) env('EXCHANGE_RATE_UPDATE_MINUTES', 60),
        'max_age_hours' => (int) env('EXCHANGE_RATE_MAX_AGE_HOURS', 48),
        'providers' => [
            'frankfurter' => [
                'url' => env('FRANKFURTER_URL', 'https://api.frankfurter.app'),
                'timeout' => 10,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ödeme sağlayıcıları
    |--------------------------------------------------------------------------
    |
    | Canlı sağlayıcılar (iyzico, PayTR, Stripe) yalnızca API anahtarları ve
    | ticari sözleşme tamamlandıktan sonra eklenecektir. "sandbox" sağlayıcısı
    | geliştirme ve test içindir ve production ortamında asla etkinleşmez.
    |
    */

    'payments' => [
        'gateways' => [
            'sandbox' => [
                'class' => SandboxGateway::class,
                'secret' => env('SANDBOX_PAYMENT_SECRET'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dosya depolama
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'product_files_disk' => env('PRODUCT_FILES_DISK', 'private'),
        'public_media_disk' => env('PUBLIC_MEDIA_DISK', 'public'),
        'download_link_ttl_minutes' => (int) env('DOWNLOAD_LINK_TTL_MINUTES', 10),
        'max_file_size_mb' => (int) env('MAX_PRODUCT_FILE_MB', 500),
    ],

];
