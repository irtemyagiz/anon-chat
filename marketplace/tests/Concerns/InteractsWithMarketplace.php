<?php

namespace Tests\Concerns;

use App\Enums\SystemWallet;
use App\Enums\WalletTransactionType;
use App\Http\Middleware\RequireTwoFactor;
use App\Models\ExchangeRate;
use App\Models\PointPurchase;
use App\Models\Product;
use App\Models\User;
use App\Services\Currency\DecimalRate;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\Gateways\SandboxGateway;
use App\Services\Security\TwoFactorAuthenticator;
use App\Services\Wallet\WalletService;
use DateTimeInterface;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait InteractsWithMarketplace
{
    protected function walletService(): WalletService
    {
        return app(WalletService::class);
    }

    /**
     * 2FA kurulmuş ve bu oturumda doğrulanmış yönetici (veya verilen kullanıcı) olarak oturum açar.
     */
    protected function actingAsAdmin(?User $user = null): User
    {
        $user ??= User::factory()->admin()->create();
        $user->forceFill(['two_factor_secret' => app(TwoFactorAuthenticator::class)->generateSecret(), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($user)->withSession([RequireTwoFactor::SESSION_KEY => $user->id]);

        return $user;
    }

    protected function currentTotp(string $secret): string
    {
        $method = new \ReflectionMethod(TwoFactorAuthenticator::class, 'codeAt');

        return $method->invoke(app(TwoFactorAuthenticator::class), $secret, intdiv(time(), 30));
    }

    protected function givePoints(User $user, int $points): void
    {
        $this->walletService()->credit($user, $points, WalletTransactionType::Promotion, 'test-grant:'.Str::uuid(), SystemWallet::Promotions);
    }

    protected function balanceOf(User $user): int
    {
        return $this->walletService()->balanceOf($user);
    }

    protected function publishedProduct(int $priceCents, ?User $creator = null): Product
    {
        $factory = Product::factory()->priced($priceCents)->published()->withPersonalLicense();

        if ($creator !== null) {
            $factory = $factory->for($creator, 'creator');
        }

        return $factory->create()->fresh(['licenses', 'category']);
    }

    protected function setUsdTryRate(string $rate, ?DateTimeInterface $fetchedAt = null): ExchangeRate
    {
        return ExchangeRate::forceCreate([
            'base_currency' => 'USD',
            'quote_currency' => 'TRY',
            'rate_micros' => DecimalRate::toMicros($rate),
            'source' => 'test',
            'fetched_at' => $fetchedAt ?? now(),
        ]);
    }

    /**
     * Sandbox sağlayıcısına imzalı webhook gönderir.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function sendSandboxWebhook(array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature ??= hash_hmac('sha256', $body, config('marketplace.payments.gateways.sandbox.secret'));

        return $this->call('POST', route('webhooks.payments', 'sandbox'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_'.str_replace('-', '_', strtoupper(SandboxGateway::SIGNATURE_HEADER)) => $signature,
        ], $body);
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentSucceededPayload(PointPurchase $purchase, ?string $eventId = null, ?int $amount = null, ?string $currency = null): array
    {
        return [
            'id' => $eventId ?? 'evt_'.Str::random(12),
            'type' => GatewayEvent::PaymentSucceeded,
            'purchase' => $purchase->uuid,
            'transaction' => 'txn_'.Str::random(12),
            'amount' => $amount ?? $purchase->charge_amount_minor,
            'currency' => $currency ?? $purchase->charge_currency,
        ];
    }
}
