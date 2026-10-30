<?php

namespace App\Models;

use App\Enums\PointPurchaseStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Puan yükleme siparişi. Ödemede kullanılan kur (exchange_rate_micros) ve
 * tahsil edilecek tutar oluşturma anında sabitlenir; sonradan değişmez.
 */
#[Guarded(['*'])]
class PointPurchase extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PointPurchaseStatus::class,
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PointPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(PointPackage::class, 'point_package_id');
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * @return BelongsTo<WalletTransaction, $this>
     */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }
}
