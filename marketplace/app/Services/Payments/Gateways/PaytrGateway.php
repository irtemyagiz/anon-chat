<?php

namespace App\Services\Payments\Gateways;

use App\Exceptions\MarketplaceException;
use App\Models\PaymentWebhookEvent;
use App\Models\PointPurchase;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\InvalidWebhookSignatureException;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\RespondsToWebhook;
use App\Services\Points\PointConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * PayTR iFrame API (Türkiye, TL).
 *
 * - Ödeme: POST /odeme/api/get-token → dönen token ile PayTR ödeme formu site içinde
 *   iframe olarak açılır (/puan-yukle/paytr/{uuid}).
 * - Bildirim (callback): PayTR mağaza paneline "Bildirim URL" olarak
 *   /webhooks/payments/paytr girilir. hash = base64(HMAC-SHA256(merchant_oid + salt +
 *   status + total_amount, merchant_key)). Yanıt düz metin "OK" olmalıdır; aksi hâlde
 *   PayTR bildirimi tekrarlar (tekrarlar olay kimliğiyle tekilleştirilir).
 * - İade: POST /odeme/iade.
 *
 * merchant_oid yalnızca harf/rakam kabul ettiği için sipariş UUID'si tiresiz gönderilir.
 */
class PaytrGateway implements PaymentGateway, RespondsToWebhook
{
    public const BASE_URL = 'https://www.paytr.com';

    public function __construct(
        private readonly ?string $merchantId,
        private readonly ?string $merchantKey,
        private readonly ?string $merchantSalt,
        private readonly bool $testMode = true,
    ) {
        if (blank($this->merchantId) || blank($this->merchantKey) || blank($this->merchantSalt)) {
            throw new LogicException('PAYTR_MERCHANT_ID, PAYTR_MERCHANT_KEY ve PAYTR_MERCHANT_SALT tanımlı olmalıdır.');
        }
    }

    public function code(): string
    {
        return 'paytr';
    }

    public function createCheckout(PointPurchase $purchase): CheckoutSession
    {
        $user = $purchase->user;
        $oid = self::merchantOid($purchase->uuid);
        $amount = (string) $purchase->charge_amount_minor;
        $currency = $purchase->charge_currency === 'TRY' ? 'TL' : $purchase->charge_currency;
        $basket = base64_encode((string) json_encode([["{$purchase->points} POINT", PointConverter::formatCents((int) $purchase->charge_amount_minor), 1]], JSON_UNESCAPED_UNICODE));
        $ip = $purchase->ip_address ?? request()->ip();
        $fields = [
            'merchant_id' => $this->merchantId,
            'user_ip' => $ip,
            'merchant_oid' => $oid,
            'email' => $user->email,
            'payment_amount' => $amount,
            'user_basket' => $basket,
            'no_installment' => '1',
            'max_installment' => '0',
            'currency' => $currency,
            'test_mode' => $this->testMode ? '1' : '0',
        ];

        $hash = $fields['merchant_id'].$ip.$oid.$user->email.$amount.$basket.$fields['no_installment'].$fields['max_installment'].$currency.$fields['test_mode'];

        $response = Http::asForm()->timeout(20)->post(self::BASE_URL.'/odeme/api/get-token', [
            ...$fields,
            'paytr_token' => $this->sign($hash.$this->merchantSalt),
            'debug_on' => $this->testMode ? '1' : '0',
            'user_name' => trim("{$user->first_name} {$user->last_name}"),
            'user_address' => config('marketplace.payments.buyer_defaults.address'),
            'user_phone' => config('marketplace.payments.buyer_defaults.phone'),
            'merchant_ok_url' => route('top-up.result', $purchase),
            'merchant_fail_url' => route('top-up.result', $purchase),
            'timeout_limit' => '30',
            'lang' => $user->locale === 'en' ? 'en' : 'tr',
        ]);

        if ($response->json('status') !== 'success' || blank($response->json('token'))) {
            Log::error('PayTR token alınamadı.', ['purchase' => $purchase->uuid, 'status' => $response->status(), 'reason' => $response->json('reason')]);

            throw new MarketplaceException(__('Ödeme sayfası açılamadı. Lütfen biraz sonra tekrar deneyin.'));
        }

        return new CheckoutSession(route('top-up.paytr', $purchase), (string) $response->json('token'));
    }

    public function parseWebhook(Request $request): GatewayEvent
    {
        $oid = (string) $request->input('merchant_oid');
        $status = (string) $request->input('status');
        $totalAmount = (string) $request->input('total_amount');
        $expected = $this->sign($oid.$this->merchantSalt.$status.$totalAmount);

        if ($oid === '' || ! hash_equals($expected, (string) $request->input('hash'))) {
            throw new InvalidWebhookSignatureException('PayTR bildirim imzası geçersiz.');
        }

        $currency = (string) $request->input('currency', 'TL');

        return new GatewayEvent(
            eventId: "paytr:{$oid}:{$status}",
            type: $status === 'success' ? GatewayEvent::PaymentSucceeded : GatewayEvent::PaymentFailed,
            purchaseUuid: self::uuidFromMerchantOid($oid),
            providerTransactionId: $oid,
            /** Taksit faizi total_amount'a eklenebilir; sipariş tutarı payment_amount alanıdır. */
            amountMinor: (int) $request->input('payment_amount', $totalAmount),
            currency: $currency === 'TL' ? 'TRY' : strtoupper($currency),
            rawPayload: (string) json_encode($request->except('hash')),
            reason: $request->input('failed_reason_msg'),
        );
    }

    public function refund(PointPurchase $purchase, int $amountMinor): string
    {
        $oid = self::merchantOid($purchase->uuid);
        $amount = PointConverter::formatCents($amountMinor);

        $response = Http::asForm()->timeout(20)->post(self::BASE_URL.'/odeme/iade', [
            'merchant_id' => $this->merchantId,
            'merchant_oid' => $oid,
            'return_amount' => $amount,
            'paytr_token' => $this->sign($this->merchantId.$oid.$amount.$this->merchantSalt),
        ]);

        if ($response->json('status') !== 'success') {
            throw new MarketplaceException(__('Ödeme sağlayıcısı iadeyi kabul etmedi: :message', ['message' => (string) $response->json('err_msg', $response->status())]));
        }

        return 'paytr_rf_'.$oid;
    }

    public function webhookResponse(Request $request, ?PaymentWebhookEvent $record, bool $accepted): Response
    {
        return $accepted
            ? response('OK', 200, ['Content-Type' => 'text/plain'])
            : response('PAYTR notification failed', 400, ['Content-Type' => 'text/plain']);
    }

    public static function merchantOid(string $uuid): string
    {
        return str_replace('-', '', $uuid);
    }

    public static function uuidFromMerchantOid(string $oid): string
    {
        if (! preg_match('/^[0-9a-f]{32}$/i', $oid)) {
            return $oid;
        }

        return implode('-', [substr($oid, 0, 8), substr($oid, 8, 4), substr($oid, 12, 4), substr($oid, 16, 4), substr($oid, 20)]);
    }

    private function sign(string $value): string
    {
        return base64_encode(hash_hmac('sha256', $value, (string) $this->merchantKey, true));
    }
}
