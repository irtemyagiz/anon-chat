<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AlreadyOwnedException;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductLicense;
use App\Services\Sales\PurchaseService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Puanla satın alma. request_id istemcinin ürettiği UUID'dir; aynı istek tekrar
 * gönderilirse (ağ hatası, çift tıklama) ikinci kez puan düşülmez, aynı sipariş döner.
 */
class PurchaseController extends Controller
{
    public function store(Request $request, Product $product, PurchaseService $purchases, WalletService $wallets): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => __('Satın alma için e-posta adresinizi doğrulayın.')], 403);
        }

        $data = $request->validate([
            'license_id' => ['nullable', 'integer'],
            'request_id' => ['required', 'uuid'],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        $license = isset($data['license_id']) ? ProductLicense::query()->where('product_id', $product->id)->find($data['license_id']) : null;

        try {
            if ($product->is_free) {
                $purchases->claimFree($request->user(), $product);

                return response()->json(['status' => 'claimed', 'balance_points' => $wallets->balanceOf($request->user())], 201);
            }

            $order = $purchases->purchase($request->user(), $product, $license, $data['request_id'], $data['coupon'] ?? null, $request->ip());
        } catch (InsufficientPointsException $exception) {
            return response()->json(['message' => __('Yetersiz puan.'), 'shortfall_points' => $exception->shortfall(), 'top_up_url' => route('top-up.create', ['eksik' => $exception->shortfall()])], 402);
        } catch (AlreadyOwnedException) {
            return response()->json(['message' => __('Bu modele zaten sahipsin.')], 409);
        } catch (MarketplaceException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'status' => 'completed',
            'order' => ['number' => $order->number, 'total_points' => (int) $order->total_points, 'created_at' => $order->created_at?->toIso8601String()],
            'balance_points' => $wallets->balanceOf($request->user()),
        ], 201);
    }
}
