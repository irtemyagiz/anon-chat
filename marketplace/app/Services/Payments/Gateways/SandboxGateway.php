<?php

namespace App\Services\Payments\Gateways;

use App\Models\PointPurchase;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\InvalidWebhookSignatureException;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;

/**
 * Yerel geliştirme ve otomatik testler için sahte para kullanmayan sandbox
 * sağlayıcısı. Gerçek sağlayıcılarla aynı akışı (yönlendirme + imzalı webhook)
 * izler. Production ortamında kesinlikle çalışmaz.
 *
 * İmza: HMAC-SHA256(ham gövde, SANDBOX_PAYMENT_SECRET), "X-Sandbox-Signature" başlığında.
 */
class SandboxGateway implements PaymentGateway
{
    public const SIGNATURE_HEADER = 'X-Sandbox-Signature';

    public function __construct(private readonly ?string $secret)
    {
        if (app()->isProduction()) {
            throw new LogicException('Sandbox ödeme sağlayıcısı production ortamında kullanılamaz.');
        }

        if (blank($this->secret)) {
            throw new LogicException('SANDBOX_PAYMENT_SECRET tanımlı değil.');
        }
    }

    public function code(): string
    {
        return 'sandbox';
    }

    public function createCheckout(PointPurchase $purchase): CheckoutSession
    {
        return new CheckoutSession(
            redirectUrl: route('sandbox.checkout', $purchase),
            providerReference: 'sbx_'.Str::lower(Str::random(24)),
        );
    }

    public function parseWebhook(Request $request): GatewayEvent
    {
        $payload = $request->getContent();
        $signature = (string) $request->header(self::SIGNATURE_HEADER);

        if (! hash_equals($this->sign($payload), $signature)) {
            throw new InvalidWebhookSignatureException('Sandbox webhook imzası geçersiz.');
        }

        $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

        return new GatewayEvent(
            eventId: (string) $data['id'],
            type: (string) $data['type'],
            purchaseUuid: (string) $data['purchase'],
            providerTransactionId: (string) $data['transaction'],
            amountMinor: (int) $data['amount'],
            currency: (string) $data['currency'],
            rawPayload: $payload,
            reason: $data['reason'] ?? null,
        );
    }

    public function refund(PointPurchase $purchase, int $amountMinor): string
    {
        return 'sbx_rf_'.Str::lower(Str::random(20));
    }

    public function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) $this->secret);
    }
}
