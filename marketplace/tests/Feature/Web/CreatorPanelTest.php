<?php

namespace Tests\Feature\Web;

use App\Enums\CreatorStatus;
use App\Enums\FileScanStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductImage;
use App\Models\User;
use App\Notifications\ProductUpdatedNotification;
use App\Services\Files\ImageProcessor;
use App\Services\Files\Scanners\FileScanner;
use App\Services\Files\Scanners\ScanResult;
use App\Services\Sales\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;
use ZipArchive;

class CreatorPanelTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');
    }

    private function stl(string $name = 'parca.stl'): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/cube.stl'), $name, 'model/stl', null, true);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('kapak.jpg', 1200, 900);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productData(array $overrides = []): array
    {
        return [
            'title' => 'Modüler Masa Organizeri',
            'category_id' => Category::query()->where('slug', 'organizasyon')->value('id'),
            'short_description' => 'Çekmece içi organizer',
            'description' => 'Birbirine geçen modüler kutular.',
            'tags' => 'organizer, masa, Organizer',
            'price' => '3.99',
            'filament_type' => 'PLA',
            ...$overrides,
        ];
    }

    private function createProduct(User $creator, array $overrides = []): TestResponse
    {
        return $this->actingAs($creator)->post(route('creator.products.store'), $this->productData($overrides));
    }

    public function test_only_approved_creators_can_open_the_panel(): void
    {
        $this->actingAs(User::factory()->create())->get(route('creator.dashboard'))->assertRedirect(route('creator.apply'));
        $this->actingAs(User::factory()->creator(CreatorStatus::Pending)->create())->get(route('creator.dashboard'))->assertRedirect(route('creator.status'));
        $this->actingAs(User::factory()->creator(CreatorStatus::Suspended)->create())->get(route('creator.products.create'))->assertRedirect(route('creator.status'));

        $creator = User::factory()->creator()->create();
        foreach (['creator.dashboard', 'creator.products.index', 'creator.products.create', 'creator.sales', 'creator.earnings', 'creator.store.edit', 'creator.statistics', 'creator.coupons.index'] as $route) {
            $this->actingAs($creator)->get(route($route))->assertOk();
        }
    }

    public function test_creator_creates_product_with_files_images_and_preview(): void
    {
        $creator = User::factory()->creator()->create();

        $this->createProduct($creator, [
            'images' => [$this->image()],
            'files' => [$this->stl(), new UploadedFile(base_path('tests/Fixtures/ascii.stl'), 'ascii.stl', 'text/plain', null, true)],
            'preview' => $this->stl('onizleme.stl'),
            'commercial_price' => '9.99',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::query()->sole();
        $this->assertSame($creator->id, $product->creator_id);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame(399, $product->price_cents);
        $this->assertSame(399, $product->pricePoints());
        $this->assertSame('moduler-masa-organizeri', $product->slug);
        $this->assertSame(['organizer', 'masa'], $product->tags->pluck('slug')->all());
        $this->assertSame([399, 999], $product->licenses()->where('is_active', true)->orderBy('price_cents')->pluck('price_cents')->all());

        $files = $product->files;
        $this->assertCount(2, $files);
        foreach ($files as $file) {
            Storage::disk('private')->assertExists($file->path);
            Storage::disk('public')->assertMissing($file->path);
            $this->assertStringNotContainsString('parca', $file->path, 'Diskte rastgele ad kullanılır');
            $this->assertSame(FileScanStatus::Clean, $file->fresh()->scan_status, 'Test ortamında tarama kuyruğu senkron çalışır');
        }

        Storage::disk('public')->assertExists($product->preview_model_path);
        $image = $product->images()->sole();
        $this->assertStringEndsWith('.webp', $image->path, 'Görseller yeniden kodlanır');
        $this->assertSame($image->path, $product->cover_image_path);

        foreach ([480, 960] as $width) {
            $variant = ImageProcessor::variantPath($image->path, $width);
            Storage::disk('public')->assertExists($variant);
            $this->assertSame($variant, $image->variants['sizes'][$width]);
            $this->assertSame($width, getimagesizefromstring(Storage::disk('public')->get($variant))[0], 'Küçük boyut gerçekten küçültülür');
        }

        $this->delete(route('creator.products.images.destroy', [$product, $image]));
        Storage::disk('public')->assertMissing($image->path);
        Storage::disk('public')->assertMissing(ImageProcessor::variantPath($image->path, 480));
    }

    public function test_coarse_preview_is_generated_from_clean_stl_when_creator_uploads_none(): void
    {
        $creator = User::factory()->creator()->create();
        $this->createProduct($creator, ['files' => [$this->stl()]])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::query()->sole();
        $this->assertTrue($product->preview_auto_generated);
        Storage::disk('public')->assertExists($product->preview_model_path);
        $this->assertNotSame(
            file_get_contents(base_path('tests/Fixtures/cube.stl')),
            Storage::disk('public')->get($product->preview_model_path),
            'Satılan dosya public diske kopyalanmaz'
        );

        $this->put(route('creator.products.update', $product), $this->productData(['preview' => $this->stl('kendi.stl')]))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertFalse($product->preview_auto_generated, 'Creator önizlemesi otomatik olanın yerini alır');

        $this->delete(route('creator.products.preview.destroy', $product));
        $this->assertNull($product->fresh()->preview_model_path, 'Kaldırılan önizleme kendiliğinden geri gelmez');

        config(['marketplace.storage.auto_preview.max_source_mb' => 0]);
        $this->createProduct($creator, ['title' => 'Büyük dosyalı ürün', 'files' => [$this->stl()]]);
        $this->assertNull(Product::query()->where('title', 'Büyük dosyalı ürün')->sole()->preview_model_path, 'Sınırı aşan dosyadan önizleme üretilmez');
    }

    public function test_product_goes_through_review_before_publishing(): void
    {
        $creator = User::factory()->creator()->create();
        $this->createProduct($creator);
        $product = Product::query()->sole();

        $this->post(route('creator.products.submit', $product))->assertSessionHasErrors('submit');
        $this->assertSame(ProductStatus::Draft, $product->fresh()->status, 'Görsel ve dosya olmadan gönderilemez');

        $this->put(route('creator.products.update', $product), $this->productData(['images' => [$this->image()], 'files' => [$this->stl()]]))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('creator.products.submit', $product))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(ProductStatus::PendingReview, $product->fresh()->status);
        $this->get(route('products.show', $product))->assertNotFound();

        $this->artisan('marketplace:approve-product', ['slug' => $product->slug])->assertSuccessful();
        $this->assertSame(ProductStatus::Published, $product->fresh()->status);
        $this->get(route('products.show', $product))->assertOk()->assertSee('Modüler Masa Organizeri');
        $this->assertDatabaseHas('product_versions', ['product_id' => $product->id, 'version' => '1.0']);

        $this->post(route('creator.products.unpublish', $product));
        $this->assertSame(ProductStatus::Unpublished, $product->fresh()->status);
    }

    public function test_creator_cannot_edit_someone_elses_product(): void
    {
        $owner = User::factory()->creator()->create();
        $this->createProduct($owner);
        $product = Product::query()->sole();
        $intruder = User::factory()->creator()->create();

        $this->actingAs($intruder)->get(route('creator.products.edit', $product))->assertForbidden();
        $this->actingAs($intruder)->put(route('creator.products.update', $product), $this->productData(['title' => 'Ele geçirildi']))->assertForbidden();
        $this->actingAs($intruder)->post(route('creator.products.submit', $product))->assertForbidden();
        $this->assertSame('Modüler Masa Organizeri', $product->fresh()->title);
    }

    public function test_creator_cannot_set_status_or_owner_through_the_form(): void
    {
        $creator = User::factory()->creator()->create();
        $other = User::factory()->creator()->create();

        $this->createProduct($creator, ['status' => 'published', 'creator_id' => $other->id, 'is_featured' => 1, 'sales_count' => 999]);

        $product = Product::query()->sole();
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame($creator->id, $product->creator_id);
        $this->assertFalse($product->is_featured);
        $this->assertSame(0, $product->sales_count);
    }

    public function test_price_validation(): void
    {
        $creator = User::factory()->creator()->create();

        $this->createProduct($creator, ['price' => '3.999'])->assertSessionHasErrors('price');
        $this->createProduct($creator, ['price' => '0.10'])->assertSessionHasErrors('price');
        $this->createProduct($creator, ['price' => '5000'])->assertSessionHasErrors('price');
        $this->createProduct($creator, ['price' => '4', 'commercial_price' => '2'])->assertSessionHasErrors('commercial_price');
        $this->assertSame(0, Product::query()->count());

        $this->createProduct($creator, ['price' => '0'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Product::query()->sole()->is_free);
    }

    public function test_disallowed_and_spoofed_files_are_rejected(): void
    {
        $creator = User::factory()->creator()->create();

        $this->createProduct($creator, ['files' => [UploadedFile::fake()->createWithContent('kabuk.php', '<?php system($_GET["c"]);')]]);
        $this->createProduct($creator, ['title' => 'Sahte STL', 'files' => [UploadedFile::fake()->createWithContent('model.stl', '<?php echo "x"; ?> solid')]]);
        $this->createProduct($creator, ['title' => 'Sahte PDF', 'files' => [UploadedFile::fake()->createWithContent('kilavuz.pdf', 'MZ fake executable')]]);

        $this->assertSame(0, ProductFile::query()->count());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_dangerous_archives_are_rejected(): void
    {
        $creator = User::factory()->creator()->create();

        $this->createProduct($creator, ['files' => [$this->zip(['../../evil.stl' => 'solid'])]])->assertSessionHasErrors('uploads');
        $this->createProduct($creator, ['title' => 'Çalıştırılabilir', 'files' => [$this->zip(['kurulum.exe' => 'MZ'])]])->assertSessionHasErrors('uploads');
        $this->createProduct($creator, ['title' => 'Sıkıştırma bombası', 'files' => [$this->zip(['bomba.stl' => str_repeat('0', 20 * 1024 * 1024)])]])->assertSessionHasErrors('uploads');

        $this->createProduct($creator, ['title' => 'Temiz arşiv', 'files' => [$this->zip(['parcalar/govde.stl' => file_get_contents(base_path('tests/Fixtures/ascii.stl')), 'okubeni.pdf' => '%PDF-1.4'])]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, ProductFile::query()->count());
    }

    public function test_svg_with_script_is_rejected(): void
    {
        $creator = User::factory()->creator()->create();
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->createProduct($creator, ['files' => [$svg]])->assertSessionHasErrors('uploads');
        $this->assertSame(0, ProductFile::query()->count());
    }

    public function test_preview_accepts_only_3d_formats_and_non_images_are_not_accepted_as_images(): void
    {
        $creator = User::factory()->creator()->create();

        $this->createProduct($creator, ['preview' => UploadedFile::fake()->createWithContent('a.pdf', '%PDF-1.4')])->assertSessionHasErrors('uploads');
        $this->createProduct($creator, ['title' => 'Sahte görsel', 'images' => [UploadedFile::fake()->createWithContent('a.jpg', '<?php phpinfo();')]])->assertSessionHasErrors('uploads');

        $this->assertNull(Product::query()->whereNotNull('preview_model_path')->first());
        $this->assertSame(0, ProductImage::query()->count());
    }

    public function test_infected_file_is_deactivated_and_logged(): void
    {
        $this->app->bind(FileScanner::class, fn () => new class implements FileScanner
        {
            public function name(): string
            {
                return 'fake';
            }

            public function scan(string $localPath): ScanResult
            {
                return new ScanResult(FileScanStatus::Infected, 'Eicar-Test-Signature');
            }
        });

        $creator = User::factory()->creator()->create();
        $this->createProduct($creator, ['files' => [$this->stl()]]);

        $file = ProductFile::query()->sole();
        $this->assertSame(FileScanStatus::Infected, $file->scan_status);
        $this->assertFalse($file->is_active);
        $this->assertFalse($file->isDownloadable());
        $this->assertDatabaseHas('security_events', ['type' => 'file.infected', 'user_id' => $creator->id]);
    }

    public function test_dashboard_reflects_real_sales(): void
    {
        $creator = User::factory()->creator()->create();
        $product = $this->publishedProduct(1000, $creator);
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 1000);
        app(PurchaseService::class)->purchase($buyer, $product, null, 'k');

        $this->actingAs($creator)->get(route('creator.dashboard'))->assertOk()->assertSee('$8.00');
        $this->get(route('creator.sales'))->assertSee($product->title)->assertSee('$2.00');
        $this->get(route('creator.earnings'))->assertSee('$8.00');
    }

    public function test_new_version_is_recorded_and_owners_are_notified(): void
    {
        Notification::fake();
        $creator = User::factory()->creator()->create();
        $product = $this->publishedProduct(500, $creator);
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 500);
        app(PurchaseService::class)->purchase($buyer, $product, null, 'k');

        $this->actingAs($creator)->put(route('creator.products.update', $product), $this->productData([
            'title' => $product->title,
            'current_version' => '1.1',
            'changelog' => 'Menteşe toleransı artırıldı.',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_versions', ['product_id' => $product->id, 'version' => '1.1', 'changelog' => 'Menteşe toleransı artırıldı.']);
        Notification::assertSentTo($buyer, ProductUpdatedNotification::class, fn (ProductUpdatedNotification $notification) => $notification->version === '1.1');
        Notification::assertNotSentTo($creator, ProductUpdatedNotification::class);

        $this->get(route('products.show', $product->fresh()))->assertSee('Menteşe toleransı artırıldı.');
    }

    public function test_product_without_sales_can_be_deleted_but_sold_product_cannot(): void
    {
        $creator = User::factory()->creator()->create();
        $this->createProduct($creator);
        $draft = Product::query()->sole();

        $this->delete(route('creator.products.destroy', $draft))->assertRedirect(route('creator.products.index'));
        $this->assertSoftDeleted($draft);

        $sold = $this->publishedProduct(500, $creator);
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 500);
        app(PurchaseService::class)->purchase($buyer, $sold, null, 'k2');

        $this->actingAs($creator)->delete(route('creator.products.destroy', $sold))->assertSessionHasErrors('submit');
        $this->assertNotSoftDeleted($sold);

        $intruder = User::factory()->creator()->create();
        $other = $this->publishedProduct(500, $creator);
        $this->actingAs($intruder)->delete(route('creator.products.destroy', $other))->assertForbidden();
    }

    public function test_store_settings_update_with_social_link_validation(): void
    {
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)->put(route('creator.store.update'), [
            'store_name' => 'Yeni Ad', 'designer_name' => 'Ada', 'social' => ['instagram' => 'https://evil.example.com/x'],
        ])->assertSessionHasErrors('social.instagram');

        $this->put(route('creator.store.update'), [
            'store_name' => 'Yeni Ad', 'designer_name' => 'Ada', 'social' => ['instagram' => 'https://instagram.com/ada'], 'logo' => $this->image(),
        ])->assertSessionHasNoErrors();

        $profile = $creator->creatorProfile->fresh();
        $this->assertSame('Yeni Ad', $profile->store_name);
        $this->assertSame(['instagram' => 'https://instagram.com/ada'], $profile->social_links);
        Storage::disk('public')->assertExists($profile->logo_path);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function zip(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, 'paket.zip', 'application/zip', null, true);
    }
}
