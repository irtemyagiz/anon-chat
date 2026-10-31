<?php

namespace App\Services\Payments\Gateways;

use App\Exceptions\MarketplaceException;
use App\Models\PaymentTransaction;
use App\Models\PointPurchase;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\InvalidWebhookSignatureException;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Stripe Checkout (uluslararası, USD).
 *
 * - Ödeme: POST /v1/checkout/sessions → kullanıcı Stripe'ın ödeme sayfasına yönlendirilir.
 * - Webhook: "Stripe-Signature: t=…,v1=…" başlığı; imza HMAC-SHA256("{t}.{gövde}", whsec_…),
 *   5 dakikadan eski zaman damgaları reddedilir (tekrar oynatma koruması).
 * - İade: POST /v1/refunds (payment_intent).
 *
 * Stripe paneli → Developers → Webhooks: uç nokta /webhooks/payments/stripe, olaylar:
 * checkout.session.completed, checkout.session.async_payment_succeeded,
 * checkout.session.async_payment_failed, checkout.session.expired, charge.dispute.created.
 */
class StripeGateway implements PaymentGateway
{
    public const API = 'https://api.stripe.com/v1';

    public const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function __construct(private readonly ?string $secretKey, private readonly ?string $webhookSecret)
    {
        if (blank($this->secretKey) || blank($this->webhookSecret)) {
            throw new LogicException('STRIPE_SECRET_KEY ve STRIPE_WEBHOOK_SECRET tanımlı olmalıdır.');
        }
    }

    public function code(): string
    {
        return 'stripe';
    }

    public function createCheckout(PointPurchase $purchase): CheckoutSession
    {
        $response = Http::asForm()->withToken((string) $this->secretKey)->timeout(20)
            ->withHeaders(['Idempotency-Key' => 'checkout-'.$purchase->uuid])
            ->post(self::API.'/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $purchase->uuid,
                'customer_email' => $purchase->user->email,
                'locale' => $purchase->user->locale === 'en' ? 'en' : 'tr',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($purchase->charge_currency),
                        'unit_amount' => (int) $purchase->charge_amount_minor,
                        'product_data' => ['name' => "{$purchase->points} POINT"],
                    ],
                ]],
                'metadata' => ['purchase' => $purchase->uuid],
                'payment_intent_data' => ['metadata' => ['purchase' => $purchase->uuid]],
                'success_url' => route('top-up.result', $purchase),
                'cancel_url' => route('top-up.result', $purchase),
            ]);

        if (! $response->successful() || blank($response->json('url'))) {
            Log::error('Stripe ödeme oturumu açılamadı.', ['purchase' => $purchase->uuid, 'status' => $response->status(), 'error' => $response->json('error.message')]);

            throw new MarketplaceException(__('Ödeme sayfası açılamadı. Lütfen biraz sonra tekrar deneyin.'));
        }

        return new CheckoutSession($response->json('url'), $response->json('id'));
    }

    public function parseWebhook(Request $request): GatewayEvent
    {
        $payload = $request->getContent();
        $this->verifySignature($payload, (string) $request->header('Stripe-Signature'));

        $event = json_decode($payload, true);

        if (! is_array($event) || ! isset($event['id'], $event['type'], $event['data']['object'])) {
            throw new InvalidWebhookSignatureException('Stripe olayı çözümlenemedi.');
        }

        $object = $event['data']['object'];

        $type = match ($event['type']) {
            'checkout.session.completed' => ($object['payment_status'] ?? null) === 'paid' ? GatewayEvent::PaymentSucceeded : 'payment.pending',
            'checkout.session.async_payment_succeeded' => GatewayEvent::PaymentSucceeded,
            'checkout.session.async_payment_failed', 'checkout.session.expired' => GatewayEvent::PaymentFailed,
            'charge.dispute.created' => GatewayEvent::DisputeCreated,
            default => 'ignored.'.$event['type'],
        };

        $paymentIntent = is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : null;

        return new GatewayEvent(
            eventId: (string) $event['id'],
            type: $type,
            purchaseUuid: (string) ($object['client_reference_id'] ?? $object['metadata']['purchase'] ?? $this->purchaseForPaymentIntent($paymentIntent) ?? ''),
            providerTransactionId: (string) ($paymentIntent ?? $object['id'] ?? ''),
            amountMinor: (int) ($object['amount_total'] ?? $object['amount'] ?? 0),
            currency: strtoupper((string) ($object['currency'] ?? '')),
            rawPayload: $payload,
            reason: $object['reason'] ?? null,
        );
    }

    public function refund(PointPurchase $purchase, int $amountMinor): string
    {
        $paymentIntent = $purchase->transactions()->where('type', 'charge')->value('provider_transaction_id');

        $response = Http::asForm()->withToken((string) $this->secretKey)->timeout(20)
            ->withHeaders(['Idempotency-Key' => 'refund-'.$purchase->uuid])
            ->post(self::API.'/refunds', ['payment_intent' => $paymentIntent, 'amount' => $amountMinor, 'metadata' => ['purchase' => $purchase->uuid]]);

        if (! $response->successful() || blank($response->json('id'))) {
            throw new MarketplaceException(__('Ödeme sağlayıcısı iadeyi kabul etmedi: :message', ['message' => (string) $response->json('error.message', $response->status())]));
        }

        return (string) $response->json('id');
    }

    private function verifySignature(string $payload, string $header): void
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't') {
                $timestamp = ctype_digit($value) ? (int) $value : null;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === [] || abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            throw new InvalidWebhookSignatureException('Stripe imzası eksik veya süresi geçmiş.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", (string) $this->webhookSecret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new InvalidWebhookSignatureException('Stripe imzası geçersiz.');
    }

    /**
     * İtiraz (dispute) olaylarında sipariş kimliği yoktur; ödeme kaydından bulunur.
     */
    private function purchaseForPaymentIntent(?string $paymentIntent): ?string
    {
        if ($paymentIntent === null) {
            return null;
        }

        return PaymentTransaction::query()->where('payment_transactions.provider', 'stripe')->where('payment_transactions.provider_transaction_id', $paymentIntent)
            ->join('point_purchases', 'point_purchases.id', '=', 'payment_transactions.point_purchase_id')
            ->value('point_purchases.uuid');
    }
}
