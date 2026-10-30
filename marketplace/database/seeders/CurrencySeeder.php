<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->updateOrCreate(['code' => 'USD'], ['name' => 'ABD Doları', 'symbol' => '$', 'minor_units' => 2]);
        Currency::query()->updateOrCreate(['code' => 'TRY'], ['name' => 'Türk Lirası', 'symbol' => '₺', 'minor_units' => 2]);
    }
}
