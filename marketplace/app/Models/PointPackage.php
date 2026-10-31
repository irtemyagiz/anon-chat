<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Puan paketi. Başlangıçta bonus puan verilmez (bonus_points = 0); alan
 * ileride kampanyalar için ayrılmıştır.
 */
#[Fillable(['code', 'name', 'points', 'price_usd_cents', 'bonus_points', 'is_active', 'sort_order'])]
class PointPackage extends Model
{
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<PointPackage>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * @return HasMany<PointPurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(PointPurchase::class);
    }

    public function totalPoints(): int
    {
        return $this->points + $this->bonus_points;
    }
}
