<?php

namespace App\Http\Controllers;

use App\Exceptions\MarketplaceException;
use App\Models\Collection;
use App\Models\CreatorProfile;
use App\Models\Product;
use App\Services\Community\CommunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Favori, beğeni, takip ve koleksiyon işlemleri. JavaScript ile çağrıldığında
 * JSON, form gönderiminde yönlendirme döner (JS kapalıyken de çalışır).
 */
class CommunityController extends Controller
{
    public function __construct(private readonly CommunityService $community) {}

    public function favorite(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        abort_unless($product->isPublished(), 404);
        $on = $this->community->toggleFavorite($request->user(), $product);

        return $this->respond($request, ['favorited' => $on], $on ? __('Favorilere eklendi.') : __('Favorilerden çıkarıldı.'));
    }

    public function like(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        abort_unless($product->isPublished(), 404);
        $on = $this->community->toggleLike($request->user(), $product);

        return $this->respond($request, ['liked' => $on, 'count' => $product->fresh()->likes_count], $on ? __('Beğenildi.') : __('Beğeni kaldırıldı.'));
    }

    public function follow(Request $request, CreatorProfile $profile): JsonResponse|RedirectResponse
    {
        try {
            $on = $this->community->toggleFollow($request->user(), $profile);
        } catch (MarketplaceException $exception) {
            return $this->respond($request, ['error' => $exception->getMessage()], $exception->getMessage(), 422);
        }

        return $this->respond($request, ['following' => $on], $on ? __(':store takip ediliyor.', ['store' => $profile->store_name]) : __('Takipten çıkıldı.'));
    }

    public function storeCollection(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['nullable', 'boolean'],
            'product' => ['nullable', 'integer'],
        ]);

        try {
            $collection = $this->community->createCollection($request->user(), $data['name'], $data['description'] ?? null, (bool) ($data['is_public'] ?? true));

            if (isset($data['product']) && $product = Product::query()->published()->find($data['product'])) {
                $this->community->toggleInCollection($collection, $product);
            }
        } catch (MarketplaceException $exception) {
            return $this->respond($request, ['error' => $exception->getMessage()], $exception->getMessage(), 422);
        }

        return $this->respond($request, ['id' => $collection->id, 'name' => $collection->name], __('Koleksiyon oluşturuldu.'));
    }

    public function updateCollection(Request $request, Collection $collection): RedirectResponse
    {
        abort_unless($collection->user_id === $request->user()->id, 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:500'], 'is_public' => ['nullable', 'boolean']]);

        $collection->update(['name' => $data['name'], 'description' => $data['description'] ?? null, 'is_public' => (bool) ($data['is_public'] ?? false)]);

        return back()->with('status', __('Koleksiyon güncellendi.'));
    }

    public function destroyCollection(Request $request, Collection $collection): RedirectResponse
    {
        abort_unless($collection->user_id === $request->user()->id, 403);
        $collection->delete();

        return redirect()->route('account.collections')->with('status', __('Koleksiyon silindi.'));
    }

    public function toggleInCollection(Request $request, Collection $collection, Product $product): JsonResponse|RedirectResponse
    {
        abort_unless($collection->user_id === $request->user()->id, 403);

        try {
            $on = $this->community->toggleInCollection($collection, $product);
        } catch (MarketplaceException $exception) {
            return $this->respond($request, ['error' => $exception->getMessage()], $exception->getMessage(), 422);
        }

        return $this->respond($request, ['in' => $on], $on ? __(':name koleksiyonuna eklendi.', ['name' => $collection->name]) : __(':name koleksiyonundan çıkarıldı.', ['name' => $collection->name]));
    }

    /**
     * Herkese açık koleksiyon sayfası: /koleksiyon/{kullanıcı}/{slug}
     */
    public function showCollection(string $username, string $slug): View
    {
        $collection = Collection::query()
            ->whereHas('user', fn ($q) => $q->where('username', $username))
            ->where('slug', $slug)
            ->with('user')
            ->firstOrFail();

        abort_unless($collection->is_public || $collection->user_id === auth()->id(), 404);

        return view('collections.show', [
            'collection' => $collection,
            'products' => $collection->products()->published()->with(['creator.creatorProfile', 'category'])->paginate(24),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function respond(Request $request, array $payload, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([...$payload, 'message' => $message], $status);
        }

        return $status >= 400 ? back()->withErrors(['community' => $message]) : back()->with('status', $message);
    }
}
