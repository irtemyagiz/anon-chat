<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Comment;
use App\Models\FileFormat;
use App\Models\Product;
use App\Models\Tag;
use App\Services\Catalog\ProductSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request, ProductSearch $search): View
    {
        $filters = array_filter($request->only(ProductSearch::FILTERS), fn ($value) => is_string($value) && $value !== '');

        return view('products.index', [
            'products' => $search->paginate($filters),
            'filters' => $filters,
            'categories' => Category::query()->whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->get(),
            'formats' => FileFormat::query()->where('is_active', true)->orderBy('extension')->pluck('extension')->all(),
            'tagName' => isset($filters['etiket']) ? Tag::query()->where('slug', $filters['etiket'])->value('name') : null,
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

        $user = $request->user();

        return view('products.show', [
            'product' => $product,
            'similar' => $search->query(['kategori' => $product->category->slug])->whereKeyNot($product->id)->limit(4)->get(),
            'reviews' => $product->reviews()->where('status', 'published')->with('user:id,username,first_name')->latest()->limit(20)->get(),
            'comments' => $product->comments()->whereNull('parent_id')->where('status', Comment::Published)
                ->with(['user:id,username,first_name', 'replies.user:id,username,first_name'])->latest()->limit(30)->get(),
            'state' => [
                'liked' => $user !== null && DB::table('product_likes')->where(['user_id' => $user->id, 'product_id' => $product->id])->exists(),
                'favorited' => $user !== null && DB::table('favorites')->where(['user_id' => $user->id, 'product_id' => $product->id])->exists(),
                'myReview' => $user ? $product->reviews()->where('user_id', $user->id)->first() : null,
                'following' => $user !== null && DB::table('follows')->where(['follower_id' => $user->id, 'creator_id' => $product->creator_id])->exists(),
            ],
        ]);
    }
}
