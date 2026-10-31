<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Katalog listeleme, filtreleme ve sıralama. Yalnızca yayındaki ürünler döner.
 */
class ProductSearch
{
    /**
     * Arayüzde gösterilen sıralama seçenekleri (anahtar => etiket).
     *
     * @var array<string, string>
     */
    public const SORTS = [
        'yeni' => 'Yeni eklenenler',
        'populer' => 'Popüler',
        'cok-satan' => 'En çok satanlar',
        'cok-indirilen' => 'En çok indirilenler',
        'ucuz' => 'En düşük fiyat',
        'pahali' => 'En yüksek fiyat',
        'puan' => 'En yüksek puan',
    ];

    /**
     * @param  array{q?: string|null, kategori?: string|null, fiyat?: string|null, sirala?: string|null, min?: string|int|null, max?: string|int|null}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{q?: string|null, kategori?: string|null, fiyat?: string|null, sirala?: string|null, min?: string|int|null, max?: string|int|null}  $filters
     * @return Builder<Product>
     */
    public function query(array $filters): Builder
    {
        $query = Product::query()
            ->published()
            ->with(['creator.creatorProfile', 'category']);

        if (filled($filters['q'] ?? null)) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $filters['q'])).'%';

            $query->where(fn (Builder $q) => $q
                ->where('title', 'like', $term)
                ->orWhere('short_description', 'like', $term)
                ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', $term))
                ->orWhereHas('creator.creatorProfile', fn (Builder $store) => $store
                    ->where('store_name', 'like', $term)
                    ->orWhere('designer_name', 'like', $term)));
        }

        if (filled($filters['kategori'] ?? null)) {
            $category = Category::query()->where('slug', $filters['kategori'])->first();
            $query->whereIn('category_id', $category ? $this->categoryWithDescendants($category) : [0]);
        }

        match ($filters['fiyat'] ?? null) {
            'ucretsiz' => $query->where('is_free', true),
            'ucretli' => $query->where('is_free', false),
            default => null,
        };

        if (is_numeric($filters['min'] ?? null)) {
            $query->where('price_cents', '>=', (int) $filters['min']);
        }

        if (is_numeric($filters['max'] ?? null)) {
            $query->where('price_cents', '<=', (int) $filters['max']);
        }

        return match ($filters['sirala'] ?? 'yeni') {
            'populer' => $query->orderByDesc('likes_count')->orderByDesc('downloads_count'),
            'cok-satan' => $query->orderByDesc('sales_count'),
            'cok-indirilen' => $query->orderByDesc('downloads_count'),
            'ucuz' => $query->orderBy('price_cents'),
            'pahali' => $query->orderByDesc('price_cents'),
            'puan' => $query->orderByDesc('rating_average')->orderByDesc('reviews_count'),
            default => $query->latest('published_at'),
        };
    }

    /**
     * @return list<int>
     */
    private function categoryWithDescendants(Category $category): array
    {
        $ids = [$category->id];
        $frontier = [$category->id];

        while ($frontier !== []) {
            $frontier = Category::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
}
