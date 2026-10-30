<?php

namespace Tests\Feature;

use App\Enums\EarningStatus;
use App\Enums\PayoutRequestStatus;
use App\Exceptions\MarketplaceException;
use App\Models\CreatorEarning;
use App\Models\CreatorVerification;
use App\Models\User;
use App\Services\Earnings\CreatorEarningService;
use App\Services\Earnings\PayoutService;
use App\Services\Sales\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class CreatorEarningsTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    /**
     * 10 USD'lik ürün için bir satış yapar ve bekleme süresini geçirip kazancı serbest bırakır.
     */
    private function creatorWithAvailableEarnings(int $sales = 10): User
    {
        $creator = User::factory()->creator()->create();
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 1000 * $sales);

        for ($i = 0; $i < $sales; $i++) {
            app(PurchaseService::class)->purchase($buyer, $this->publishedProduct(1000, $creator), null, "sale-{$i}");
        }

        $this->travel((int) config('marketplace.earnings.hold_days') + 1)->days();
        app(CreatorEarningService::class)->releaseMatured();

        return $creator->fresh();
    }

    private function verify(User $creator): void
    {
        CreatorVerification::query()->forceCreate([
            'creator_profile_id' => $creator->creatorProfile->id,
            'legal_name' => $creator->full_name,
            'entity_type' => 'individual',
            'country_code' => 'TR',
            'status' => 'approved',
        ]);
    }

    public function test_earnings_become_available_only_after_hold_period(): void
    {
        $creator = User::factory()->creator()->create();
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 1000);
        app(PurchaseService::class)->purchase($buyer, $this->publishedProduct(1000, $creator), null, 's');

        $this->assertSame(0, app(CreatorEarningService::class)->releaseMatured());

        $this->travel((int) config('marketplace.earnings.hold_days') + 1)->days();
        $this->assertSame(1, app(CreatorEarningService::class)->releaseMatured());

        $balance = $creator->creatorBalance()->sole();
        $this->assertSame(0, $balance->pending_cents);
        $this->assertSame(800, $balance->available_cents);
    }

    public function test_unverified_creator_cannot_request_payout(): void
    {
        $creator = $this->creatorWithAvailableEarnings();

        $this->expectException(MarketplaceException::class);

        app(PayoutService::class)->request($creator, 5000, 'USD', 'bank_transfer', ['iban' => 'TR000000000000000000000000']);
    }

    public function test_payout_request_cannot_exceed_withdrawable_balance(): void
    {
        $creator = $this->creatorWithAvailableEarnings();
        $this->verify($creator);

        app(PayoutService::class)->request($creator, 6000, 'USD', 'bank_transfer', ['iban' => 'TR1']);

        $this->expectException(MarketplaceException::class);
        app(PayoutService::class)->request($creator, 5000, 'USD', 'bank_transfer', ['iban' => 'TR1']);
    }

    public function test_rejected_request_releases_reserve_and_paid_request_settles_balance(): void
    {
        $creator = $this->creatorWithAvailableEarnings();
        $this->verify($creator);
        $admin = User::factory()->admin()->create();
        $payouts = app(PayoutService::class);

        $rejected = $payouts->request($creator, 8000, 'USD', 'bank_transfer', ['iban' => 'TR12345678']);
        $payouts->reject($rejected, $admin, 'IBAN hatalı');
        $this->assertSame(0, $creator->creatorBalance()->sole()->reserved_cents);

        $request = $payouts->request($creator, 8000, 'USD', 'bank_transfer', ['iban' => 'TR12345678']);
        $payouts->approve($request, $admin);
        $payouts->markPaid($request, $admin, 'USD', 8000, 'SWIFT-1');

        $balance = $creator->creatorBalance()->sole();
        $this->assertSame(0, $balance->available_cents);
        $this->assertSame(0, $balance->reserved_cents);
        $this->assertSame(8000, $balance->paid_cents);
        $this->assertSame(PayoutRequestStatus::Paid, $request->fresh()->status);
        $this->assertSame(10, CreatorEarning::query()->where('status', EarningStatus::Paid)->count());
        $this->assertSame('••••••5678', $request->fresh()->maskedAccount());
        $this->assertDatabaseHas('audit_logs', ['event' => 'payout.paid', 'actor_id' => $admin->id]);
    }

    public function test_payout_details_are_encrypted_at_rest(): void
    {
        $creator = $this->creatorWithAvailableEarnings();
        $this->verify($creator);

        $request = app(PayoutService::class)->request($creator, 5000, 'USD', 'bank_transfer', ['iban' => 'TR330006100519786457841326']);

        $raw = DB::table('creator_payout_requests')->where('id', $request->id)->value('payout_details');
        $this->assertStringNotContainsString('TR330006100519786457841326', $raw);
    }
}
