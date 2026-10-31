<?php

namespace App\Http\Controllers;

use App\Enums\CreatorStatus;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CreatorProfile;
use App\Models\Product;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\ProductSearch;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(ProductSearch $search): View
    {
        /**
         * Sıralama sorguları 5 dakika önbelleğe alınır. Önbellekte nesne değil yalnızca
         * kimlik listeleri tutulur (cache.serializable_classes kapalı); ürünler tek bir
         * birincil anahtar sorgusuyla, yayında olup olmadıkları yeniden kontrol edilerek yüklenir.
         */
        $sectionIds = Cache::remember(CatalogCache::HomeSections, CatalogCache::Seconds, fn (): array => [
            'featured' => $search->query([])->where('is_featured', true)->limit(8)->pluck('products.id')->all(),
            'popular' => $search->query(['sirala' => 'populer'])->limit(8)->pluck('products.id')->all(),
            'latest' => $search->query(['sirala' => 'yeni'])->limit(8)->pluck('products.id')->all(),
            'free' => $search->query(['fiyat' => 'ucretsiz'])->limit(8)->pluck('products.id')->all(),
        ]);

        $products = Product::query()->published()
            ->whereIn('id', array_merge(...array_values($sectionIds)))
            ->with(['creator.creatorProfile', 'category'])
            ->get()
            ->keyBy('id');

        $sections = array_map(fn (array $ids) => collect($ids)->map(fn (int $id) => $products->get($id))->filter()->values(), $sectionIds);
        $catalogIsEmpty = $sections['latest']->isEmpty();

        $categoryCounts = Cache::remember(CatalogCache::CategoryCounts, CatalogCache::Seconds, fn (): array => Product::query()->published()
            ->selectRaw('category_id, COUNT(*) as total')->groupBy('category_id')->pluck('total', 'category_id')->map(fn ($total) => (int) $total)->all());

        $categories = Category::query()->whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->get();
        $categories->each(fn (Category $category) => $category->setAttribute('products_count', $categoryCounts[$category->id] ?? 0));

        return view('home', [
            'catalogIsEmpty' => $catalogIsEmpty,
            'sections' => $catalogIsEmpty ? [] : $sections,
            'categories' => $categories,
            'banners' => Banner::query()->live('home')->get(),
            'collections' => Collection::query()
                ->where('is_public', true)
                ->whereHas('products', fn ($q) => $q->published())
                ->with('user:id,username,first_name')
                ->withCount(['products' => fn ($q) => $q->published()])
                ->orderByDesc('products_count')
                ->limit(4)
                ->get(),
            'designers' => CreatorProfile::query()
                ->where('status', CreatorStatus::Approved)
                ->with('user')
                ->whereHas('products', fn ($q) => $q->published())
                ->withCount(['products' => fn ($q) => $q->published()])
                ->orderByDesc('products_count')
                ->limit(6)
                ->get(),
        ]);
    }
}
