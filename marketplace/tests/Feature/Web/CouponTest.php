<?php

namespace Tests\Feature\Web;

use App\Models\Coupon;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    public function test_creator_creates_percent_and_fixed_coupons(): void
    {
        $creator = User::factory()->creator()->create();
        $product = $this->publishedProduct(1000, $creator);

        $this->actingAs($creator)->get(route('creator.coupons.index'))->assertOk()->assertSee('Henüz kuponun yok');

        $this->post(route('creator.coupons.store'), ['code' => ' yaz-2026 ', 'discount_type' => 'percent', 'percent' => 15])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('creator.coupons.store'), ['code' => 'IKIDOLAR', 'discount_type' => 'fixed', 'amount' => '2,00', 'product_id' => $product->id, 'max_redemptions' => 3])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coupons', ['creator_id' => $creator->id, 'code' => 'YAZ-2026', 'discount_type' => 'percent', 'discount_value' => 1500, 'product_id' => null]);
        $this->assertDatabaseHas('coupons', ['code' => 'IKIDOLAR', 'discount_type' => 'fixed', 'discount_value' => 200, 'product_id' => $product->id, 'max_redemptions' => 3]);
        $this->get(route('creator.coupons.index'))->assertSee('YAZ-2026')->assertSee('%15')->assertSee('$2.00');
    }

    public function test_coupon_validation_rules(): void
    {
        $creator = User::factory()->creator()->create();
        $foreignProduct = $this->publishedProduct(1000);
        $this->actingAs($creator);

        $this->post(route('creator.coupons.store'), ['code' => 'A B', 'discount_type' => 'percent', 'percent' => 10])->assertSessionHasErrors('code');
        $this->post(route('creator.coupons.store'), ['code' => 'COK', 'discount_type' => 'percent', 'percent' => 150])->assertSessionHasErrors('percent');
        $this->post(route('creator.coupons.store'), ['code' => 'SIFIR', 'discount_type' => 'fixed', 'amount' => '0'])->assertSessionHasErrors('amount');
        $this->post(route('creator.coupons.store'), ['code' => 'BASKA', 'discount_type' => 'percent', 'percent' => 10, 'product_id' => $foreignProduct->id])->assertSessionHasErrors('product_id');
        $this->post(route('creator.coupons.store'), ['code' => 'GECMIS', 'discount_type' => 'percent', 'percent' => 10, 'ends_at' => now()->subDay()->toDateTimeString()])->assertSessionHasErrors('ends_at');

        $this->post(route('creator.coupons.store'), ['code' => 'TEK', 'discount_type' => 'percent', 'percent' => 10])->assertSessionHasNoErrors();
        $this->post(route('creator.coupons.store'), ['code' => 'tek', 'discount_type' => 'percent', 'percent' => 20])->assertSessionHasErrors('code');

        $other = User::factory()->creator()->create();
        $this->actingAs($other)->post(route('creator.coupons.store'), ['code' => 'TEK', 'discount_type' => 'percent', 'percent' => 20])->assertSessionHasNoErrors();
    }

    public function test_buyer_previews_and_redeems_coupon_with_commission_on_discounted_price(): void
    {
        $creator = User::factory()->creator()->create();
        $product = $this->publishedProduct(1000, $creator);
        $coupon = Coupon::query()->forceCreate(['creator_id' => $creator->id, 'code' => 'YARIM', 'discount_type' => 'percent', 'discount_value' => 5000, 'max_redemptions' => 1]);
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 1000);

        $this->actingAs($buyer)->postJson(route('products.coupon', $product), ['coupon' => 'yarim'])
            ->assertOk()->assertJson(['valid' => true, 'list_points' => 1000, 'discount_points' => 500, 'total_points' => 500]);
        $this->assertSame(0, $coupon->fresh()->redemptions_count, 'Önizleme kuponu kullanmaz');

        $this->postJson(route('products.coupon', $product), ['coupon' => 'YOK'])->assertStatus(422)->assertJson(['valid' => false]);

        $this->post(route('products.purchase', $product), ['request_id' => (string) Str::uuid(), 'coupon' => 'YARIM'])->assertRedirect()->assertSessionHasNoErrors();

        $item = OrderItem::query()->sole();
        $this->assertSame(500, $item->price_points);
        $this->assertSame(500, $item->discount_points);
        $this->assertSame(500, $item->creator_amount_cents + $item->platform_amount_cents, 'Komisyon indirimli tutar üzerinden');
        $this->assertSame(500, $this->balanceOf($buyer));
        $this->assertSame(1, $coupon->fresh()->redemptions_count);

        $second = User::factory()->create();
        $this->givePoints($second, 1000);
        $this->actingAs($second)->post(route('products.purchase', $product), ['request_id' => (string) Str::uuid(), 'coupon' => 'YARIM'])
            ->assertSessionHasErrors('purchase')->assertSessionHasInput('coupon', 'YARIM');
        $this->assertSame(1000, $this->balanceOf($second), 'Limiti dolan kupon reddedilir, puan düşmez');
    }

    public function test_coupon_toggle_and_delete_rules(): void
    {
        $creator = User::factory()->creator()->create();
        $used = Coupon::query()->forceCreate(['creator_id' => $creator->id, 'code' => 'KULLANILDI', 'discount_type' => 'percent', 'discount_value' => 1000, 'redemptions_count' => 2]);
        $unused = Coupon::query()->forceCreate(['creator_id' => $creator->id, 'code' => 'YENI', 'discount_type' => 'percent', 'discount_value' => 1000]);
        $intruder = User::factory()->creator()->create();

        $this->actingAs($intruder)->post(route('creator.coupons.toggle', $used))->assertForbidden();
        $this->actingAs($intruder)->delete(route('creator.coupons.destroy', $unused))->assertForbidden();

        $this->actingAs($creator)->post(route('creator.coupons.toggle', $used))->assertRedirect();
        $this->assertFalse($used->fresh()->is_active);
        $this->delete(route('creator.coupons.destroy', $used))->assertSessionHasErrors('coupon');
        $this->assertModelExists($used);
        $this->delete(route('creator.coupons.destroy', $unused))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($unused);
    }
}
