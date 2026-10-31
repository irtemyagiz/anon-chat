<?php

namespace Tests\Feature\Web;

use App\Enums\CreatorStatus;
use App\Models\Category;
use App\Models\License;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    private function product(string $title, int $priceCents = 500, array $attributes = []): Product
    {
        $product = $this->publishedProduct($priceCents);
        $product->forceFill(['title' => $title, ...$attributes])->save();

        return $product;
    }

    private function addFile(Product $product, string $extension, bool $active = true): void
    {
        $product->files()->create([
            'disk' => 'private', 'path' => "p/{$product->id}.{$extension}", 'original_name' => "model.{$extension}",
            'extension' => $extension, 'mime_type' => 'application/octet-stream', 'size_bytes' => 100,
            'sha256' => str_repeat('a', 64), 'is_active' => $active,
        ]);
    }

    public function test_filters_by_format_license_technology_tag_and_usd_price_range(): void
    {
        $stl = $this->product('Vida Seti', 250, ['print_technology' => 'fdm']);
        $this->addFile($stl, 'stl');
        $obj = $this->product('Reçine Büst', 1200, ['print_technology' => 'sla']);
        $this->addFile($obj, 'obj');
        $this->addFile($obj, 'stl', active: false);
        $commercial = License::query()->where('code', License::Commercial)->sole();
        $obj->licenses()->create(['license_id' => $commercial->id, 'price_cents' => 2400, 'is_active' => true]);
        $tag = Tag::query()->create(['name' => 'Minyatür', 'slug' => 'minyatur']);
        $obj->tags()->attach($tag);

        $this->get(route('products.index', ['format' => 'stl']))->assertSee('Vida Seti')->assertDontSee('Reçine Büst');
        $this->get(route('products.index', ['format' => 'obj']))->assertSee('Reçine Büst')->assertDontSee('Vida Seti');
        $this->get(route('products.index', ['lisans' => 'ticari']))->assertSee('Reçine Büst')->assertDontSee('Vida Seti');
        $this->get(route('products.index', ['teknoloji' => 'fdm']))->assertSee('Vida Seti')->assertDontSee('Reçine Büst');
        $this->get(route('products.index', ['etiket' => 'minyatur']))->assertSee('#Minyatür etiketli modeller')->assertSee('Reçine Büst')->assertDontSee('Vida Seti');
        $this->get(route('products.index', ['min' => '5', 'max' => '20.00']))->assertSee('Reçine Büst')->assertDontSee('Vida Seti');
        $this->get(route('products.index', ['max' => '2,50']))->assertSee('Vida Seti')->assertDontSee('Reçine Büst');

        $this->get(route('products.index', ['min' => 'abc', 'format' => '../etc', 'teknoloji' => 'lazer', 'sirala' => 'x']))
            ->assertOk()->assertSee('Vida Seti')->assertSee('Reçine Büst');
    }

    public function test_most_liked_sort_and_category_page_filters(): void
    {
        $category = Category::query()->where('slug', 'organizasyon')->sole();
        $this->product('Az Beğenilen', 500, ['likes_count' => 1, 'category_id' => $category->id]);
        $this->product('Çok Beğenilen', 500, ['likes_count' => 40, 'category_id' => $category->id]);

        $this->get(route('products.index', ['sirala' => 'begeni']))->assertSeeInOrder(['Çok Beğenilen', 'Az Beğenilen']);
        $this->get(route('categories.show', [$category, 'teknoloji' => 'sls']))->assertSee('Bu filtrelerle eşleşen model bulunamadı');
    }

    public function test_suggestions_return_only_public_matches(): void
    {
        $visible = $this->product('Ejderha Kafası');
        $hidden = Product::factory()->create(['title' => 'Ejderha Taslağı']);
        $visible->creator->creatorProfile->forceFill(['store_name' => 'Ejderha Atölyesi'])->save();
        $pending = User::factory()->creator(CreatorStatus::Pending)->create();
        $pending->creatorProfile->forceFill(['store_name' => 'Ejderha Bekleyen'])->save();

        $response = $this->getJson(route('search.suggestions', ['q' => 'ejder']))->assertOk();

        $this->assertSame(['Ejderha Kafası'], array_column($response->json('products'), 'title'));
        $this->assertSame(['Ejderha Atölyesi'], array_column($response->json('designers'), 'name'));
        $this->assertNotContains($hidden->title, array_column($response->json('products'), 'title'));

        $this->assertSame(['Figürler'], array_column($this->getJson(route('search.suggestions', ['q' => 'FİGÜR']))->json('categories'), 'name'));
        $this->assertSame([], $this->getJson(route('search.suggestions', ['q' => 'e']))->json('products'));
        $this->getJson(route('search.suggestions', ['q' => '%_']))->assertOk()->assertJsonPath('products', []);
    }
}
