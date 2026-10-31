<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CreatorStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CategoryResource;
use App\Http\Resources\V1\ProductResource;
use App\Models\Category;
use App\Models\CreatorProfile;
use App\Models\Product;
use App\Services\Catalog\ProductSearch;
use App\Services\Currency\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Herkese açık katalog uçları. Yalnızca yayındaki ürünler ve onaylı tasarımcılar döner.
 */
class CatalogController extends Controller
{
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->where('is_active', true)->withCount(['products' => fn ($q) => $q->published()])->orderBy('sort_order')->get()
        );
    }

    public function products(Request $request, ProductSearch $search): AnonymousResourceCollection
    {
        $filters = array_filter($request->only(ProductSearch::FILTERS), fn ($value) => is_string($value) && $value !== '');
        $perPage = min(48, max(1, $request->integer('per_page', 24)));

        return ProductResource::collection($search->paginate($filters, $perPage));
    }

    public function product(Product $product): ProductResource
    {
        abort_unless($product->isPublished(), 404);

        return new ProductResource($product->load(['creator.creatorProfile', 'category', 'images', 'licenses.license', 'tags', 'versions', 'files']));
    }

    public function designer(Request $request, CreatorProfile $profile, ProductSearch $search): JsonResponse
    {
        abort_unless($profile->status === CreatorStatus::Approved, 404);

        return response()->json(['data' => [
            'store_name' => $profile->store_name,
            'store_slug' => $profile->store_slug,
            'designer_name' => $profile->designer_name,
            'description' => $profile->description,
            'verified' => $profile->isVerified(),
            'url' => route('designers.show', $profile),
            'products' => ProductResource::collection($search->query([])->where('creator_id', $profile->user_id)->limit(24)->get())->toArray($request),
        ]]);
    }

    public function exchangeRate(ExchangeRateService $rates): JsonResponse
    {
        $rate = $rates->current('TRY');

        return response()->json(['data' => $rate === null ? null : [
            'base' => 'USD',
            'quote' => 'TRY',
            'rate' => $rate->formattedRate(),
            'source' => $rate->source,
            'fetched_at' => $rate->fetched_at?->toIso8601String(),
            'points_per_usd' => 100,
        ]]);
    }
}
