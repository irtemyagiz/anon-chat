<?php

namespace App\Models;

use App\Enums\PayoutRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Guarded(['*'])]
#[Hidden(['payout_details'])]
class CreatorPayoutRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PayoutRequestStatus::class,
            'payout_details' => 'encrypted:array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return HasOne<CreatorPayout, $this>
     */
    public function payout(): HasOne
    {
        return $this->hasOne(CreatorPayout::class);
    }

    /**
     * Yönetici ekranı için maskelenmiş IBAN/hesap bilgisi.
     */
    public function maskedAccount(): string
    {
        $account = (string) ($this->payout_details['iban'] ?? $this->payout_details['account'] ?? '');

        return strlen($account) > 4 ? str_repeat('•', strlen($account) - 4).substr($account, -4) : $account;
    }
}
