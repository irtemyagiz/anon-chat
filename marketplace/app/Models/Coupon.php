<?php

namespace App\Models;

use App\Services\Points\PointConverter;
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

    public const Percent = 'percent';

    public const Fixed = 'fixed';

    public static function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Kuponun şu anda kullanılabilir olup olmadığı (etkin, tarih aralığında, limit dolmamış).
     */
    public function isUsable(): bool
    {
        $now = now();

        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte($now))
            && ($this->ends_at === null || $this->ends_at->gt($now))
            && ($this->max_redemptions === null || $this->redemptions_count < $this->max_redemptions);
    }

    /**
     * Arayüzde gösterilecek indirim metni: "%15" veya "$2.00".
     */
    public function discountLabel(): string
    {
        return $this->discount_type === self::Percent
            ? '%'.rtrim(rtrim(number_format($this->discount_value / 100, 2, '.', ''), '0'), '.')
            : '$'.PointConverter::formatCents($this->discount_value);
    }
}
