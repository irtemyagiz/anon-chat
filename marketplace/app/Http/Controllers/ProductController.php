<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request, ProductSearch $search): View
    {
        $filters = $request->only('q', 'kategori', 'fiyat', 'sirala', 'min', 'max');

        return view('products.index', [
            'products' => $search->paginate($filters),
            'filters' => $filters,
            'categories' => Category::query()->whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function show(Request $request, Product $product, ProductSearch $search): View
    {
        abort_unless($product->isPublished(), 404);

        /** Görüntülenme oturum başına bir kez sayılır; ürün sahibinin ziyaretleri sayılmaz. */
        $viewed = $request->session()->get('viewed_products', []);

        if (! in_array($product->id, $viewed, true) && $request->user()?->id !== $product->creator_id) {
            $product->increment('views_count');
            $request->session()->put('viewed_products', array_slice([...$viewed, $product->id], -200));
        }

        $product->load(['creator.creatorProfile', 'category.parent', 'images', 'licenses.license', 'tags', 'versions']);

        return view('products.show', [
            'product' => $product,
            'similar' => $search->query(['kategori' => $product->category->slug])->whereKeyNot($product->id)->limit(4)->get(),
        ]);
    }
}
