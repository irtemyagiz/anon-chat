<?php

namespace App\Models;

use App\Enums\CommissionScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform komisyon kuralı. rate_bps baz puan cinsindendir (2000 = %20).
 */
#[Fillable(['scope', 'creator_id', 'category_id', 'name', 'rate_bps', 'starts_at', 'ends_at', 'is_active'])]
class CommissionRule extends Model
{
    protected function casts(): array
    {
        return [
            'scope' => CommissionScope::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Şu an geçerli (aktif ve tarih aralığında) kurallar.
     *
     * @param  Builder<CommissionRule>  $query
     */
    public function scopeCurrentlyEffective(Builder $query): void
    {
        $now = now();

        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }
}
