<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Creator parasal hakediş özeti (USD sent). Puan cüzdanından tamamen ayrıdır.
 * Yalnızca CreatorEarningService ve PayoutService tarafından, kilitli satır
 * üzerinde güncellenir.
 */
#[Guarded(['*'])]
class CreatorBalance extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Ödeme talebi oluşturulabilecek tutar (kullanılabilir - rezerve).
     */
    public function withdrawableCents(): int
    {
        return max(0, (int) $this->available_cents - (int) $this->reserved_cents);
    }
}
