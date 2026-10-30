<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Tek bir iş olayı (yükleme, satın alma, iade...). Bacakları (ledger entries)
 * toplamı her zaman sıfırdır. Kayıtlar asla güncellenmez veya silinmez.
 */
#[Guarded(['*'])]
class WalletTransaction extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<WalletLedgerEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class);
    }

    /**
     * @return BelongsTo<WalletTransaction, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /**
     * @return HasOne<WalletTransaction, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
