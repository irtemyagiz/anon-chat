<?php

namespace Tests\Feature;

use App\Exceptions\AlreadyOwnedException;
use App\Exceptions\InsufficientPointsException;
use App\Models\Order;
use App\Models\PurchaseEntitlement;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\Sales\PurchaseService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;
use Throwable;

/**
 * Gerçek eşzamanlılık testi: birden fazla süreç aynı anda satın alma yapar.
 *
 * Satır kilitleri gerektirdiği için yalnızca MySQL/MariaDB üzerinde ve pcntl
 * eklentisi olan (Linux/macOS) ortamlarda çalışır:
 *   DB_CONNECTION=mariadb DB_DATABASE=... php artisan test --group=concurrency
 */
#[Group('concurrency')]
class ConcurrentSpendingTest extends TestCase
{
    use DatabaseTruncation, InteractsWithMarketplace;

    private const WORKERS = 8;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Eşzamanlılık testi MySQL/MariaDB ve pcntl gerektirir.');
        }
    }

    public function test_parallel_purchases_cannot_double_spend_the_same_balance(): void
    {
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 1000);
        $products = [$this->publishedProduct(700), $this->publishedProduct(700)];

        $this->runInParallel(fn (int $worker) => app(PurchaseService::class)
            ->purchase(User::query()->find($buyer->id), $products[$worker % 2]->fresh(['licenses']), null, "parallel-{$worker}"));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(300, $this->balanceOf($buyer));
        $this->assertSame(0, (int) WalletLedgerEntry::query()->sum('amount'));
    }

    public function test_parallel_purchases_of_the_same_product_grant_a_single_entitlement(): void
    {
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 10_000);
        $product = $this->publishedProduct(1000);

        $this->runInParallel(fn (int $worker) => app(PurchaseService::class)
            ->purchase(User::query()->find($buyer->id), $product->fresh(['licenses']), null, "same-{$worker}"));

        $this->assertSame(1, PurchaseEntitlement::query()->count());
        $this->assertSame(9000, $this->balanceOf($buyer));
    }

    /**
     * Her işçi ayrı bir süreçte ve ayrı veritabanı bağlantısıyla, aynı anda başlar.
     */
    private function runInParallel(callable $work): void
    {
        $resultFile = tempnam(sys_get_temp_dir(), 'concurrency');
        $startAt = microtime(true) + 0.5;
        $pids = [];

        DB::disconnect();

        for ($worker = 0; $worker < self::WORKERS; $worker++) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                DB::reconnect();
                usleep((int) max(0, ($startAt - microtime(true)) * 1_000_000));

                try {
                    $work($worker);
                } catch (Throwable $exception) {
                    file_put_contents($resultFile, get_class($exception).PHP_EOL, FILE_APPEND | LOCK_EX);
                }

                posix_kill(getmypid(), SIGKILL);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        /** Kilitler doğru çalışıyorsa başarısız işçiler yalnızca iş kuralı hatası alır; deadlock veya unique ihlali oluşmaz. */
        $failures = array_unique(array_filter(explode(PHP_EOL, (string) file_get_contents($resultFile))));
        unlink($resultFile);

        $this->assertEmpty(array_diff($failures, [AlreadyOwnedException::class, InsufficientPointsException::class]), implode(', ', $failures));
    }
}
