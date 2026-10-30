<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['*'])]
class CreatorPayout extends Model
{
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CreatorPayoutRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(CreatorPayoutRequest::class, 'creator_payout_request_id');
    }
}
