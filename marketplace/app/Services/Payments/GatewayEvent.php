<?php

namespace App\Services\Payments;

/**
 * Sağlayıcıdan bağımsız, doğrulanmış webhook olayı.
 */
final readonly class GatewayEvent
{
    public const PaymentSucceeded = 'payment.succeeded';

    public const PaymentFailed = 'payment.failed';

    public const RefundSucceeded = 'refund.succeeded';

    public const DisputeCreated = 'dispute.created';

    public function __construct(
        public string $eventId,
        public string $type,
        public string $purchaseUuid,
        public string $providerTransactionId,
        public int $amountMinor,
        public string $currency,
        public string $rawPayload,
        public ?string $reason = null,
    ) {}
}
