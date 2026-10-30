<?php

namespace Database\Factories;

use App\Models\CreatorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreatorProfile>
 */
class CreatorProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = fake()->unique()->company();

        return [
            'designer_name' => fake()->name(),
            'store_name' => $store,
            'store_slug' => Str::slug($store).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'terms_accepted_at' => now(),
        ];
    }
}
