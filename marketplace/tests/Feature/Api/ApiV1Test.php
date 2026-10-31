<?php

namespace Tests\Feature\Api;

use App\Enums\FileScanStatus;
use App\Enums\UserStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\Accounts\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    private function token(User $user): string
    {
        return $this->postJson(route('api.v1.tokens.store'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'test'])
            ->assertCreated()->json('token');
    }

    public function test_public_catalog_endpoints_expose_only_published_products(): void
    {
        $published = $this->publishedProduct(399);
        $draft = Product::factory()->create(['title' => 'Gizli Taslak']);

        $this->getJson(route('api.v1.products.index'))->assertOk()
            ->assertJsonPath('data.0.slug', $published->slug)
            ->assertJsonPath('data.0.price.points', 399)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissing(['title' => 'Gizli Taslak']);

        $this->getJson(route('api.v1.products.index', ['q' => 'yok-boyle-bir-sey']))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(route('api.v1.products.show', $draft))->assertNotFound();

        $this->setUsdTryRate('40.000000');
        $detail = $this->getJson(route('api.v1.products.show', [$published, 'currency' => 'TRY']))->assertOk();
        $detail->assertJsonPath('data.price.currency', 'TRY')->assertJsonPath('data.price.amount_minor', 15960)->assertJsonPath('data.licenses.0.price.points', 399);
        $this->assertStringNotContainsString('"path"', $detail->getContent(), 'Dosya yolları API çıktısına girmez');

        $this->getJson(route('api.v1.categories'))->assertOk()->assertJsonStructure(['data' => [['id', 'slug', 'name', 'products_count']]]);
        $this->getJson(route('api.v1.exchange-rate'))->assertOk()->assertJsonPath('data.rate', '40.000000');
        $this->getJson(route('api.v1.designers.show', $published->creator->creatorProfile))->assertOk()->assertJsonPath('data.products.0.slug', $published->slug);
    }

    public function test_token_lifecycle_and_protected_endpoints(): void
    {
        $user = User::factory()->create();

        $this->getJson(route('api.v1.me'))->assertUnauthorized();
        $this->postJson(route('api.v1.tokens.store'), ['email' => $user->email, 'password' => 'yanlis', 'device_name' => 'x'])->assertUnprocessable();

        $token = $this->token($user);
        $this->withToken($token)->getJson(route('api.v1.me'))->assertOk()->assertJsonPath('data.email', $user->email);
        $this->assertNotNull($user->tokens()->sole()->expires_at, 'Belirteçlerin süresi vardır');

        $this->withToken($token)->deleteJson(route('api.v1.tokens.destroy'))->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_purchase_wallet_library_and_signed_download(): void
    {
        Storage::fake('private');
        $product = $this->publishedProduct(500);
        Storage::disk('private')->put('dosya.stl', 'solid t');
        $file = $product->files()->create(['disk' => 'private', 'path' => 'dosya.stl', 'original_name' => 'model.stl', 'extension' => 'stl', 'mime_type' => 'model/stl', 'size_bytes' => 7, 'sha256' => str_repeat('c', 64)]);
        $file->forceFill(['scan_status' => FileScanStatus::Clean])->save();
        $buyer = User::factory()->create();
        $token = $this->token($buyer);
        $requestId = (string) Str::uuid();

        $this->withToken($token)->postJson(route('api.v1.products.purchase', $product), ['request_id' => $requestId])
            ->assertStatus(402)->assertJsonPath('shortfall_points', 500);

        $this->withToken($token)->getJson(route('api.v1.me.downloads', $product))->assertForbidden();

        $this->givePoints($buyer, 600);
        $this->withToken($token)->postJson(route('api.v1.products.purchase', $product), ['request_id' => $requestId])->assertCreated()->assertJsonPath('balance_points', 100);
        $this->withToken($token)->postJson(route('api.v1.products.purchase', $product), ['request_id' => $requestId])->assertCreated()->assertJsonPath('balance_points', 100, 'Aynı istek kimliği ikinci kez puan düşmez');
        $this->withToken($token)->postJson(route('api.v1.products.purchase', $product), ['request_id' => (string) Str::uuid()])->assertStatus(409);

        $this->withToken($token)->getJson(route('api.v1.me.wallet'))->assertOk()->assertJsonPath('balance_points', 100)->assertJsonPath('data.0.amount_points', -500);
        $this->withToken($token)->getJson(route('api.v1.me.library'))->assertOk()->assertJsonPath('data.0.product.slug', $product->slug);

        $url = $this->withToken($token)->getJson(route('api.v1.me.downloads', $product))->assertOk()->json('data.0.url');
        $this->assertNotNull($url);
        $this->get($url)->assertOk()->assertDownload('model.stl');
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();

        $stranger = User::factory()->create();
        $this->get(preg_replace('/user=[^&]+/', 'user='.$stranger->uuid, $url))->assertForbidden();
    }

    public function test_unverified_and_suspended_users_are_limited(): void
    {
        $product = $this->publishedProduct(100);
        $unverified = User::factory()->unverified()->create();
        $this->givePoints($unverified, 500);
        $this->withToken($this->token($unverified))->postJson(route('api.v1.products.purchase', $product), ['request_id' => (string) Str::uuid()])->assertForbidden();

        $user = User::factory()->create();
        $token = $this->token($user);
        app(AccountService::class)->setStatus($user, UserStatus::Suspended, User::factory()->admin()->create(), 'Test');
        $this->assertSame(0, $user->tokens()->count(), 'Askıya alma belirteçleri iptal eder');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
        $this->postJson(route('api.v1.tokens.store'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'x'])->assertForbidden();
    }
}
