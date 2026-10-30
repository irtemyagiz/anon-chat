<?php

namespace Database\Seeders;

use App\Models\PointPackage;
use Illuminate\Database\Seeder;

/**
 * Başlangıç puan paketleri (1 USD = 100 POINT, bonus yok).
 */
class PointPackageSeeder extends Seeder
{
    /**
     * @var array<string, array{tr: string, en: string, points: int}>
     */
    private const PACKAGES = [
        'starter' => ['tr' => 'Başlangıç', 'en' => 'Starter', 'points' => 500],
        'standard' => ['tr' => 'Standart', 'en' => 'Standard', 'points' => 1000],
        'plus' => ['tr' => 'Plus', 'en' => 'Plus', 'points' => 2500],
        'pro' => ['tr' => 'Pro', 'en' => 'Pro', 'points' => 5000],
        'premium' => ['tr' => 'Premium', 'en' => 'Premium', 'points' => 10000],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::PACKAGES as $code => $package) {
            PointPackage::query()->updateOrCreate(['code' => $code], [
                'name' => ['tr' => $package['tr'], 'en' => $package['en']],
                'points' => $package['points'],
                'price_usd_cents' => $package['points'],
                'bonus_points' => 0,
                'is_active' => true,
                'sort_order' => $order += 10,
            ]);
        }
    }
}
