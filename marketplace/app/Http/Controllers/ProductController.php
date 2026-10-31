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

    public function show(Product $product): View
    {
        abort_unless($product->isPublished(), 404);

        $product->load(['creator.creatorProfile', 'category.parent', 'images', 'licenses.license', 'tags', 'versions']);

        return view('products.show', ['product' => $product]);
    }
}
