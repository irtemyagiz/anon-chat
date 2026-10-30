<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * @var array<string, array{tr: string, en: string}>
     */
    private const CATEGORIES = [
        'figurler' => ['tr' => 'Figürler', 'en' => 'Figures'],
        'oyuncaklar' => ['tr' => 'Oyuncaklar', 'en' => 'Toys'],
        'miniaturler' => ['tr' => 'Miniatürler', 'en' => 'Miniatures'],
        'ev-dekorasyonu' => ['tr' => 'Ev Dekorasyonu', 'en' => 'Home Decor'],
        'organizasyon' => ['tr' => 'Organizasyon', 'en' => 'Organization'],
        'arac-aksesuarlari' => ['tr' => 'Araç Aksesuarları', 'en' => 'Vehicle Accessories'],
        'elektronik' => ['tr' => 'Elektronik', 'en' => 'Electronics'],
        'masaustu-urunler' => ['tr' => 'Masaüstü Ürünler', 'en' => 'Desktop Items'],
        'cosplay' => ['tr' => 'Cosplay', 'en' => 'Cosplay'],
        'taki-ve-aksesuar' => ['tr' => 'Takı ve Aksesuar', 'en' => 'Jewelry & Accessories'],
        'anahtarliklar' => ['tr' => 'Anahtarlıklar', 'en' => 'Keychains'],
        'oyun-aksesuarlari' => ['tr' => 'Oyun Aksesuarları', 'en' => 'Gaming Accessories'],
        'sanat' => ['tr' => 'Sanat', 'en' => 'Art'],
        'egitim' => ['tr' => 'Eğitim', 'en' => 'Education'],
        'mimari' => ['tr' => 'Mimari', 'en' => 'Architecture'],
        'mekanik-parcalar' => ['tr' => 'Mekanik Parçalar', 'en' => 'Mechanical Parts'],
        'yedek-parcalar' => ['tr' => 'Yedek Parçalar', 'en' => 'Spare Parts'],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::CATEGORIES as $slug => $name) {
            Category::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'sort_order' => $order += 10,
                'is_active' => true,
            ]);
        }
    }
}
