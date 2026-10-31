<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DownloadController;
use App\Http\Resources\V1\LedgerEntryResource;
use App\Http\Resources\V1\ProductResource;
use App\Http\Resources\V1\UserResource;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\PurchaseEntitlement;
use App\Models\WalletLedgerEntry;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Belirteç sahibinin hesabı: profil, puan cüzdanı, kütüphane ve indirme bağlantıları.
 */
class AccountController extends Controller
{
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function wallet(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->walletFor($request->user())->refresh();
        $entries = WalletLedgerEntry::query()->where('wallet_id', $wallet->id)->with('transaction')->latest('id')->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return LedgerEntryResource::collection($entries)->additional(['balance_points' => (int) $wallet->balance])->response();
    }

    public function library(Request $request): JsonResponse
    {
        $entitlements = PurchaseEntitlement::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->with(['product' => fn ($q) => $q->withTrashed()->with(['creator.creatorProfile', 'category']), 'license'])
            ->latest('granted_at')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return response()->json([
            'data' => $entitlements->getCollection()->map(fn (PurchaseEntitlement $entitlement) => [
                'acquired_at' => $entitlement->granted_at?->toIso8601String(),
                'source' => $entitlement->source?->value,
                'license' => $entitlement->license?->code,
                'product' => (new ProductResource($entitlement->product))->toArray($request),
            ])->values(),
            'meta' => ['current_page' => $entitlements->currentPage(), 'last_page' => $entitlements->lastPage(), 'total' => $entitlements->total()],
        ]);
    }

    /**
     * Sahip olunan ürünün indirilebilir dosyaları için kısa süreli imzalı bağlantılar.
     */
    public function downloads(Request $request, Product $product): JsonResponse
    {
        $user = $request->user();
        $owns = $product->creator_id === $user->id || ($product->isPublished() && PurchaseEntitlement::query()
            ->where(['user_id' => $user->id, 'product_id' => $product->id])->whereNull('revoked_at')->exists());

        abort_unless($owns, 403, __('Bu ürünün dosyalarına erişim hakkın yok.'));

        return response()->json(['data' => $product->files()->where('is_active', true)->get()->map(fn (ProductFile $file) => [
            'name' => $file->original_name,
            'extension' => $file->extension,
            'size_bytes' => (int) $file->size_bytes,
            'available' => $file->isDownloadable(),
            'url' => $file->isDownloadable() ? DownloadController::temporaryApiUrl($file, $user) : null,
        ])->values(), 'expires_in_minutes' => (int) config('marketplace.storage.download_link_ttl_minutes')]);
    }
}
