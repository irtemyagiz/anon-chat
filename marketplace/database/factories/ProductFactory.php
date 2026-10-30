<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'creator_id' => User::factory()->creator(),
            'category_id' => Category::factory(),
            'title' => Str::title($title),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'short_description' => fake()->sentence(),
            'price_cents' => 1000,
        ];
    }

    public function published(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $product->forceFill(['status' => ProductStatus::Published, 'published_at' => now()])->save();
        });
    }

    public function priced(int $cents): static
    {
        return $this->state(fn (array $attributes) => ['price_cents' => $cents]);
    }

    public function free(): static
    {
        return $this->priced(0);
    }

    /**
     * Ürün fiyatıyla kişisel (varsayılan) lisans tanımlar.
     */
    public function withPersonalLicense(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $license = License::query()->firstOrCreate(['code' => License::Personal], ['name' => ['tr' => 'Kişisel', 'en' => 'Personal']]);

            $product->licenses()->create([
                'license_id' => $license->id,
                'price_cents' => $product->price_cents,
                'is_default' => true,
            ]);
        });
    }
}
