<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sınırsız derinlikte ağaç yapısı (parent_id). Çok dilli alanlar JSON olarak
 * {"tr": "...", "en": "..."} biçiminde tutulur.
 */
#[Fillable([
    'parent_id', 'slug', 'name', 'description', 'image_path',
    'meta_title', 'meta_description', 'sort_order', 'is_active',
])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'meta_title' => 'array',
            'meta_description' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Geçerli dildeki adı döndürür; yoksa Türkçe'ye düşer.
     */
    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->name[$locale] ?? $this->name['tr'] ?? $this->slug;
    }
}
