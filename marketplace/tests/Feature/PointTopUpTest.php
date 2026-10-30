<?php

namespace Tests\Feature;

use App\Enums\PointPurchaseStatus;
use App\Enums\SystemWallet;
use App\Enums\WalletTransactionType;
use App\Enums\WebhookEventStatus;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\MarketplaceException;
use App\Models\PaymentWebhookEvent;
use App\Models\PointPackage;
use App\Models\User;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\PointTopUpService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class PointTopUpTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    private function topUps(): PointTopUpService
    {
        return app(PointTopUpService::class);
    }

    private function package(string $code): PointPackage
    {
        return PointPackage::query()->where('code', $code)->sole();
    }

    public function test_seeded_packages_follow_one_usd_equals_one_hundred_points(): void
    {
        $this->assertSame(
            [500 => 500, 1000 => 1000, 2500 => 2500, 5000 => 5000, 10000 => 10000],
            PointPackage::query()->active()->pluck('price_usd_cents', 'points')->all(),
        );
        $this->assertSame(0, (int) PointPackage::query()->sum('bonus_points'));
    }

    public function test_top_up_below_five_hundred_points_is_rejected(): void
    {
        $this->expectException(MarketplaceException::class);

        $this->topUps()->createPurchase(User::factory()->create(), 499, 'sandbox');
    }

    public function test_minimum_top_up_is_configurable_from_settings(): void
    {
        app(SettingsService::class)->set('points.minimum_top_up', 1000, group: 'points');

        $this->expectException(MarketplaceException::class);

        $this->topUps()->createPurchase(User::factory()->create(), $this->package('starter'), 'sandbox');
    }

    public function test_international_user_is_charged_in_usd(): void
    {
        $purchase = $this->topUps()->createPurchase(User::factory()->create(), $this->package('starter'), 'sandbox');

        $this->assertSame('USD', $purchase->charge_currency);
        $this->assertSame(500, $purchase->charge_amount_minor);
        $this->assertSame(500, $purchase->points);
        $this->assertNull($purchase->exchange_rate_micros);
    }

    public function test_turkish_user_is_charged_in_try_with_rate_snapshot(): void
    {
        $this->setUsdTryRate('40');

        $purchase = $this->topUps()->createPurchase(User::factory()->turkish()->create(), $this->package('standard'), 'sandbox');

        $this->assertSame('TRY', $purchase->charge_currency);
        $this->assertSame(40_000, $purchase->charge_amount_minor, '10 USD x 40 = 400,00 TL');
        $this->assertSame(40_000_000, $purchase->exchange_rate_micros);
        $this->assertSame(1000, $purchase->points);
    }

    public function test_stale_exchange_rate_blocks_try_payments(): void
    {
        $this->setUsdTryRate('40', now()->subDays(5));

        $this->expectException(MarketplaceException::class);

        $this->topUps()->createPurchase(User::factory()->turkish()->create(), $this->package('starter'), 'sandbox');
    }

    public function test_verified_webhook_credits_points_once(): void
    {
        $user = User::factory()->create();
        $purchase = $this->topUps()->createPurchase($user, $this->package('starter'), 'sandbox');
        $payload = $this->paymentSucceededPayload($purchase, 'evt_once');

        $this->sendSandboxWebhook($payload)->assertOk()->assertJson(['status' => 'processed']);
        $this->sendSandboxWebhook($payload)->assertOk();

        $this->assertSame(500, $this->balanceOf($user));
        $this->assertSame(PointPurchaseStatus::Paid, $purchase->fresh()->status);
        $this->assertSame(1, PaymentWebhookEvent::query()->where('event_id', 'evt_once')->count());
    }

    public function test_different_webhook_event_for_already_paid_purchase_does_not_credit_again(): void
    {
        $user = User::factory()->create();
        $purchase = $this->topUps()->createPurchase($user, $this->package('starter'), 'sandbox');

        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase, 'evt_a'))->assertOk();
        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase, 'evt_b'))->assertOk()->assertJson(['status' => 'ignored']);

        $this->assertSame(500, $this->balanceOf($user));
    }

    public function test_forged_webhook_signature_is_rejected_and_no_points_are_created(): void
    {
        $user = User::factory()->create();
        $purchase = $this->topUps()->createPurchase($user, $this->package('premium'), 'sandbox');

        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase), 'forged-signature')->assertStatus(400);

        $this->assertSame(0, $this->balanceOf($user));
        $this->assertSame(PointPurchaseStatus::Pending, $purchase->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', ['signature_valid' => false]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'payment.webhook_signature_invalid']);
    }

    public function test_amount_or_currency_mismatch_does_not_credit_points(): void
    {
        $user = User::factory()->create();
        $purchase = $this->topUps()->createPurchase($user, $this->package('standard'), 'sandbox');

        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase, amount: 1))->assertOk()->assertJson(['status' => 'failed']);
        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase, currency: 'TRY'))->assertOk()->assertJson(['status' => 'failed']);

        $this->assertSame(0, $this->balanceOf($user));
        $this->assertDatabaseHas('audit_logs', ['event' => 'payment.amount_mismatch']);
    }

    public function test_failed_payment_creates_no_points(): void
    {
        $user = User::factory()->create();
        $purchase = $this->topUps()->createPurchase($user, $this->package('starter'), 'sandbox');

        $this->sendSandboxWebhook([...$this->paymentSucceededPayload($purchase), 'type' => GatewayEvent::PaymentFailed, 'reason' => 'card_declined'])->assertOk();

        $this->assertSame(0, $this->balanceOf($user));
        $this->assertSame(PointPurchaseStatus::Failed, $purchase->fresh()->status);
    }

    public function test_rate_change_after_checkout_does_not_change_charge_or_points(): void
    {
        $this->setUsdTryRate('40');
        $user = User::factory()->turkish()->create();
        $purchase = $this->topUps()->createPurchase($user, $this->package('starter'), 'sandbox');

        $this->setUsdTryRate('45');
        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase))->assertOk();

        $purchase->refresh();
        $this->assertSame(20_000, $purchase->charge_amount_minor);
        $this->assertSame(40_000_000, $purchase->exchange_rate_micros);
        $this->assertSame(500, $this->balanceOf($user));
    }

    public function test_refund_of_unused_points_debits_wallet(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $purchase = $this->topUps()->createPurchase($user, $this->package('standard'), 'sandbox');
        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase))->assertOk();

        $refund = $this->topUps()->refund($purchase, $admin, 'Kullanıcı talebi');

        $this->assertSame(0, $this->balanceOf($user));
        $this->assertSame(1000, $refund->points_reversed);
        $this->assertSame(PointPurchaseStatus::Refunded, $purchase->fresh()->status);
    }

    public function test_refund_is_blocked_when_points_were_already_spent(): void
    {
        $user = User::factory()->create();
        $purchase = $this->topUps()->createPurchase($user, $this->package('starter'), 'sandbox');
        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase))->assertOk();
        $this->walletService()->debit($user, 200, WalletTransactionType::Purchase, 'spend', SystemWallet::SalesRevenue);

        $this->expectException(InsufficientPointsException::class);

        $this->topUps()->refund($purchase, User::factory()->admin()->create(), 'Talep');
    }

    public function test_chargeback_reverses_points_even_into_negative_balance(): void
    {
        $user = User::factory()->create();
        $purchase = $this->topUps()->createPurchase($user, $this->package('starter'), 'sandbox');
        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase))->assertOk();
        $this->walletService()->debit($user, 300, WalletTransactionType::Purchase, 'spend', SystemWallet::SalesRevenue);

        $this->sendSandboxWebhook([...$this->paymentSucceededPayload($purchase), 'type' => GatewayEvent::DisputeCreated])->assertOk();

        $this->assertSame(-300, $this->balanceOf($user));
        $this->assertSame(PointPurchaseStatus::Disputed, $purchase->fresh()->status);
        $this->assertSame(WebhookEventStatus::Processed, PaymentWebhookEvent::query()->latest('id')->first()->status);
    }

    public function test_disabled_provider_cannot_be_used(): void
    {
        $this->expectException(MarketplaceException::class);

        $this->topUps()->createPurchase(User::factory()->create(), $this->package('starter'), 'stripe');
    }
}
