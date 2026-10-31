<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Catalog\ProductSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('categories.index', [
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)])
                ->withCount(['products' => fn ($q) => $q->published()])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function show(Request $request, Category $category, ProductSearch $search): View
    {
        abort_unless($category->is_active, 404);

        $filters = [...$request->only('fiyat', 'sirala'), 'kategori' => $category->slug];

        return view('categories.show', [
            'category' => $category->load(['parent', 'children' => fn ($q) => $q->where('is_active', true)]),
            'products' => $search->paginate($filters),
            'filters' => $filters,
        ]);
    }
}
