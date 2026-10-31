<?php

namespace App\Services\Payments;

use App\Enums\PointPurchaseStatus;
use App\Enums\SystemWallet;
use App\Enums\WalletTransactionType;
use App\Enums\WebhookEventStatus;
use App\Exceptions\MarketplaceException;
use App\Models\PaymentDispute;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Models\PointPackage;
use App\Models\PointPurchase;
use App\Models\User;
use App\Notifications\PointsToppedUpNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Currency\CurrencyResolver;
use App\Services\Currency\ExchangeRateService;
use App\Services\Notifications\Notifier;
use App\Services\Points\PointConverter;
use App\Services\Settings\SettingsService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Puan yükleme akışı:
 *  1. createPurchase: paket + para birimi + kur sabitlenerek bekleyen sipariş oluşturulur.
 *  2. Kullanıcı sağlayıcıya yönlendirilir.
 *  3. handleWebhook: imza doğrulanır, olay bir kez işlenir, tutar/para birimi
 *     doğrulanır ve ancak bundan sonra puan cüzdana yazılır.
 *
 * Puan miktarı yalnızca USD referansına bağlıdır; kur değişimi yüklenen puanı etkilemez.
 */
class PointTopUpService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ExchangeRateService $rates,
        private readonly CurrencyResolver $currencies,
        private readonly PaymentGatewayManager $gateways,
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    public function minimumTopUpPoints(): int
    {
        return $this->settings->integer('points.minimum_top_up');
    }

    /**
     * @param  PointPackage|int  $selection  Paket veya (paket dışı) puan miktarı
     */
    public function createPurchase(User $user, PointPackage|int $selection, string $providerCode, ?string $ipAddress = null): PointPurchase
    {
        if ($selection instanceof PointPackage) {
            if (! $selection->is_active) {
                throw new MarketplaceException(__('Seçilen puan paketi aktif değil.'));
            }

            $points = $selection->totalPoints();
            $usdCents = $selection->price_usd_cents;
        } else {
            $points = $selection;
            $usdCents = PointConverter::pointsToUsdCents($points);
        }

        if ($points < $this->minimumTopUpPoints()) {
            throw new MarketplaceException(__('Minimum puan yükleme miktarı :points puandır.', ['points' => $this->minimumTopUpPoints()]));
        }

        $currency = $this->currencies->paymentCurrency($user);
        $provider = $this->gateways->enabledProvider($providerCode);

        if (! $provider->supportsCurrency($currency)) {
            throw new MarketplaceException(__('Seçilen ödeme yöntemi :currency para birimini desteklemiyor.', ['currency' => $currency]));
        }

        $rate = $currency === ExchangeRateService::BASE ? null : $this->rates->currentForPayment($currency);

        $purchase = PointPurchase::forceCreate([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'point_package_id' => $selection instanceof PointPackage ? $selection->id : null,
            'points' => $points,
            'usd_cents' => $usdCents,
            'charge_currency' => $currency,
            'charge_amount_minor' => $this->rates->convertFromUsdCents($usdCents, $currency, $rate),
            'exchange_rate_micros' => $rate?->rate_micros,
            'exchange_rate_id' => $rate?->id,
            'provider' => $provider->code,
            'status' => PointPurchaseStatus::Pending,
            'ip_address' => $ipAddress,
        ]);

        return $purchase;
    }

    public function startCheckout(PointPurchase $purchase): CheckoutSession
    {
        $session = $this->gateways->gateway($purchase->provider)->createCheckout($purchase);

        $purchase->forceFill(['provider_reference' => $session->providerReference])->save();

        return $session;
    }

    /**
     * Webhook'u doğrular ve işler. Aynı olay (provider + event id) yalnızca bir kez işlenir.
     *
     * @throws InvalidWebhookSignatureException
     */
    public function handleWebhook(string $providerCode, Request $request): PaymentWebhookEvent
    {
        $gateway = $this->gateways->gateway($providerCode);

        try {
            $event = $gateway->parseWebhook($request);
        } catch (InvalidWebhookSignatureException $exception) {
            PaymentWebhookEvent::forceCreate([
                'provider' => $providerCode,
                'event_id' => 'invalid_'.Str::uuid(),
                'signature_valid' => false,
                'status' => WebhookEventStatus::Failed,
                'payload' => $request->getContent(),
                'processing_error' => $exception->getMessage(),
                'ip_address' => $request->ip(),
            ]);

            $this->audit->record('payment.webhook_signature_invalid', null, null, null, null, [
                'provider' => $providerCode,
                'ip' => $request->ip(),
            ]);
            $this->notifier->staff('payments.manage', __('Şüpheli ödeme bildirimi'), __(':provider için imzası geçersiz bir webhook alındı (IP: :ip).', ['provider' => $providerCode, 'ip' => $request->ip()]), route('admin.payments.webhooks'));

            throw $exception;
        }

        $record = PaymentWebhookEvent::unguarded(fn (): PaymentWebhookEvent => PaymentWebhookEvent::query()->createOrFirst(
            ['provider' => $providerCode, 'event_id' => $event->eventId],
            [
                'event_type' => $event->type,
                'signature_valid' => true,
                'status' => WebhookEventStatus::Received,
                'payload' => $event->rawPayload,
                'ip_address' => $request->ip(),
            ],
        ));

        if (! $record->wasRecentlyCreated && $record->status !== WebhookEventStatus::Failed) {
            return $record;
        }

        try {
            $status = $this->dispatch($providerCode, $event);
            $record->forceFill([
                'status' => $status,
                'processed_at' => now(),
                'processing_error' => $status === WebhookEventStatus::Failed ? 'Ödeme tutarı veya para birimi siparişle eşleşmiyor.' : null,
            ])->save();
        } catch (Throwable $exception) {
            $record->forceFill(['status' => WebhookEventStatus::Failed, 'processing_error' => $exception->getMessage()])->save();
            Log::error('Ödeme webhook işlenemedi.', ['provider' => $providerCode, 'event' => $event->eventId, 'error' => $exception->getMessage()]);

            throw $exception;
        }

        return $record;
    }

    /**
     * Doğrulanmış başarılı ödemeyi işler ve puanları cüzdana yazar.
     */
    public function markPaid(PointPurchase $purchase, GatewayEvent $event): WebhookEventStatus
    {
        return DB::transaction(function () use ($purchase, $event): WebhookEventStatus {
            /** @var PointPurchase $purchase */
            $purchase = PointPurchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status === PointPurchaseStatus::Paid) {
                return WebhookEventStatus::Ignored;
            }

            if (! in_array($purchase->status, [PointPurchaseStatus::Pending, PointPurchaseStatus::Failed], true)) {
                return WebhookEventStatus::Ignored;
            }

            if ($event->amountMinor !== (int) $purchase->charge_amount_minor || $event->currency !== $purchase->charge_currency) {
                $this->audit->record('payment.amount_mismatch', $purchase, null, null, null, [
                    'expected' => [$purchase->charge_amount_minor, $purchase->charge_currency],
                    'received' => [$event->amountMinor, $event->currency],
                    'event' => $event->eventId,
                ]);

                $purchase->forceFill(['failure_reason' => 'amount_mismatch'])->save();
                $this->notifier->staff('payments.manage', __('Şüpheli ödeme'), __('Ödeme tutarı veya para birimi siparişle eşleşmedi; puan yüklenmedi. İşlem: :uuid', ['uuid' => $purchase->uuid]), route('admin.payments.index', ['q' => $purchase->uuid]));

                return WebhookEventStatus::Failed;
            }

            $this->recordProviderTransaction($purchase, $event, 'charge', 'succeeded');

            $transaction = $this->wallets->credit(
                $purchase->user,
                $purchase->points,
                WalletTransactionType::TopUp,
                'topup:'.$purchase->uuid,
                SystemWallet::TopUpClearing,
                $purchase,
                "{$purchase->points} puan yüklendi",
                metadata: ['charge_amount_minor' => $purchase->charge_amount_minor, 'currency' => $purchase->charge_currency],
            );

            $purchase->forceFill([
                'status' => PointPurchaseStatus::Paid,
                'paid_at' => now(),
                'wallet_transaction_id' => $transaction->id,
                'failure_reason' => null,
            ])->save();

            $this->notifier->send($purchase->user, new PointsToppedUpNotification($purchase->points));

            return WebhookEventStatus::Processed;
        });
    }

    /**
     * Kullanılmamış puanların iadesi. Kullanıcının yeterli harcanmamış puanı yoksa iade yapılamaz.
     */
    public function refund(PointPurchase $purchase, User $admin, string $reason): PaymentRefund
    {
        return DB::transaction(function () use ($purchase, $admin, $reason): PaymentRefund {
            /** @var PointPurchase $purchase */
            $purchase = PointPurchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status !== PointPurchaseStatus::Paid) {
                throw new MarketplaceException(__('Yalnızca tamamlanmış yüklemeler iade edilebilir.'));
            }

            $transaction = $this->wallets->debit(
                $purchase->user,
                $purchase->points,
                WalletTransactionType::TopUpRefund,
                'topup-refund:'.$purchase->uuid,
                SystemWallet::TopUpClearing,
                $purchase,
                'Puan yükleme iadesi',
                $admin,
            );

            $providerRefundId = $this->gateways->gateway($purchase->provider)->refund($purchase, (int) $purchase->charge_amount_minor);

            $refund = PaymentRefund::forceCreate([
                'uuid' => (string) Str::uuid(),
                'point_purchase_id' => $purchase->id,
                'provider_refund_id' => $providerRefundId,
                'amount_minor' => $purchase->charge_amount_minor,
                'currency' => $purchase->charge_currency,
                'points_reversed' => $purchase->points,
                'status' => 'succeeded',
                'reason' => $reason,
                'wallet_transaction_id' => $transaction->id,
                'processed_by' => $admin->id,
            ]);

            $purchase->forceFill(['status' => PointPurchaseStatus::Refunded, 'refunded_at' => now()])->save();

            $this->audit->record('payment.refunded', $purchase, ['status' => 'paid'], ['status' => 'refunded'], $admin, ['reason' => $reason]);

            return $refund;
        });
    }

    private function dispatch(string $providerCode, GatewayEvent $event): WebhookEventStatus
    {
        /** Bekleyen ödeme, iade bildirimi gibi işlem gerektirmeyen olaylar sipariş aranmadan kaydedilir. */
        if (! in_array($event->type, [GatewayEvent::PaymentSucceeded, GatewayEvent::PaymentFailed, GatewayEvent::DisputeCreated], true)) {
            return WebhookEventStatus::Ignored;
        }

        $purchase = PointPurchase::query()
            ->where('uuid', $event->purchaseUuid)
            ->where('provider', $providerCode)
            ->first();

        if ($purchase === null) {
            throw new MarketplaceException(__('Webhook için puan yükleme siparişi bulunamadı.'));
        }

        return match ($event->type) {
            GatewayEvent::PaymentSucceeded => $this->markPaid($purchase, $event),
            GatewayEvent::PaymentFailed => $this->markFailed($purchase, $event),
            GatewayEvent::DisputeCreated => $this->recordDispute($purchase, $event),
            default => WebhookEventStatus::Ignored,
        };
    }

    private function markFailed(PointPurchase $purchase, GatewayEvent $event): WebhookEventStatus
    {
        return DB::transaction(function () use ($purchase, $event): WebhookEventStatus {
            $purchase = PointPurchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status !== PointPurchaseStatus::Pending) {
                return WebhookEventStatus::Ignored;
            }

            $this->recordProviderTransaction($purchase, $event, 'charge', 'failed');
            $purchase->forceFill(['status' => PointPurchaseStatus::Failed, 'failure_reason' => $event->reason])->save();

            return WebhookEventStatus::Processed;
        });
    }

    /**
     * Ödeme itirazı (chargeback): yüklenen puanlar zorunlu olarak geri alınır;
     * kullanıcı puanları harcadıysa bakiye eksiye düşebilir.
     */
    private function recordDispute(PointPurchase $purchase, GatewayEvent $event): WebhookEventStatus
    {
        return DB::transaction(function () use ($purchase, $event): WebhookEventStatus {
            $purchase = PointPurchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status !== PointPurchaseStatus::Paid) {
                return WebhookEventStatus::Ignored;
            }

            $transaction = $this->wallets->debit(
                $purchase->user,
                $purchase->points,
                WalletTransactionType::Chargeback,
                'chargeback:'.$purchase->uuid,
                SystemWallet::TopUpClearing,
                $purchase,
                'Ödeme itirazı nedeniyle puan geri alındı',
            );

            PaymentDispute::forceCreate([
                'point_purchase_id' => $purchase->id,
                'provider_dispute_id' => $event->providerTransactionId,
                'amount_minor' => $event->amountMinor,
                'currency' => $event->currency,
                'reason' => $event->reason,
                'status' => 'open',
                'points_reversed' => $purchase->points,
                'wallet_transaction_id' => $transaction->id,
            ]);

            $purchase->forceFill(['status' => PointPurchaseStatus::Disputed])->save();

            $this->audit->record('payment.disputed', $purchase, null, null, null, ['event' => $event->eventId]);
            $this->notifier->staff('payments.manage', __('Ödeme itirazı (chargeback)'), __(':email kullanıcısının :points puanlık yüklemesine itiraz edildi; puanlar geri alındı.', ['email' => $purchase->user->email, 'points' => $purchase->points]), route('admin.payments.index', ['durum' => 'disputed']));

            return WebhookEventStatus::Processed;
        });
    }

    private function recordProviderTransaction(PointPurchase $purchase, GatewayEvent $event, string $type, string $status): void
    {
        PaymentTransaction::unguarded(fn (): PaymentTransaction => PaymentTransaction::query()->updateOrCreate(
            ['provider' => $purchase->provider, 'provider_transaction_id' => $event->providerTransactionId, 'type' => $type],
            [
                'point_purchase_id' => $purchase->id,
                'status' => $status,
                'amount_minor' => $event->amountMinor,
                'currency' => $event->currency,
                'raw_response' => json_decode($event->rawPayload, true) ?? ['raw' => mb_substr($event->rawPayload, 0, 5000)],
            ],
        ));
    }
}
