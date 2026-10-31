<?php

namespace App\Services\Catalog;

use App\Enums\CreatorStatus;
use App\Models\Category;
use App\Models\CreatorProfile;
use App\Models\Product;
use App\Models\Tag;
use App\Services\Files\ImageProcessor;
use App\Services\Points\PointConverter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

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
        'begeni' => 'En çok beğenilenler',
    ];

    /**
     * Baskı teknolojisi filtresi seçenekleri.
     *
     * @var array<string, string>
     */
    public const TECHNOLOGIES = [
        'fdm' => 'FDM (filament)',
        'sla' => 'SLA / reçine',
        'sls' => 'SLS (toz)',
        'other' => 'Diğer',
    ];

    /**
     * Filtre adları; denetleyici yalnızca bunları istekten alır.
     *
     * @var list<string>
     */
    public const FILTERS = ['q', 'kategori', 'fiyat', 'sirala', 'min', 'max', 'format', 'lisans', 'teknoloji', 'etiket'];

    /**
     * @param  array<string, mixed>  $filters  Bkz. FILTERS. min/max ondalık USD'dir ("2.50").
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters  Bkz. FILTERS. min/max ondalık USD'dir ("2.50").
     * @return Builder<Product>
     */
    public function query(array $filters): Builder
    {
        $query = Product::query()
            ->published()
            ->with(['creator.creatorProfile', 'category']);

        if (filled($filters['q'] ?? null)) {
            $term = $this->likeTerm((string) $filters['q']);

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

        if (($minimum = $this->usdCents($filters['min'] ?? null)) !== null) {
            $query->where('price_cents', '>=', $minimum);
        }

        if (($maximum = $this->usdCents($filters['max'] ?? null)) !== null) {
            $query->where('price_cents', '<=', $maximum);
        }

        if (is_string($filters['format'] ?? null) && preg_match('/^[a-z0-9]{1,10}$/', $filters['format'])) {
            $query->whereHas('files', fn (Builder $files) => $files->where('is_active', true)->where('extension', $filters['format']));
        }

        if (($filters['lisans'] ?? null) === 'ticari') {
            $query->whereHas('licenses', fn (Builder $licenses) => $licenses
                ->where('is_active', true)
                ->whereHas('license', fn (Builder $license) => $license->where('allows_commercial_use', true)));
        }

        if (array_key_exists((string) ($filters['teknoloji'] ?? ''), self::TECHNOLOGIES)) {
            $query->where('print_technology', $filters['teknoloji']);
        }

        if (filled($filters['etiket'] ?? null)) {
            $query->whereHas('tags', fn (Builder $tags) => $tags->where('slug', (string) $filters['etiket']));
        }

        return match ($filters['sirala'] ?? 'yeni') {
            'populer' => $query->orderByDesc('likes_count')->orderByDesc('downloads_count'),
            'cok-satan' => $query->orderByDesc('sales_count'),
            'cok-indirilen' => $query->orderByDesc('downloads_count'),
            'ucuz' => $query->orderBy('price_cents'),
            'pahali' => $query->orderByDesc('price_cents'),
            'puan' => $query->orderByDesc('rating_average')->orderByDesc('reviews_count'),
            'begeni' => $query->orderByDesc('likes_count')->latest('published_at'),
            default => $query->latest('published_at'),
        };
    }

    /**
     * Başlıktaki arama kutusu için hızlı öneriler (ürün, tasarımcı, kategori, etiket).
     *
     * @return array{products: list<array{title: string, url: string, image: string|null, creator: string|null}>, designers: list<array{name: string, url: string}>, categories: list<array{name: string, url: string}>, tags: list<array{name: string, url: string}>}
     */
    public function suggestions(string $term): array
    {
        $like = $this->likeTerm($term);
        $disk = Storage::disk(config('marketplace.storage.public_media_disk'));

        $products = Product::query()->published()
            ->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', $like)))
            ->with('creator.creatorProfile:id,user_id,store_name')
            ->orderByDesc('sales_count')->orderByDesc('likes_count')
            ->limit(6)
            ->get(['id', 'uuid', 'slug', 'title', 'cover_image_path', 'creator_id']);

        $designers = CreatorProfile::query()
            ->where('status', CreatorStatus::Approved)
            ->where(fn (Builder $q) => $q->where('store_name', 'like', $like)->orWhere('designer_name', 'like', $like))
            ->limit(4)
            ->get(['id', 'store_name', 'store_slug']);

        /** JSON alanlarında LIKE, MySQL'de büyük/küçük harfe duyarlıdır; kategori sayısı az olduğundan PHP'de eşleştirilir. */
        $needle = $this->fold($term);
        $categories = Category::query()->where('is_active', true)->orderBy('sort_order')->get()
            ->filter(fn (Category $category) => collect($category->name)->contains(fn ($name) => str_contains($this->fold((string) $name), $needle)))
            ->take(4);

        $tags = Tag::query()->where('name', 'like', $like)
            ->whereHas('products', fn (Builder $q) => $q->published())
            ->limit(5)
            ->get(['id', 'name', 'slug']);

        return [
            'products' => $products->map(fn (Product $product) => [
                'title' => $product->title,
                'url' => route('products.show', $product),
                'image' => $product->cover_image_path ? $disk->url(ImageProcessor::variantPath($product->cover_image_path, 480)) : null,
                'creator' => $product->creator?->creatorProfile?->store_name,
            ])->all(),
            'designers' => $designers->map(fn (CreatorProfile $profile) => ['name' => $profile->store_name, 'url' => route('designers.show', $profile)])->all(),
            'categories' => $categories->map(fn (Category $category) => ['name' => $category->localizedName(), 'url' => route('categories.show', $category)])->values()->all(),
            'tags' => $tags->map(fn (Tag $tag) => ['name' => $tag->name, 'url' => route('products.index', ['etiket' => $tag->slug])])->all(),
        ];
    }

    /**
     * Türkçe I/İ/ı farkını yok sayan küçük harf karşılaştırma biçimi.
     */
    private function fold(string $value): string
    {
        return mb_strtolower(strtr(trim($value), ['İ' => 'i', 'I' => 'i', 'ı' => 'i']));
    }

    private function likeTerm(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)).'%';
    }

    /**
     * Kullanıcının girdiği ondalık USD tutarını sente çevirir; geçersizse null.
     */
    private function usdCents(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        try {
            return PointConverter::parseUsdToCents((string) $value);
        } catch (InvalidArgumentException) {
            return null;
        }
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
