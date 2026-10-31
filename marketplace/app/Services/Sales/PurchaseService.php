<?php

namespace App\Services\Sales;

use App\Enums\EntitlementSource;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\SystemWallet;
use App\Enums\WalletTransactionType;
use App\Exceptions\AlreadyOwnedException;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\MarketplaceException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductLicense;
use App\Models\PurchaseEntitlement;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\NewSaleNotification;
use App\Notifications\PurchaseCompletedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Earnings\CreatorEarningService;
use App\Services\Notifications\Notifier;
use App\Services\Points\PointConverter;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Puanla dijital ürün satın alma.
 *
 * Tüm adımlar tek bir veritabanı transaction'ında ve alıcının cüzdan satırı
 * kilitliyken yapılır: sahiplik kontrolü, puan düşümü, sipariş, indirme hakkı,
 * komisyon ve creator hakedişi. Böylece eşzamanlı isteklerde çift harcama ve
 * çift satın alma oluşamaz.
 */
class PurchaseService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly CommissionResolver $commissions,
        private readonly CreatorEarningService $earnings,
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Kuponlar uygulandıktan sonra ödenecek puan.
     */
    public function quote(Product $product, ProductLicense $license, ?Coupon $coupon = null): int
    {
        $listPoints = $license->pricePoints();

        return $listPoints - $this->discountPoints($listPoints, $product, $coupon);
    }

    public function purchase(
        User $buyer,
        Product $product,
        ?ProductLicense $license,
        string $idempotencyKey,
        ?string $couponCode = null,
        ?string $ipAddress = null,
    ): Order {
        $license = $this->resolveLicense($product, $license);
        $this->assertPurchasable($buyer, $product);

        if ($license->price_cents === 0) {
            throw new MarketplaceException(__('Ücretsiz ürünler satın alınmaz, doğrudan indirilir.'));
        }

        $existing = Order::query()->where('idempotency_key', $this->orderKey($buyer, $idempotencyKey))->first();

        if ($existing !== null) {
            return $existing;
        }

        $wallet = $this->wallets->walletFor($buyer);

        return DB::transaction(function () use ($buyer, $product, $license, $idempotencyKey, $couponCode, $ipAddress, $wallet): Order {
            /** Alıcının cüzdanını kilitle: aynı kullanıcının tüm satın almaları sıraya girer. */
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            $existing = Order::query()->where('idempotency_key', $this->orderKey($buyer, $idempotencyKey))->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($buyer->ownsProduct($product, $license->license_id)) {
                throw new AlreadyOwnedException;
            }

            $coupon = $this->lockCoupon($product, $couponCode);
            $listPoints = $license->pricePoints();
            $discountPoints = $this->discountPoints($listPoints, $product, $coupon);
            $pricePoints = $listPoints - $discountPoints;

            if ((int) $wallet->balance < $pricePoints) {
                throw new InsufficientPointsException($pricePoints, (int) $wallet->balance);
            }

            $rateBps = $this->commissions->rateFor($product);
            $split = CommissionResolver::split(PointConverter::pointsToUsdCents($pricePoints), $rateBps);

            $order = Order::forceCreate([
                'uuid' => (string) Str::uuid(),
                'number' => $this->orderNumber(),
                'user_id' => $buyer->id,
                'total_points' => $pricePoints,
                'status' => OrderStatus::Completed,
                'idempotency_key' => $this->orderKey($buyer, $idempotencyKey),
                'ip_address' => $ipAddress,
            ]);

            $item = OrderItem::forceCreate([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'license_id' => $license->license_id,
                'creator_id' => $product->creator_id,
                'coupon_id' => $coupon?->id,
                'product_title' => $product->title,
                'list_price_points' => $listPoints,
                'discount_points' => $discountPoints,
                'price_points' => $pricePoints,
                'commission_bps' => $rateBps,
                'creator_amount_cents' => $split['creator'],
                'platform_amount_cents' => $split['platform'],
                'status' => OrderItemStatus::Completed,
            ]);

            if ($pricePoints > 0) {
                $transaction = $this->wallets->debit(
                    $buyer,
                    $pricePoints,
                    WalletTransactionType::Purchase,
                    'order:'.$order->uuid,
                    SystemWallet::SalesRevenue,
                    $order,
                    "{$product->title} satın alındı",
                    metadata: ['order' => $order->number],
                );

                $order->forceFill(['wallet_transaction_id' => $transaction->id])->save();
            }

            $this->grantEntitlement($buyer, $product, $license->license_id, EntitlementSource::Purchase, $item);
            $this->earnings->recordSale($item);

            $coupon?->increment('redemptions_count');
            $product->increment('sales_count');

            $this->notifier->send($buyer, new PurchaseCompletedNotification($product->title, $product->slug));
            $this->notifier->send($product->creator, new NewSaleNotification($product->title, $split['creator']));

            return $order;
        });
    }

    /**
     * Ücretsiz ürün için indirme hakkı tanımlar (puan hareketi oluşmaz). İdempotenttir.
     */
    public function claimFree(User $user, Product $product): PurchaseEntitlement
    {
        $this->assertPurchasable($user, $product, allowOwnProduct: true);
        $license = $this->resolveLicense($product, null);

        if ($license->price_cents !== 0) {
            throw new MarketplaceException(__('Bu ürün ücretsiz değil.'));
        }

        return DB::transaction(fn (): PurchaseEntitlement => $this->grantEntitlement($user, $product, $license->license_id, EntitlementSource::Free));
    }

    /**
     * Satış kalemini iade eder: puanlar geri yüklenir, indirme hakkı kaldırılır,
     * creator kazancı geri alınır.
     */
    public function refundItem(OrderItem $item, User $admin, string $reason): OrderItem
    {
        return DB::transaction(function () use ($item, $admin, $reason): OrderItem {
            /** @var OrderItem $item */
            $item = OrderItem::query()->lockForUpdate()->findOrFail($item->id);

            if ($item->status === OrderItemStatus::Refunded) {
                throw new MarketplaceException(__('Bu satış zaten iade edilmiş.'));
            }

            $order = $item->order;

            if ($item->price_points > 0) {
                $this->wallets->credit(
                    $order->user,
                    $item->price_points,
                    WalletTransactionType::PurchaseRefund,
                    'order-item-refund:'.$item->id,
                    SystemWallet::SalesRevenue,
                    $order,
                    "{$item->product_title} iadesi",
                    $admin,
                    ['reason' => $reason],
                );
            }

            PurchaseEntitlement::query()
                ->where('order_item_id', $item->id)
                ->update(['revoked_at' => now(), 'revoke_reason' => 'refund']);

            $this->earnings->reverse($item);

            $item->forceFill(['status' => OrderItemStatus::Refunded])->save();

            $allRefunded = $order->items()->where('status', '!=', OrderItemStatus::Refunded)->doesntExist();
            $order->forceFill([
                'status' => $allRefunded ? OrderStatus::Refunded : OrderStatus::PartiallyRefunded,
                'refunded_at' => $allRefunded ? now() : $order->refunded_at,
            ])->save();

            $this->audit->record('order.item_refunded', $item, ['status' => 'completed'], ['status' => 'refunded'], $admin, [
                'reason' => $reason,
                'points' => $item->price_points,
            ]);

            return $item;
        });
    }

    private function grantEntitlement(User $user, Product $product, int $licenseId, EntitlementSource $source, ?OrderItem $item = null): PurchaseEntitlement
    {
        $entitlement = PurchaseEntitlement::query()
            ->where(['user_id' => $user->id, 'product_id' => $product->id, 'license_id' => $licenseId])
            ->lockForUpdate()
            ->first();

        if ($entitlement !== null) {
            if ($entitlement->revoked_at !== null) {
                $entitlement->forceFill([
                    'order_item_id' => $item?->id,
                    'source' => $source,
                    'granted_at' => now(),
                    'revoked_at' => null,
                    'revoke_reason' => null,
                ])->save();
            }

            return $entitlement;
        }

        return PurchaseEntitlement::forceCreate([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'license_id' => $licenseId,
            'order_item_id' => $item?->id,
            'source' => $source,
            'granted_at' => now(),
        ]);
    }

    private function assertPurchasable(User $buyer, Product $product, bool $allowOwnProduct = false): void
    {
        if (! $product->isPublished()) {
            throw new MarketplaceException(__('Bu ürün şu anda satışta değil.'));
        }

        if (! $buyer->isActive()) {
            throw new MarketplaceException(__('Hesabınız aktif olmadığı için işlem yapılamıyor.'));
        }

        if (! $allowOwnProduct && $buyer->id === $product->creator_id) {
            throw new MarketplaceException(__('Kendi ürününüzü satın alamazsınız.'));
        }
    }

    private function resolveLicense(Product $product, ?ProductLicense $license): ProductLicense
    {
        $license ??= $product->licenses()->where('is_active', true)->orderByDesc('is_default')->first();

        if ($license === null || $license->product_id !== $product->id || ! $license->is_active) {
            throw new MarketplaceException(__('Geçerli bir lisans seçilmedi.'));
        }

        return $license;
    }

    /**
     * Satın alma öncesi kupon önizlemesi: kupon geçerliyse indirimli tutarı döndürür.
     * Kupon kullanım sayısı değişmez; asıl kontrol satın alma anında kilitle tekrar yapılır.
     *
     * @return array{list_points: int, discount_points: int, total_points: int}
     *
     * @throws MarketplaceException
     */
    public function previewCoupon(Product $product, ?ProductLicense $license, string $code): array
    {
        $license = $this->resolveLicense($product, $license);
        $coupon = $this->validCoupon($product, $code, lock: false);
        $listPoints = $license->pricePoints();
        $discountPoints = $this->discountPoints($listPoints, $product, $coupon);

        return ['list_points' => $listPoints, 'discount_points' => $discountPoints, 'total_points' => $listPoints - $discountPoints];
    }

    private function lockCoupon(Product $product, ?string $code): ?Coupon
    {
        if (blank($code)) {
            return null;
        }

        return $this->validCoupon($product, $code, lock: true);
    }

    /**
     * @throws MarketplaceException
     */
    private function validCoupon(Product $product, string $code, bool $lock): Coupon
    {
        $coupon = Coupon::query()
            ->where('creator_id', $product->creator_id)
            ->where('code', Coupon::normalizeCode($code))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        $valid = $coupon !== null
            && ($coupon->product_id === null || $coupon->product_id === $product->id)
            && $coupon->isUsable();

        if (! $valid) {
            throw new MarketplaceException(__('Kupon kodu geçersiz veya süresi dolmuş.'));
        }

        return $coupon;
    }

    private function discountPoints(int $listPoints, Product $product, ?Coupon $coupon): int
    {
        if ($coupon === null) {
            return 0;
        }

        $discount = $coupon->discount_type === Coupon::Percent
            ? intdiv($listPoints * min(10_000, $coupon->discount_value), 10_000)
            : PointConverter::usdCentsToPoints($coupon->discount_value);

        return min($listPoints, $discount);
    }

    private function orderKey(User $buyer, string $idempotencyKey): string
    {
        return $buyer->id.':'.$idempotencyKey;
    }

    private function orderNumber(): string
    {
        return 'ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
    }
}
