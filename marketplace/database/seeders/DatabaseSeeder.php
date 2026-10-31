<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Yalnızca platformun çalışması için gereken referans verileri yükler.
 * Ürün, kullanıcı, satış veya gelir verisi OLUŞTURMAZ (ilk kurulumda katalog boştur).
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AccessControlSeeder::class,
            CurrencySeeder::class,
            CategorySeeder::class,
            LicenseSeeder::class,
            FileFormatSeeder::class,
            PointPackageSeeder::class,
            PaymentProviderSeeder::class,
            ContentSeeder::class,
        ]);
    }
}
