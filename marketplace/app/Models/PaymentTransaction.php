<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Guarded(['*'])]
class PaymentTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentTransaction $transaction): void {
            $transaction->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<PointPurchase, $this>
     */
    public function pointPurchase(): BelongsTo
    {
        return $this->belongsTo(PointPurchase::class);
    }
}
