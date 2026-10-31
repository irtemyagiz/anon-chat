<?php

namespace App\Http\Controllers;

use App\Enums\CreatorStatus;
use App\Models\Category;
use App\Models\CreatorProfile;
use App\Models\Product;
use App\Services\Catalog\ProductSearch;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(ProductSearch $search): View
    {
        $catalogIsEmpty = Product::query()->published()->doesntExist();

        $sections = $catalogIsEmpty ? [] : [
            'featured' => $search->query([])->where('is_featured', true)->limit(8)->get(),
            'popular' => $search->query(['sirala' => 'populer'])->limit(8)->get(),
            'latest' => $search->query(['sirala' => 'yeni'])->limit(8)->get(),
            'free' => $search->query(['fiyat' => 'ucretsiz'])->limit(8)->get(),
        ];

        return view('home', [
            'catalogIsEmpty' => $catalogIsEmpty,
            'sections' => $sections,
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->withCount(['products' => fn ($q) => $q->published()])
                ->orderBy('sort_order')
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
