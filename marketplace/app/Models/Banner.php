<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['placement', 'title', 'subtitle', 'image_path', 'link_url', 'link_label', 'sort_order', 'is_active', 'starts_at', 'ends_at'])]
class Banner extends Model
{
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'subtitle' => 'array',
            'link_label' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Banner>  $query
     */
    public function scopeLive(Builder $query, string $placement): void
    {
        $query->where('placement', $placement)->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('sort_order');
    }
}
