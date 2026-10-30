<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Creator kuponu. discount_type: "percent" (discount_value bps, 1000 = %10)
 * veya "fixed" (discount_value USD sent).
 */
#[Fillable([
    'product_id', 'code', 'discount_type', 'discount_value',
    'max_redemptions', 'starts_at', 'ends_at', 'is_active',
])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
