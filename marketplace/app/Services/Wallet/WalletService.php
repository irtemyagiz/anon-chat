<?php

namespace App\Services\Wallet;

use App\Enums\SystemWallet;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientPointsException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletAdjustment;
use App\Models\WalletLedgerEntry;
use App\Models\WalletTransaction;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Çift taraflı (double-entry) puan defteri.
 *
 * Her işlem en az iki bacaktan oluşur ve bacakların toplamı sıfırdır:
 * kullanıcı cüzdanına giren her puan bir sistem hesabından çıkar.
 * Kurallar:
 *  - Bakiye yalnızca burada, kilitli satır üzerinde ve defter kaydıyla aynı
 *    transaction içinde değişir.
 *  - Her işlem benzersiz bir idempotency anahtarı taşır; aynı anahtarla
 *    tekrar çağrı yeni kayıt oluşturmaz, mevcut işlemi döndürür.
 *  - Kayıtlar silinmez; hatalar ters kayıtla (reverse) düzeltilir.
 */
class WalletService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function walletFor(User $user): Wallet
    {
        return Wallet::query()->createOrFirst(['user_id' => $user->id]);
    }

    public function systemWallet(SystemWallet $code): Wallet
    {
        return Wallet::query()->createOrFirst(['system_code' => $code->value]);
    }

    public function balanceOf(User $user): int
    {
        return (int) Wallet::query()->where('user_id', $user->id)->value('balance');
    }

    /**
     * Sistem hesabından kullanıcıya puan aktarır.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function credit(
        User $user,
        int $points,
        WalletTransactionType $type,
        string $idempotencyKey,
        SystemWallet $source,
        ?Model $subject = null,
        ?string $description = null,
        ?User $initiatedBy = null,
        array $metadata = [],
    ): WalletTransaction {
        $this->assertPositive($points);

        return $this->post($type, $idempotencyKey, [
            ['wallet' => $this->systemWallet($source), 'amount' => -$points],
            ['wallet' => $this->walletFor($user), 'amount' => $points],
        ], $description, $subject, $initiatedBy, $metadata);
    }

    /**
     * Kullanıcıdan sistem hesabına puan aktarır. Yetersiz bakiyede
     * InsufficientPointsException fırlatır (işlem türü izin vermiyorsa).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function debit(
        User $user,
        int $points,
        WalletTransactionType $type,
        string $idempotencyKey,
        SystemWallet $destination,
        ?Model $subject = null,
        ?string $description = null,
        ?User $initiatedBy = null,
        array $metadata = [],
    ): WalletTransaction {
        $this->assertPositive($points);

        return $this->post($type, $idempotencyKey, [
            ['wallet' => $this->walletFor($user), 'amount' => -$points],
            ['wallet' => $this->systemWallet($destination), 'amount' => $points],
        ], $description, $subject, $initiatedBy, $metadata);
    }

    /**
     * Bir işlemi ters kayıtla geri alır. Aynı işlem yalnızca bir kez geri alınabilir.
     */
    public function reverse(WalletTransaction $transaction, string $reason, ?User $initiatedBy = null): WalletTransaction
    {
        if ($transaction->reversal_of_id !== null) {
            throw new InvalidArgumentException('Ters kayıt işlemi tekrar geri alınamaz.');
        }

        $legs = $transaction->entries()->with('wallet')->get()
            ->map(fn (WalletLedgerEntry $entry) => ['wallet' => $entry->wallet, 'amount' => -$entry->amount])
            ->all();

        return $this->post(
            WalletTransactionType::Reversal,
            'reversal:'.$transaction->reference,
            $legs,
            $reason,
            $transaction->subject,
            $initiatedBy,
            ['original_type' => $transaction->type->value],
            $transaction,
        );
    }

    /**
     * Yönetici puan düzeltmesi. Pozitif değer ekler, negatif değer düşer.
     * Her düzeltme gerekçesiyle birlikte denetim kaydına yazılır.
     */
    public function adjust(User $user, int $points, string $reason, User $admin, string $idempotencyKey): WalletTransaction
    {
        if ($points === 0) {
            throw new InvalidArgumentException('Düzeltme tutarı sıfır olamaz.');
        }

        return DB::transaction(function () use ($user, $points, $reason, $admin, $idempotencyKey): WalletTransaction {
            $transaction = $this->post(WalletTransactionType::AdminAdjustment, $idempotencyKey, [
                ['wallet' => $this->systemWallet(SystemWallet::Adjustments), 'amount' => -$points],
                ['wallet' => $this->walletFor($user), 'amount' => $points],
            ], $reason, $user, $admin);

            if ($transaction->wasRecentlyCreated) {
                WalletAdjustment::forceCreate([
                    'wallet_transaction_id' => $transaction->id,
                    'user_id' => $user->id,
                    'admin_id' => $admin->id,
                    'points' => $points,
                    'reason' => $reason,
                ]);

                $this->audit->record('wallet.adjusted', $user, null, ['points' => $points], $admin, [
                    'reason' => $reason,
                    'transaction' => $transaction->reference,
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Defter kaydı oluşturur. Tüm puan hareketleri bu metottan geçer.
     *
     * @param  list<array{wallet: Wallet, amount: int}>  $legs
     * @param  array<string, mixed>  $metadata
     */
    public function post(
        WalletTransactionType $type,
        string $idempotencyKey,
        array $legs,
        ?string $description = null,
        ?Model $subject = null,
        ?User $initiatedBy = null,
        array $metadata = [],
        ?WalletTransaction $reversalOf = null,
    ): WalletTransaction {
        if (count($legs) < 2 || array_sum(array_column($legs, 'amount')) !== 0) {
            throw new InvalidArgumentException('Defter bacaklarının toplamı sıfır olmalıdır.');
        }

        try {
            return DB::transaction(function () use ($type, $idempotencyKey, $legs, $description, $subject, $initiatedBy, $metadata, $reversalOf): WalletTransaction {
                $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    return $existing;
                }

                $walletIds = collect($legs)->map(fn (array $leg) => $leg['wallet']->id)->unique()->sort()->values();

                /** Kilitler her zaman id sırasıyla alınır; böylece kilitlenme (deadlock) önlenir. */
                $wallets = Wallet::query()->whereIn('id', $walletIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                foreach ($legs as $leg) {
                    $wallet = $wallets[$leg['wallet']->id];
                    $newBalance = (int) $wallet->balance + $leg['amount'];

                    if ($leg['amount'] < 0 && $wallet->user_id !== null && $newBalance < 0 && ! $this->mayGoNegative($type, $reversalOf)) {
                        throw new InsufficientPointsException(-$leg['amount'], (int) $wallet->balance);
                    }

                    $wallet->balance = $newBalance;
                }

                $transaction = WalletTransaction::forceCreate([
                    'reference' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'type' => $type,
                    'description' => $description,
                    'reversal_of_id' => $reversalOf?->id,
                    'initiated_by' => $initiatedBy?->id,
                    'subject_type' => $subject?->getMorphClass(),
                    'subject_id' => $subject?->getKey(),
                    'metadata' => $metadata ?: null,
                ]);

                foreach ($legs as $leg) {
                    $wallet = $wallets[$leg['wallet']->id];
                    $this->applyTotals($wallet, $type, $leg['amount']);

                    $wallet->save();

                    WalletLedgerEntry::forceCreate([
                        'wallet_transaction_id' => $transaction->id,
                        'wallet_id' => $wallet->id,
                        'amount' => $leg['amount'],
                        'balance_after' => $wallet->balance,
                    ]);
                }

                return $transaction;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            /** Aynı anahtarla eşzamanlı ikinci istek: ilk isteğin oluşturduğu kaydı döndür. */
            return WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }

    private function mayGoNegative(WalletTransactionType $type, ?WalletTransaction $reversalOf): bool
    {
        return $type->allowsNegativeBalance() || $reversalOf !== null;
    }

    private function applyTotals(Wallet $wallet, WalletTransactionType $type, int $amount): void
    {
        if ($wallet->user_id === null) {
            return;
        }

        match (true) {
            $type === WalletTransactionType::TopUp && $amount > 0 => $wallet->total_loaded += $amount,
            $type === WalletTransactionType::Purchase && $amount < 0 => $wallet->total_spent += -$amount,
            $type === WalletTransactionType::PurchaseRefund && $amount > 0 => $wallet->total_spent = max(0, $wallet->total_spent - $amount),
            in_array($type, [WalletTransactionType::TopUpRefund, WalletTransactionType::Chargeback], true) && $amount < 0 => $wallet->total_loaded = max(0, $wallet->total_loaded + $amount),
            default => null,
        };
    }

    private function assertPositive(int $points): void
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Puan miktarı pozitif olmalıdır.');
        }
    }
}
