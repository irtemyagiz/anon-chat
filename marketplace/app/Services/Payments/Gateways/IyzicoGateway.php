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
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * iyzico Ödeme Formu (Checkout Form, Türkiye).
 *
 * - Ödeme: POST /payment/iyzipos/checkoutform/initialize/auth/ecom → kullanıcı
 *   iyzico'nun ödeme sayfasına (paymentPageUrl) yönlendirilir.
 * - Sonuç: iyzico, kullanıcının tarayıcısıyla callbackUrl'e (/webhooks/payments/iyzico)
 *   "token" gönderir; mağaza bildirimi (webhook) açıksa aynı token sunucuya da gelir.
 *   İsteğin içeriğine güvenilmez: token ile POST /payment/iyzipos/checkoutform/auth/ecom/detail
 *   çağrılır ve ödeme durumu, tutarı ve sipariş kimliği iyzico'dan kimlik doğrulamalı
 *   olarak alınır. Böylece sahte bildirim puan yükletemez.
 * - Kimlik doğrulama: IYZWSv2 (HMAC-SHA256(randomKey + uri + gövde, secretKey)).
 * - İade: POST /payment/refund (paymentTransactionId).
 */
class IyzicoGateway implements PaymentGateway, RespondsToWebhook
{
    private ?string $lastPurchaseUuid = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?string $secretKey,
        private readonly string $baseUrl = 'https://sandbox-api.iyzipay.com',
    ) {
        if (blank($this->apiKey) || blank($this->secretKey)) {
            throw new LogicException('IYZICO_API_KEY ve IYZICO_SECRET_KEY tanımlı olmalıdır.');
        }
    }

    public function code(): string
    {
        return 'iyzico';
    }

    public function createCheckout(PointPurchase $purchase): CheckoutSession
    {
        $user = $purchase->user;
        $price = PointConverter::formatCents((int) $purchase->charge_amount_minor);
        $defaults = config('marketplace.payments.buyer_defaults');
        $country = config("countries.{$user->country_code}.1", 'Turkey');
        $address = [
            'contactName' => trim("{$user->first_name} {$user->last_name}"),
            'city' => $defaults['city'],
            'country' => $country,
            'address' => $defaults['address'],
        ];

        $response = $this->request('/payment/iyzipos/checkoutform/initialize/auth/ecom', [
            'locale' => $user->locale === 'en' ? 'en' : 'tr',
            'conversationId' => $purchase->uuid,
            'price' => $price,
            'paidPrice' => $price,
            'currency' => $purchase->charge_currency === 'TRY' ? 'TRY' : $purchase->charge_currency,
            'basketId' => $purchase->uuid,
            'paymentGroup' => 'PRODUCT',
            'callbackUrl' => route('webhooks.payments', 'iyzico'),
            'enabledInstallments' => [1],
            'buyer' => [
                'id' => (string) $user->uuid,
                'name' => $user->first_name,
                'surname' => $user->last_name,
                'email' => $user->email,
                'identityNumber' => $defaults['identity_number'],
                'registrationAddress' => $defaults['address'],
                'city' => $defaults['city'],
                'country' => $country,
                'ip' => $purchase->ip_address ?? request()->ip(),
            ],
            'billingAddress' => $address,
            'basketItems' => [[
                'id' => 'points-'.$purchase->points,
                'name' => "{$purchase->points} POINT",
                'category1' => 'Dijital kredi',
                'itemType' => 'VIRTUAL',
                'price' => $price,
            ]],
        ]);

        if ($response->json('status') !== 'success' || blank($response->json('paymentPageUrl'))) {
            Log::error('iyzico ödeme formu açılamadı.', ['purchase' => $purchase->uuid, 'error' => $response->json('errorMessage'), 'code' => $response->json('errorCode')]);

            throw new MarketplaceException(__('Ödeme sayfası açılamadı. Lütfen biraz sonra tekrar deneyin.'));
        }

        return new CheckoutSession((string) $response->json('paymentPageUrl'), (string) $response->json('token'));
    }

    public function parseWebhook(Request $request): GatewayEvent
    {
        $token = (string) $request->input('token');

        if ($token === '' || strlen($token) > 200) {
            throw new InvalidWebhookSignatureException('iyzico bildiriminde token yok.');
        }

        $response = $this->request('/payment/iyzipos/checkoutform/auth/ecom/detail', ['locale' => 'tr', 'token' => $token]);
        $data = $response->json();

        if (! is_array($data) || ($data['status'] ?? null) !== 'success' || blank($data['basketId'] ?? null)) {
            throw new InvalidWebhookSignatureException('iyzico ödeme sonucu doğrulanamadı.');
        }

        $this->lastPurchaseUuid = (string) $data['basketId'];
        $succeeded = ($data['paymentStatus'] ?? null) === 'SUCCESS';
        $fraudStatus = (int) ($data['fraudStatus'] ?? 1);
        $paymentId = (string) ($data['paymentId'] ?? $token);

        $type = match (true) {
            $succeeded && $fraudStatus === 1 => GatewayEvent::PaymentSucceeded,
            $succeeded && $fraudStatus === 0 => 'payment.pending',
            default => GatewayEvent::PaymentFailed,
        };

        return new GatewayEvent(
            eventId: "iyzico:{$paymentId}:".($data['paymentStatus'] ?? 'UNKNOWN').":{$fraudStatus}",
            type: $type,
            purchaseUuid: $this->lastPurchaseUuid,
            providerTransactionId: (string) ($data['itemTransactions'][0]['paymentTransactionId'] ?? $paymentId),
            /** iyzico tutarları ondalık sayı olarak döndürür (ör. 105.5); iki basamakta kuruşa çevrilir. */
            amountMinor: (int) round((float) ($data['paidPrice'] ?? 0) * 100),
            currency: strtoupper((string) ($data['currency'] ?? '')),
            rawPayload: $response->body(),
            reason: $data['errorMessage'] ?? null,
        );
    }

    public function refund(PointPurchase $purchase, int $amountMinor): string
    {
        $transactionId = $purchase->transactions()->where('type', 'charge')->value('provider_transaction_id');

        $response = $this->request('/payment/refund', [
            'locale' => 'tr',
            'conversationId' => $purchase->uuid,
            'paymentTransactionId' => $transactionId,
            'price' => PointConverter::formatCents($amountMinor),
            'currency' => $purchase->charge_currency,
            'ip' => request()->ip(),
        ]);

        if ($response->json('status') !== 'success') {
            throw new MarketplaceException(__('Ödeme sağlayıcısı iadeyi kabul etmedi: :message', ['message' => (string) $response->json('errorMessage', $response->status())]));
        }

        return 'iyzico_rf_'.$response->json('paymentTransactionId', $transactionId);
    }

    /**
     * Tarayıcıdan gelen geri dönüşte kullanıcı sonuç sayfasına yönlendirilir;
     * sunucudan sunucuya gelen bildirime JSON yanıt verilir.
     */
    public function webhookResponse(Request $request, ?PaymentWebhookEvent $record, bool $accepted): Response
    {
        if ($request->isJson() || $request->expectsJson()) {
            return response()->json(['status' => $accepted ? 'ok' : 'rejected'], $accepted ? 200 : 400);
        }

        $purchase = $this->lastPurchaseUuid ? PointPurchase::query()->where('uuid', $this->lastPurchaseUuid)->first() : null;

        return $purchase !== null
            ? redirect()->route('top-up.result', $purchase)
            : redirect()->route('wallet')->withErrors(['payment' => __('Ödeme sonucu doğrulanamadı. Hesabınızdan para çekildiyse destek ile iletişime geçin.')]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function request(string $path, array $body): HttpResponse
    {
        $json = (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $randomKey = now()->getTimestampMs().Str::random(8);
        $signature = hash_hmac('sha256', $randomKey.$path.$json, (string) $this->secretKey);
        $authorization = base64_encode("apiKey:{$this->apiKey}&randomKey:{$randomKey}&signature:{$signature}");

        return Http::timeout(20)
            ->withHeaders(['Authorization' => "IYZWSv2 {$authorization}", 'x-iyzi-rnd' => $randomKey, 'Accept' => 'application/json'])
            ->withBody($json, 'application/json')
            ->post(rtrim($this->baseUrl, '/').$path);
    }
}
