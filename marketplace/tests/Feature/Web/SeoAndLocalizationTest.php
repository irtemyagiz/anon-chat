<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class SeoAndLocalizationTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    public function test_sitemap_lists_public_urls_only(): void
    {
        $published = $this->publishedProduct(500);
        $draft = Product::factory()->create(['slug' => 'gizli-taslak']);

        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee(route('sitemap.section', ['section' => 'products', 'page' => 1]), false);

        $this->get(route('sitemap.section', ['section' => 'products', 'page' => 1]))->assertOk()
            ->assertSee(route('products.show', $published), false)
            ->assertSee('hreflang="en"', false)
            ->assertDontSee('gizli-taslak');
        $this->get(route('sitemap.section', ['section' => 'categories', 'page' => 1]))->assertSee(route('categories.show', 'figurler'), false);
        $this->get(route('sitemap.section', ['section' => 'pages', 'page' => 1]))->assertSee(route('pages.show', 'kullanim-sartlari'), false);
        $this->get('/sitemaps/unknown-1.xml')->assertNotFound();
    }

    public function test_robots_blocks_private_areas_in_production_and_everything_elsewhere(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');

        $this->app->detectEnvironment(fn () => 'production');
        $this->get('/robots.txt')->assertSee('Disallow: /panel')->assertSee('Disallow: /indir')->assertSee('Sitemap: '.route('sitemap.index'));
    }

    public function test_structured_data_is_present_and_cannot_break_out_of_the_script_tag(): void
    {
        $product = $this->publishedProduct(500);
        $product->forceFill(['title' => 'Vazo </script><script>alert(1)</script>'])->save();

        $response = $this->get(route('products.show', $product))->assertOk();
        $response->assertDontSee('</script><script>alert(1)</script>', false);
        $response->assertSee('"@type":"BreadcrumbList"', false)->assertSee('"@type":"Product"', false);
        $this->get('/')->assertSee('"@type":"WebSite"', false)->assertSee('SearchAction', false);
    }

    public function test_hreflang_and_language_switching(): void
    {
        $this->get('/')->assertSee('hreflang="en" href="'.url('/').'?lang=en"', false)->assertSee('Ücretsiz Modeller');

        $this->get('/?lang=en')->assertSee('<html lang="en"', false)->assertSee('Free Models')->assertSee('rel="canonical" href="'.url('/').'?lang=en"', false);
        $this->get('/modeller')->assertSee('Free Models', false);

        $this->post(route('preferences.update'), ['locale' => 'tr']);
        $this->get('/modeller')->assertSee('Ücretsiz Modeller');
    }

    public function test_browser_language_and_account_preference(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->get('/')->assertSee('Free Models');

        $user = User::factory()->create(['locale' => 'tr']);
        $this->actingAs($user)->withHeader('Accept-Language', 'en-US')->get('/')->assertSee('Ücretsiz Modeller');
    }

    public function test_error_pages_follow_the_browser_language(): void
    {
        $this->withHeader('Accept-Language', 'en-US')->get('/olmayan-adres')->assertNotFound()->assertSee('Page not found');
        $this->withHeader('Accept-Language', 'tr-TR')->get('/olmayan-adres')->assertNotFound()->assertSee('Sayfa bulunamadı');
    }

    public function test_english_content_pages_fall_back_to_turkish_when_missing(): void
    {
        $this->get('/sss?lang=en')->assertSee('What are points');
        $this->get(route('pages.show', 'kullanim-sartlari').'?lang=en')->assertSee('Terms of Use');
    }

    /**
     * Kullanıcıya görünen her metnin İngilizce karşılığı olmalı (yönetim paneli hariç).
     */
    public function test_every_public_string_has_an_english_translation(): void
    {
        $translations = json_decode(file_get_contents(lang_path('en.json')), true);
        $files = collect(array_merge(
            glob(resource_path('views/{,*/,*/*/,*/*/*/}*.blade.php'), GLOB_BRACE),
            glob(app_path('{,*/,*/*/,*/*/*/}*.php'), GLOB_BRACE),
        ))->reject(fn (string $path) => str_contains($path, '/admin/') || str_contains($path, 'layouts/admin') || str_contains($path, '/Admin/'));

        $missing = [];

        foreach ($files as $file) {
            preg_match_all("/(?:__|trans_choice)\\(\\s*'((?:\\\\.|[^'])*)'/s", file_get_contents($file), $matches);

            foreach ($matches[1] as $key) {
                $key = str_replace("\\'", "'", $key);

                if (preg_match('/[a-zçğıöşü]/iu', $key) && ! array_key_exists($key, $translations)) {
                    $missing[] = basename($file).': '.$key;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }
}
