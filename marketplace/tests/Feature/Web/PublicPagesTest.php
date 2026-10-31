<?php

namespace Tests\Feature\Web;

use App\Enums\CreatorStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function publicPages(): array
    {
        return [
            'ana sayfa' => ['/'],
            'modeller' => ['/modeller'],
            'ücretsiz' => ['/modeller?fiyat=ucretsiz'],
            'arama' => ['/modeller?q=vazo&sirala=ucuz'],
            'kategoriler' => ['/kategoriler'],
            'kategori' => ['/kategori/figurler'],
            'tasarımcılar' => ['/tasarimcilar'],
            'giriş' => ['/giris'],
            'kayıt' => ['/kayit'],
            'creator ol' => ['/creator-ol'],
            'şifre sıfırlama' => ['/sifremi-unuttum'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_public_page_renders(string $url): void
    {
        $this->get($url)->assertOk();
    }

    public function test_empty_catalog_shows_empty_state_instead_of_fake_products(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Katalog ilk tasarımlarını bekliyor')
            ->assertDontSee('Öne çıkan modeller');
    }

    public function test_published_products_appear_and_drafts_do_not(): void
    {
        $published = $this->publishedProduct(399);
        $draft = Product::factory()->create(['title' => 'Gizli Taslak Model']);

        $this->get('/')->assertSee($published->title)->assertDontSee('Katalog ilk tasarımlarını bekliyor');
        $this->get('/modeller')->assertSee($published->title)->assertDontSee($draft->title);
        $this->get(route('products.show', $published))->assertOk()->assertSee('399');
        $this->get(route('products.show', $draft))->assertNotFound();
    }

    public function test_home_page_cache_follows_publishing_changes(): void
    {
        $this->get('/')->assertSee('Katalog ilk tasarımlarını bekliyor');

        $product = $this->publishedProduct(399);
        $this->get('/')->assertSee($product->title, 'Yayına giren ürün önbellek beklenmeden görünür');

        $product->forceFill(['status' => ProductStatus::Unpublished])->save();
        $this->get('/')->assertDontSee($product->title)->assertSee('Katalog ilk tasarımlarını bekliyor');

        $other = $this->publishedProduct(500);
        DB::table('products')->where('id', $other->id)->update(['status' => ProductStatus::Unpublished->value]);
        $this->get('/')->assertDontSee($other->title, 'Önbellekteki kimlikler yayında olup olmadıkları kontrol edilerek yüklenir');
    }

    public function test_search_filters_and_category_listing(): void
    {
        $category = Category::query()->where('slug', 'figurler')->sole();
        $vase = Product::factory()->published()->withPersonalLicense()->create(['title' => 'Spiral Vazo', 'category_id' => $category->id]);
        $free = Product::factory()->free()->published()->withPersonalLicense()->create(['title' => 'Ücretsiz Kanca']);

        $this->get('/modeller?q=spiral')->assertSee('Spiral Vazo')->assertDontSee('Ücretsiz Kanca');
        $this->get('/modeller?fiyat=ucretsiz')->assertSee('Ücretsiz Kanca')->assertDontSee('Spiral Vazo');
        $this->get('/kategori/figurler')->assertSee($vase->title)->assertDontSee($free->title);
    }

    public function test_turkish_visitor_sees_try_and_others_see_usd(): void
    {
        $this->setUsdTryRate('40');
        $product = $this->publishedProduct(1000);

        $this->actingAs(User::factory()->turkish()->create())->get(route('products.show', $product))->assertSee('400,00 TL');
        $this->actingAs(User::factory()->create())->get(route('products.show', $product))->assertSee('$10.00');
    }

    public function test_display_currency_and_language_preferences(): void
    {
        $this->setUsdTryRate('40');
        $product = $this->publishedProduct(1000);

        $this->post(route('preferences.update'), ['currency' => 'TRY'])->assertRedirect();
        $this->get(route('products.show', $product))->assertSee('400,00 TL');

        $this->post(route('preferences.update'), ['currency' => 'EUR'])->assertSessionHasErrors('currency');
    }

    public function test_views_are_counted_once_per_session_and_not_for_the_owner(): void
    {
        $product = $this->publishedProduct(500);

        $this->get(route('products.show', $product));
        $this->get(route('products.show', $product));
        $this->assertSame(1, $product->fresh()->views_count);

        $this->actingAs($product->creator)->get(route('products.show', $product));
        $this->assertSame(1, $product->fresh()->views_count);
    }

    public function test_video_links_are_embedded_with_privacy_friendly_hosts(): void
    {
        $product = $this->publishedProduct(500);

        $product->forceFill(['preview_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])->save();
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $product->videoEmbedUrl());

        $product->forceFill(['preview_video_url' => 'https://vimeo.com/76979871'])->save();
        $this->assertSame('https://player.vimeo.com/video/76979871?dnt=1', $product->videoEmbedUrl());
    }

    public function test_unknown_store_and_unapproved_store_are_not_found(): void
    {
        $pending = User::factory()->creator(CreatorStatus::Pending)->create();

        $this->get('/magaza/olmayan-magaza')->assertNotFound();
        $this->get(route('designers.show', $pending->creatorProfile))->assertNotFound();
    }
}
