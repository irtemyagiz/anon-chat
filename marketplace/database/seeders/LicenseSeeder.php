<?php

namespace Database\Seeders;

use App\Models\License;
use Illuminate\Database\Seeder;

class LicenseSeeder extends Seeder
{
    public function run(): void
    {
        License::query()->updateOrCreate(['code' => License::Personal], [
            'name' => ['tr' => 'Kişisel Kullanım', 'en' => 'Personal Use'],
            'summary' => [
                'tr' => 'Basılan ürünler yalnızca kişisel amaçla kullanılabilir; dosya paylaşılamaz ve satılamaz.',
                'en' => 'Prints are for personal use only; files may not be shared or resold.',
            ],
            'allows_commercial_use' => false,
            'sort_order' => 10,
        ]);

        License::query()->updateOrCreate(['code' => License::Commercial], [
            'name' => ['tr' => 'Ticari Kullanım', 'en' => 'Commercial Use'],
            'summary' => [
                'tr' => 'Basılan fiziksel ürünler satılabilir; dijital dosyanın kendisi paylaşılamaz ve satılamaz.',
                'en' => 'Physical prints may be sold; the digital file itself may not be shared or resold.',
            ],
            'allows_commercial_use' => true,
            'sort_order' => 20,
        ]);
    }
}
