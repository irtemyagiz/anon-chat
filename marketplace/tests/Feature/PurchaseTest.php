<?php

namespace Tests\Feature;

use App\Enums\CommissionScope;
use App\Enums\EarningStatus;
use App\Enums\OrderStatus;
use App\Exceptions\AlreadyOwnedException;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\MarketplaceException;
use App\Models\CommissionRule;
use App\Models\Coupon;
use App\Models\CreatorEarning;
use App\Models\Product;
use App\Models\User;
use App\Services\Sales\PurchaseService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    private function purchases(): PurchaseService
    {
        return app(PurchaseService::class);
    }

    public function test_purchase_deducts_points_grants_download_and_splits_revenue(): void
    {
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 1500);
        $product = $this->publishedProduct(1000);

        $order = $this->purchases()->purchase($buyer, $product, null, 'req-1');

        $this->assertSame(500, $this->balanceOf($buyer));
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertTrue($buyer->ownsProduct($product));

        $item = $order->items()->sole();
        $this->assertSame(1000, $item->price_points);
        $this->assertSame(2000, $item->commission_bps);
        $this->assertSame(800, $item->creator_amount_cents, 'Creator hakedişi 8 USD');
        $this->assertSame(200, $item->platform_amount_cents, 'Platform geliri 2 USD');

        $earning = CreatorEarning::query()->sole();
        $this->assertSame(EarningStatus::Pending, $earning->status);
        $this->assertSame(800, $product->creator->creatorBalance->pending_cents);
        $this->assertSame(0, (int) $this->walletService()->balanceOf($product->creator), 'Creator kazancı puan cüzdanına yazılmaz');
    }

    public function test_insufficient_balance_reports_shortfall_and_keeps_existing_points(): void
    {
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 300);
        $product = $this->publishedProduct(800);

        try {
            $this->purchases()->purchase($buyer, $product, null, 'req-1');
            $this->fail('Yetersiz bakiye bekleniyordu.');
        } catch (InsufficientPointsException $exception) {
            $this->assertSame(500, $exception->shortfall());
        }

        $this->assertSame(300, $this->balanceOf($buyer));
        $this->assertFalse($buyer->ownsProduct($product));
    }

    public function test_same_product_and_license_cannot_be_bought_twice(): void
    {
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 5000);
        $product = $this->publishedProduct(1000);

        $this->purchases()->purchase($buyer, $product, null, 'req-1');

        $this->expectException(AlreadyOwnedException::class);

        try {
            $this->purchases()->purchase($buyer, $product, null, 'req-2');
        } finally {
            $this->assertSame(4000, $this->balanceOf($buyer));
        }
    }

    public function test_retried_request_with_same_key_returns_the_same_order(): void
    {
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 5000);
        $product = $this->publishedProduct(1000);

        $first = $this->purchases()->purchase($buyer, $product, null, 'double-click');
        $second = $this->purchases()->purchase($buyer, $product, null, 'double-click');

        $this->assertTrue($first->is($second));
        $this->assertSame(4000, $this->balanceOf($buyer));
    }

    public function test_balance_cannot_be_spent_twice_across_purchases(): void
    {
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 1000);
        $first = $this->publishedProduct(700);
        $second = $this->publishedProduct(700);

        $this->purchases()->purchase($buyer, $first, null, 'a');

        $this->expectException(InsufficientPointsException::class);

        try {
            $this->purchases()->purchase($buyer, $second, null, 'b');
        } finally {
            $this->assertSame(300, $this->balanceOf($buyer));
        }
    }

    public function test_unpublished_and_own_products_cannot_be_bought(): void
    {
        $creator = User::factory()->creator()->create();
        $this->givePoints($creator, 5000);
        $draft = Product::factory()->withPersonalLicense()->create()->fresh(['licenses']);
        $own = $this->publishedProduct(500, $creator);

        $this->assertThrows(fn () => $this->purchases()->purchase($creator, $draft, null, 'x'), MarketplaceException::class);
        $this->assertThrows(fn () => $this->purchases()->purchase($creator, $own, null, 'y'), MarketplaceException::class);
        $this->assertSame(5000, $this->balanceOf($creator));
    }

    public function test_commission_follows_default_creator_and_campaign_priority(): void
    {
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 10_000);
        $creator = User::factory()->creator()->create();

        app(SettingsService::class)->set('commission.default_rate_bps', 3000);
        $this->assertSame(3000, $this->purchases()->purchase($buyer, $this->publishedProduct(1000), null, 'd')->items()->sole()->commission_bps);

        CommissionRule::query()->create(['scope' => CommissionScope::Creator, 'creator_id' => $creator->id, 'name' => 'VIP', 'rate_bps' => 1000]);
        $this->assertSame(1000, $this->purchases()->purchase($buyer, $this->publishedProduct(1000, $creator), null, 'c')->items()->sole()->commission_bps);

        CommissionRule::query()->create(['scope' => CommissionScope::Campaign, 'name' => 'Kampanya', 'rate_bps' => 500, 'ends_at' => now()->addDay()]);
        $this->assertSame(500, $this->purchases()->purchase($buyer, $this->publishedProduct(1000, $creator), null, 'k')->items()->sole()->commission_bps);
    }

    public function test_commission_change_does_not_affect_past_sales(): void
    {
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 1000);
        $order = $this->purchases()->purchase($buyer, $this->publishedProduct(1000), null, 'p');

        app(SettingsService::class)->set('commission.default_rate_bps', 5000);

        $this->assertSame(800, $order->items()->sole()->creator_amount_cents);
    }

    public function test_coupon_discount_is_applied_and_redemption_counted(): void
    {
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 1000);
        $product = $this->publishedProduct(1000);
        $coupon = Coupon::query()->forceCreate([
            'creator_id' => $product->creator_id, 'code' => 'YUZDE20', 'discount_type' => 'percent', 'discount_value' => 2000,
        ]);

        $item = $this->purchases()->purchase($buyer, $product, null, 'c', 'yuzde20')->items()->sole();

        $this->assertSame(800, $item->price_points);
        $this->assertSame(200, $this->balanceOf($buyer));
        $this->assertSame(1, $coupon->fresh()->redemptions_count);
    }

    public function test_refund_restores_points_revokes_access_and_reverses_earning(): void
    {
        $buyer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->givePoints($buyer, 1000);
        $product = $this->publishedProduct(1000);
        $item = $this->purchases()->purchase($buyer, $product, null, 'r')->items()->sole();

        $this->purchases()->refundItem($item, $admin, 'Dosya bozuk');

        $this->assertSame(1000, $this->balanceOf($buyer));
        $this->assertFalse($buyer->ownsProduct($product));
        $this->assertSame(EarningStatus::Reversed, CreatorEarning::query()->sole()->status);

        $balance = $product->creator->creatorBalance;
        $this->assertSame(0, $balance->pending_cents);
        $this->assertSame(800, $balance->reversed_cents);
        $this->assertThrows(fn () => $this->purchases()->refundItem($item, $admin, 'tekrar'), MarketplaceException::class);
    }

    public function test_free_product_is_claimed_without_points(): void
    {
        $user = User::factory()->create();
        $product = $this->publishedProduct(0);

        $this->purchases()->claimFree($user, $product);
        $this->purchases()->claimFree($user, $product);

        $this->assertTrue($user->ownsProduct($product));
        $this->assertSame(0, $this->balanceOf($user));
        $this->assertThrows(fn () => $this->purchases()->purchase($user, $product, null, 'f'), MarketplaceException::class);
    }
}
