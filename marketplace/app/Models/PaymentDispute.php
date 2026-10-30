<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['*'])]
class PaymentDispute extends Model
{
    /**
     * @return BelongsTo<PointPurchase, $this>
     */
    public function pointPurchase(): BelongsTo
    {
        return $this->belongsTo(PointPurchase::class);
    }
}
