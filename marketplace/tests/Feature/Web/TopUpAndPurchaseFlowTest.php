<?php

namespace Tests\Feature\Web;

use App\Enums\FileScanStatus;
use App\Http\Controllers\DownloadController;
use App\Models\PointPackage;
use App\Models\PointPurchase;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class TopUpAndPurchaseFlowTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    public function test_full_top_up_flow_through_sandbox_checkout(): void
    {
        $user = User::factory()->create();
        $package = PointPackage::query()->where('code', 'standard')->sole();

        $this->actingAs($user)->get('/puan-yukle')->assertOk()->assertSee('1.000')->assertSee('$10.00');

        $response = $this->post('/puan-yukle', ['package' => $package->id, 'provider' => 'sandbox']);
        $purchase = PointPurchase::query()->sole();
        $response->assertRedirect(route('sandbox.checkout', $purchase));

        $this->get(route('sandbox.checkout', $purchase))->assertOk()->assertSee('TEST ÖDEME SAYFASI');
        $this->assertSame(0, $this->balanceOf($user), 'Yönlendirme tek başına puan yüklemez');

        $this->post(route('sandbox.complete', $purchase), ['outcome' => 'success'])->assertRedirect(route('top-up.result', $purchase));

        $this->assertSame(1000, $this->balanceOf($user));
        $this->get(route('top-up.result', $purchase))->assertSee('Puanların yüklendi');
        $this->get('/cuzdan')->assertOk()->assertSee('Puan yükleme')->assertSee('+1.000');
    }

    public function test_failed_sandbox_payment_does_not_credit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/puan-yukle', ['package' => PointPackage::query()->where('code', 'starter')->value('id'), 'provider' => 'sandbox']);
        $purchase = PointPurchase::query()->sole();

        $this->post(route('sandbox.complete', $purchase), ['outcome' => 'failure']);

        $this->assertSame(0, $this->balanceOf($user));
        $this->get(route('top-up.result', $purchase))->assertSee('Ödeme tamamlanamadı');
    }

    public function test_users_cannot_see_or_complete_each_others_payments(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/puan-yukle', ['package' => PointPackage::query()->where('code', 'starter')->value('id'), 'provider' => 'sandbox']);
        $purchase = PointPurchase::query()->sole();

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get(route('sandbox.checkout', $purchase))->assertNotFound();
        $this->actingAs($intruder)->post(route('sandbox.complete', $purchase), ['outcome' => 'success'])->assertNotFound();
        $this->actingAs($intruder)->get(route('top-up.result', $purchase))->assertNotFound();
        $this->assertSame(0, $this->balanceOf($owner));
    }

    public function test_purchase_via_web_and_insufficient_balance_redirects_to_top_up(): void
    {
        $buyer = User::factory()->create();
        $product = $this->publishedProduct(800);
        $this->givePoints($buyer, 300);

        $this->actingAs($buyer)->post(route('products.purchase', $product), ['request_id' => (string) Str::uuid()])
            ->assertRedirect(route('top-up.create', ['eksik' => 500]));
        $this->assertSame(300, $this->balanceOf($buyer));

        $this->givePoints($buyer, 500);
        $this->get(route('products.show', $product))->assertSee('Mevcut bakiye: 800 POINT');
        $this->post(route('products.purchase', $product), ['request_id' => (string) Str::uuid()])->assertSessionHas('status');

        $this->assertSame(0, $this->balanceOf($buyer));
        $this->get(route('products.show', $product))->assertSee('Satın alındı');
    }

    public function test_free_product_is_added_to_library(): void
    {
        $user = User::factory()->create();
        $product = $this->publishedProduct(0);

        $this->actingAs($user)->post(route('products.purchase', $product), ['request_id' => (string) Str::uuid()])->assertSessionHas('status');

        $this->assertTrue($user->ownsProduct($product));
    }

    public function test_paid_files_require_purchase_and_a_signed_link(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('products/model.stl', 'solid test');

        $product = $this->publishedProduct(500);
        $file = $this->productFile($product);
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('downloads.file', $file))->assertForbidden();
        $this->actingAs($stranger)->get(DownloadController::temporaryUrl($file))->assertForbidden();
        $this->actingAs($stranger)->get(route('products.show', $product))->assertDontSee('model.stl');

        $this->givePoints($buyer, 500);
        $this->actingAs($buyer)->post(route('products.purchase', $product), ['request_id' => (string) Str::uuid()]);

        $this->get(route('downloads.file', $file))->assertForbidden();
        $this->get(DownloadController::temporaryUrl($file))->assertOk()->assertDownload('model.stl');
        $this->assertDatabaseHas('downloads', ['user_id' => $buyer->id, 'product_file_id' => $file->id]);
        $this->assertSame(1, $product->fresh()->downloads_count);

        $link = DownloadController::temporaryUrl($file);
        $this->travel(11)->minutes();
        $this->get($link)->assertForbidden();
    }

    public function test_file_that_has_not_passed_scanning_cannot_be_downloaded(): void
    {
        Storage::fake('private');
        $product = $this->publishedProduct(0);
        $file = $this->productFile($product, FileScanStatus::Pending);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('products.purchase', $product), ['request_id' => (string) Str::uuid()]);

        $this->get(DownloadController::temporaryUrl($file))->assertNotFound();
    }

    private function productFile(Product $product, FileScanStatus $status = FileScanStatus::Clean): ProductFile
    {
        $file = $product->files()->create([
            'disk' => 'private',
            'path' => 'products/model.stl',
            'original_name' => 'model.stl',
            'extension' => 'stl',
            'mime_type' => 'model/stl',
            'size_bytes' => 10,
            'sha256' => hash('sha256', 'solid test'),
        ]);
        $file->forceFill(['scan_status' => $status])->save();

        return $file;
    }
}
