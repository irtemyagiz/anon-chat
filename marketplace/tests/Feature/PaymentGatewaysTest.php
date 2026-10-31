<?php

namespace Tests\Feature;

use App\Enums\PointPurchaseStatus;
use App\Models\PaymentProvider;
use App\Models\PointPackage;
use App\Models\PointPurchase;
use App\Models\User;
use App\Services\Payments\Gateways\PaytrGateway;
use App\Services\Payments\PointTopUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

/**
 * Gerçek sağlayıcı adaptörleri, sağlayıcıların belgelenmiş API biçimleriyle (Http::fake)
 * ve gerçek imza hesaplarıyla test edilir. Canlı hesapla deneme yapılmamıştır.
 */
class PaymentGatewaysTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    private function enable(string $code, array $settings): void
    {
        config(["marketplace.payments.gateways.{$code}" => [...config("marketplace.payments.gateways.{$code}"), ...$settings]]);
        PaymentProvider::query()->where('code', $code)->update(['is_enabled' => true]);
    }

    private function purchase(User $user, string $provider): PointPurchase
    {
        $topUps = app(PointTopUpService::class);
        $purchase = $topUps->createPurchase($user, PointPackage::query()->where('code', 'starter')->sole(), $provider, '203.0.113.7');
        $topUps->startCheckout($purchase);

        return $purchase->fresh();
    }

    private function stripeWebhook(array $event, string $secret = 'whsec_test', ?int $timestamp = null): TestResponse
    {
        $body = json_encode($event);
        $timestamp ??= time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        return $this->call('POST', route('webhooks.payments', 'stripe'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}"], $body);
    }

    public function test_real_providers_stay_unavailable_until_keys_are_configured(): void
    {
        $this->actingAsAdmin();

        foreach (['iyzico', 'paytr', 'stripe'] as $code) {
            $provider = PaymentProvider::query()->where('code', $code)->sole();
            $this->post(route('admin.payments.providers.toggle', $provider))->assertSessionHasErrors('provider');
            $this->assertFalse($provider->fresh()->is_enabled);
        }

        $this->get(route('admin.payments.index'))->assertSee('STRIPE_SECRET_KEY')->assertSee(route('webhooks.payments', 'paytr'));
        $this->postJson(route('webhooks.payments', 'stripe'), [])->assertNotFound();

        config(['marketplace.payments.gateways.stripe.secret_key' => 'sk_test', 'marketplace.payments.gateways.stripe.webhook_secret' => 'whsec_test']);
        $this->post(route('admin.payments.providers.toggle', PaymentProvider::query()->where('code', 'stripe')->sole()))->assertSessionHasNoErrors();
    }

    public function test_stripe_checkout_webhook_dispute_and_refund(): void
    {
        $this->enable('stripe', ['secret_key' => 'sk_test_123', 'webhook_secret' => 'whsec_test']);
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
            'api.stripe.com/v1/refunds' => Http::response(['id' => 're_test_1', 'status' => 'succeeded']),
        ]);
        $user = User::factory()->create(['country_code' => 'DE']);

        $purchase = $this->purchase($user, 'stripe');
        $this->assertSame('cs_test_1', $purchase->provider_reference);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request->hasHeader('Authorization', 'Bearer sk_test_123')
            && $request->hasHeader('Idempotency-Key', 'checkout-'.$purchase->uuid)
            && $request['line_items'][0]['price_data']['unit_amount'] === 500
            && $request['line_items'][0]['price_data']['currency'] === 'usd'
            && $request['client_reference_id'] === $purchase->uuid);

        $event = ['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_test_1', 'client_reference_id' => $purchase->uuid, 'payment_status' => 'paid',
            'amount_total' => 500, 'currency' => 'usd', 'payment_intent' => 'pi_test_1',
        ]]];

        $this->stripeWebhook($event, 'whsec_yanlis')->assertStatus(400);
        $this->stripeWebhook($event, timestamp: time() - 600)->assertStatus(400);
        $this->assertSame(0, $this->balanceOf($user));

        $this->stripeWebhook($event)->assertOk();
        $this->stripeWebhook($event)->assertOk();
        $this->assertSame(500, $this->balanceOf($user), 'Tekrarlanan olay ikinci kez puan yüklemez');
        $this->assertSame(PointPurchaseStatus::Paid, $purchase->fresh()->status);

        $this->stripeWebhook(['id' => 'evt_2', 'type' => 'customer.created', 'data' => ['object' => ['id' => 'cus_1']]])->assertOk();

        $this->actingAsAdmin();
        $this->post(route('admin.payments.refund', $purchase), ['reason' => 'Kullanıcı talebi'])->assertSessionHasNoErrors();
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.stripe.com/v1/refunds' && $request['payment_intent'] === 'pi_test_1' && (int) $request['amount'] === 500);
        $this->assertSame(PointPurchaseStatus::Refunded, $purchase->fresh()->status);
    }

    public function test_stripe_dispute_reverses_points(): void
    {
        $this->enable('stripe', ['secret_key' => 'sk_test_123', 'webhook_secret' => 'whsec_test']);
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_2', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_2'])]);
        $user = User::factory()->create(['country_code' => 'US']);
        $purchase = $this->purchase($user, 'stripe');

        $this->stripeWebhook(['id' => 'evt_3', 'type' => 'checkout.session.completed', 'data' => ['object' => ['client_reference_id' => $purchase->uuid, 'payment_status' => 'paid', 'amount_total' => 500, 'currency' => 'usd', 'payment_intent' => 'pi_test_2']]])->assertOk();
        $this->stripeWebhook(['id' => 'evt_4', 'type' => 'charge.dispute.created', 'data' => ['object' => ['id' => 'dp_1', 'payment_intent' => 'pi_test_2', 'amount' => 500, 'currency' => 'usd', 'reason' => 'fraudulent']]])->assertOk();

        $this->assertSame(PointPurchaseStatus::Disputed, $purchase->fresh()->status);
        $this->assertSame(0, $this->balanceOf($user));
    }

    public function test_paytr_token_iframe_and_signed_callback(): void
    {
        $this->setUsdTryRate('40.000000');
        $this->enable('paytr', ['merchant_id' => '123456', 'merchant_key' => 'anahtar', 'merchant_salt' => 'tuz']);
        Http::fake(['www.paytr.com/odeme/api/get-token' => Http::response(['status' => 'success', 'token' => 'paytrtoken123'])]);
        $user = User::factory()->create(['country_code' => 'TR']);

        $purchase = $this->purchase($user, 'paytr');
        $this->assertSame('TRY', $purchase->charge_currency);
        $oid = PaytrGateway::merchantOid($purchase->uuid);

        Http::assertSent(function (HttpRequest $request) use ($purchase, $oid, $user) {
            $hash = '123456'.'203.0.113.7'.$oid.$user->email.$purchase->charge_amount_minor.$request['user_basket'].'1'.'0'.'TL'.'1';

            return $request['merchant_oid'] === $oid
                && $request['payment_amount'] === (string) $purchase->charge_amount_minor
                && $request['paytr_token'] === base64_encode(hash_hmac('sha256', $hash.'tuz', 'anahtar', true));
        });

        $this->actingAs($user)->get(route('top-up.paytr', $purchase))->assertOk()->assertSee('https://www.paytr.com/odeme/guvenli/paytrtoken123', false);
        $this->actingAs(User::factory()->create())->get(route('top-up.paytr', $purchase))->assertNotFound();

        $amount = (string) $purchase->charge_amount_minor;
        $callback = fn (string $status, string $key = 'anahtar') => $this->post(route('webhooks.payments', 'paytr'), [
            'merchant_oid' => $oid, 'status' => $status, 'total_amount' => $amount, 'payment_amount' => $amount, 'currency' => 'TL',
            'hash' => base64_encode(hash_hmac('sha256', $oid.'tuz'.$status.$amount, $key, true)),
        ]);

        $callback('success', 'sahte')->assertStatus(400);
        $this->assertSame(0, $this->balanceOf($user));

        $callback('success')->assertOk()->assertSeeText('OK');
        $callback('success')->assertOk()->assertSeeText('OK');
        $this->assertSame(500, $this->balanceOf($user));
    }

    public function test_paytr_failed_payment_creates_no_points(): void
    {
        $this->setUsdTryRate('40.000000');
        $this->enable('paytr', ['merchant_id' => '1', 'merchant_key' => 'k', 'merchant_salt' => 's']);
        Http::fake(['www.paytr.com/*' => Http::response(['status' => 'success', 'token' => 't'])]);
        $user = User::factory()->create(['country_code' => 'TR']);
        $purchase = $this->purchase($user, 'paytr');
        $oid = PaytrGateway::merchantOid($purchase->uuid);
        $amount = (string) $purchase->charge_amount_minor;

        $this->post(route('webhooks.payments', 'paytr'), [
            'merchant_oid' => $oid, 'status' => 'failed', 'total_amount' => $amount, 'failed_reason_msg' => 'Yetersiz bakiye',
            'hash' => base64_encode(hash_hmac('sha256', $oid.'s'.'failed'.$amount, 'k', true)),
        ])->assertOk();

        $this->assertSame(PointPurchaseStatus::Failed, $purchase->fresh()->status);
        $this->assertSame(0, $this->balanceOf($user));
    }

    public function test_iyzico_checkout_signature_and_verified_callback(): void
    {
        $this->setUsdTryRate('40.000000');
        $this->enable('iyzico', ['api_key' => 'api-anahtar', 'secret_key' => 'gizli-anahtar']);
        $user = User::factory()->create(['country_code' => 'TR']);
        $state = ['paymentStatus' => 'SUCCESS'];

        Http::fake(function (HttpRequest $request) use (&$state, &$purchaseRef) {
            if (str_ends_with($request->url(), '/checkoutform/initialize/auth/ecom')) {
                return Http::response(['status' => 'success', 'token' => 'tkn-1', 'paymentPageUrl' => 'https://sandbox-cpp.iyzipay.com?token=tkn-1']);
            }

            if ($request['token'] !== 'tkn-1') {
                return Http::response(['status' => 'failure', 'errorMessage' => 'Geçersiz token']);
            }

            return Http::response(['status' => 'success', 'paymentStatus' => $state['paymentStatus'], 'fraudStatus' => 1, 'paymentId' => '987',
                'paidPrice' => $purchaseRef->charge_amount_minor / 100, 'currency' => 'TRY', 'basketId' => $purchaseRef->uuid,
                'itemTransactions' => [['paymentTransactionId' => '654']]]);
        });

        $purchaseRef = $purchase = $this->purchase($user, 'iyzico');
        $this->assertSame('tkn-1', $purchase->provider_reference);

        Http::assertSent(function (HttpRequest $request) {
            if (! str_ends_with($request->url(), '/initialize/auth/ecom')) {
                return false;
            }

            parse_str(str_replace(['&', ':'], ['&', '='], base64_decode(substr($request->header('Authorization')[0], 8))), $auth);
            $expected = hash_hmac('sha256', $auth['randomKey'].'/payment/iyzipos/checkoutform/initialize/auth/ecom'.$request->body(), 'gizli-anahtar');

            return $auth['apiKey'] === 'api-anahtar' && hash_equals($expected, $auth['signature']) && $request['basketItems'][0]['itemType'] === 'VIRTUAL';
        });

        $this->post(route('webhooks.payments', 'iyzico'), ['token' => 'sahte'])->assertRedirect(route('wallet'));
        $this->assertSame(0, $this->balanceOf($user));

        $this->post(route('webhooks.payments', 'iyzico'), ['token' => 'tkn-1'])->assertRedirect(route('top-up.result', $purchase));
        $this->postJson(route('webhooks.payments', 'iyzico'), ['token' => 'tkn-1', 'iyziEventType' => 'CHECKOUT_FORM_AUTH'])->assertOk();
        $this->assertSame(500, $this->balanceOf($user), 'Tarayıcı dönüşü ve sunucu bildirimi aynı ödemeyi bir kez yükler');
        $this->assertDatabaseHas('payment_transactions', ['point_purchase_id' => $purchase->id, 'provider_transaction_id' => '654']);
    }

    public function test_iyzico_failed_payment_is_marked_failed(): void
    {
        $this->setUsdTryRate('40.000000');
        $this->enable('iyzico', ['api_key' => 'a', 'secret_key' => 'b']);
        $user = User::factory()->create(['country_code' => 'TR']);
        Http::fake(function (HttpRequest $request) use (&$purchaseRef) {
            return str_ends_with($request->url(), '/initialize/auth/ecom')
                ? Http::response(['status' => 'success', 'token' => 'tkn-2', 'paymentPageUrl' => 'https://sandbox-cpp.iyzipay.com?token=tkn-2'])
                : Http::response(['status' => 'success', 'paymentStatus' => 'FAILURE', 'basketId' => $purchaseRef->uuid, 'paidPrice' => 0, 'currency' => 'TRY', 'errorMessage' => 'Kart reddedildi']);
        });
        $purchaseRef = $purchase = $this->purchase($user, 'iyzico');

        $this->post(route('webhooks.payments', 'iyzico'), ['token' => 'tkn-2'])->assertRedirect(route('top-up.result', $purchase));
        $this->assertSame(PointPurchaseStatus::Failed, $purchase->fresh()->status);
        $this->assertSame(0, $this->balanceOf($user));
        $this->assertTrue(Str::isUuid($purchase->uuid));
    }
}
