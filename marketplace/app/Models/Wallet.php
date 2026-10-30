<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Puan cüzdanı. "balance" yalnızca WalletService tarafından, defter kaydıyla
 * aynı veritabanı transaction'ı içinde güncellenir; doğrudan değiştirilmez.
 * user_id dolu ise kullanıcı cüzdanı, system_code dolu ise sistem hesabıdır.
 */
#[Fillable(['user_id', 'system_code'])]
class Wallet extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WalletLedgerEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class);
    }

    /**
     * Harcanabilir puan (bekleyen puanlar hariç).
     */
    public function availableBalance(): int
    {
        return max(0, (int) $this->balance);
    }
}
