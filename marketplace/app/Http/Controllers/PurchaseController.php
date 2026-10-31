<?php

namespace App\Http\Controllers;

use App\Exceptions\AlreadyOwnedException;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\MarketplaceException;
use App\Models\Product;
use App\Models\ProductLicense;
use App\Services\Points\PointConverter;
use App\Services\Sales\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

class PurchaseController extends Controller
{
    public function store(Request $request, Product $product, PurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'license' => ['nullable', 'integer'],
            'request_id' => ['required', 'uuid'],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        $license = isset($data['license'])
            ? ProductLicense::query()->where('product_id', $product->id)->find($data['license'])
            : null;

        try {
            if ($product->is_free) {
                $purchases->claimFree($request->user(), $product);

                return back()->with('status', __('Model kütüphanene eklendi. Dosyaları indirebilirsin.'));
            }

            $purchases->purchase($request->user(), $product, $license, $data['request_id'], $data['coupon'] ?? null, $request->ip());
        } catch (InsufficientPointsException $exception) {
            return redirect()
                ->route('top-up.create', ['eksik' => $exception->shortfall()])
                ->with('status', __('Bu model için :points puan daha gerekiyor. Mevcut puanların korunur.', ['points' => $exception->shortfall()]));
        } catch (AlreadyOwnedException) {
            return back()->with('status', __('Bu modele zaten sahipsin.'));
        } catch (MarketplaceException $exception) {
            return back()->withInput($request->only('coupon'))->withErrors(['purchase' => $exception->getMessage()]);
        }

        return back()->with('status', __('Satın alma tamamlandı. Model kütüphanene eklendi.'));
    }

    /**
     * Kupon önizlemesi: indirimli tutarı gösterir, kuponu kullanmaz.
     */
    public function previewCoupon(Request $request, Product $product, PurchaseService $purchases): JsonResponse
    {
        $data = $request->validate([
            'license' => ['nullable', 'integer'],
            'coupon' => ['required', 'string', 'max:40'],
        ]);

        $license = isset($data['license'])
            ? ProductLicense::query()->where('product_id', $product->id)->where('is_active', true)->find($data['license'])
            : null;

        try {
            abort_unless($product->isPublished() && ! $product->is_free, 404);
            $quote = $purchases->previewCoupon($product, $license, $data['coupon']);
        } catch (MarketplaceException $exception) {
            return response()->json(['valid' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'valid' => true,
            ...$quote,
            'message' => __(':discount POINT indirim uygulandı. Ödenecek: :total POINT', [
                'discount' => number_format($quote['discount_points'], 0, ',', '.'),
                'total' => number_format($quote['total_points'], 0, ',', '.'),
            ]),
            'price_html' => Blade::render('<x-price :cents="$cents" size="lg"/>', ['cents' => PointConverter::pointsToUsdCents($quote['total_points'])]),
        ]);
    }
}
