<?php

namespace Tests\Feature;

use App\Enums\SystemWallet;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientPointsException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class WalletLedgerTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    public function test_every_transaction_balances_to_zero_across_its_legs(): void
    {
        $user = User::factory()->create();

        $this->givePoints($user, 1500);
        $this->walletService()->debit($user, 700, WalletTransactionType::Purchase, 'k1', SystemWallet::SalesRevenue);

        $this->assertSame(800, $this->balanceOf($user));
        $this->assertSame(0, (int) WalletLedgerEntry::query()->sum('amount'));

        WalletTransaction::query()->each(function (WalletTransaction $transaction): void {
            $this->assertSame(0, (int) $transaction->entries()->sum('amount'));
        });
    }

    public function test_debit_beyond_balance_is_rejected_and_nothing_is_written(): void
    {
        $user = User::factory()->create();
        $this->givePoints($user, 300);
        $transactionsBefore = WalletTransaction::query()->count();

        try {
            $this->walletService()->debit($user, 800, WalletTransactionType::Purchase, 'too-much', SystemWallet::SalesRevenue);
            $this->fail('Yetersiz bakiye hatası bekleniyordu.');
        } catch (InsufficientPointsException $exception) {
            $this->assertSame(500, $exception->shortfall());
        }

        $this->assertSame(300, $this->balanceOf($user));
        $this->assertSame($transactionsBefore, WalletTransaction::query()->count());
    }

    public function test_same_idempotency_key_never_posts_twice(): void
    {
        $user = User::factory()->create();

        $first = $this->walletService()->credit($user, 500, WalletTransactionType::TopUp, 'topup:abc', SystemWallet::TopUpClearing);
        $second = $this->walletService()->credit($user, 500, WalletTransactionType::TopUp, 'topup:abc', SystemWallet::TopUpClearing);

        $this->assertTrue($first->is($second));
        $this->assertSame(500, $this->balanceOf($user));
    }

    public function test_reversal_creates_a_counter_entry_and_keeps_the_original(): void
    {
        $user = User::factory()->create();
        $transaction = $this->walletService()->credit($user, 500, WalletTransactionType::TopUp, 'topup:rev', SystemWallet::TopUpClearing);

        $reversal = $this->walletService()->reverse($transaction, 'Hatalı yükleme');
        $again = $this->walletService()->reverse($transaction, 'Tekrar');

        $this->assertTrue($reversal->is($again));
        $this->assertSame(0, $this->balanceOf($user));
        $this->assertDatabaseHas('wallet_transactions', ['id' => $transaction->id]);
        $this->assertSame($transaction->id, $reversal->reversal_of_id);
    }

    public function test_admin_adjustment_is_audited_with_actor_and_reason(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->walletService()->adjust($user, 250, 'Destek talebi #12 telafisi', $admin, 'adjust:1');

        $this->assertSame(250, $this->balanceOf($user));
        $this->assertDatabaseHas('wallet_adjustments', ['user_id' => $user->id, 'admin_id' => $admin->id, 'points' => 250]);

        $log = AuditLog::query()->where('event', 'wallet.adjusted')->sole();
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame('Destek talebi #12 telafisi', $log->context['reason']);
    }

    public function test_chargeback_may_push_balance_negative_but_purchase_may_not(): void
    {
        $user = User::factory()->create();
        $this->givePoints($user, 100);

        $this->walletService()->debit($user, 500, WalletTransactionType::Chargeback, 'cb:1', SystemWallet::TopUpClearing);

        $this->assertSame(-400, $this->balanceOf($user));

        $this->expectException(InsufficientPointsException::class);
        $this->walletService()->debit($user, 1, WalletTransactionType::Purchase, 'p:1', SystemWallet::SalesRevenue);
    }
}
