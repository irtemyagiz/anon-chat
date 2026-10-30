<?php

namespace App\Services\Payments;

use App\Models\PointPurchase;
use Illuminate\Http\Request;

/**
 * Ödeme sağlayıcı adaptörü. iyzico, PayTR, Stripe gibi her sağlayıcı bu
 * arayüzü uygular; uygulama kodu hiçbir sağlayıcıya doğrudan bağımlı değildir.
 */
interface PaymentGateway
{
    public function code(): string;

    /**
     * Sağlayıcıda ödeme oturumu başlatır ve kullanıcının yönlendirileceği adresi döndürür.
     */
    public function createCheckout(PointPurchase $purchase): CheckoutSession;

    /**
     * Webhook isteğinin imzasını doğrular ve normalize edilmiş olaya çevirir.
     *
     * @throws InvalidWebhookSignatureException
     */
    public function parseWebhook(Request $request): GatewayEvent;

    /**
     * Sağlayıcıda iade başlatır ve sağlayıcının iade referansını döndürür.
     */
    public function refund(PointPurchase $purchase, int $amountMinor): string;
}
