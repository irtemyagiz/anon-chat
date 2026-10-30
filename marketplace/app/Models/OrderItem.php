<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Satış kalemi. Fiyat, komisyon oranı ve paylaşım tutarları satış anında
 * sabitlenir (snapshot); ürün fiyatı veya komisyon sonradan değişse de etkilenmez.
 */
#[Guarded(['*'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OrderItemStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return HasOne<CreatorEarning, $this>
     */
    public function earning(): HasOne
    {
        return $this->hasOne(CreatorEarning::class);
    }

    /**
     * @return HasOne<PurchaseEntitlement, $this>
     */
    public function entitlement(): HasOne
    {
        return $this->hasOne(PurchaseEntitlement::class);
    }
}
