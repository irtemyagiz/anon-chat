<?php

namespace App\Http\Controllers;

use App\Exceptions\AlreadyOwnedException;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\MarketplaceException;
use App\Models\Product;
use App\Models\ProductLicense;
use App\Services\Sales\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            return back()->withErrors(['purchase' => $exception->getMessage()]);
        }

        return back()->with('status', __('Satın alma tamamlandı. Model kütüphanene eklendi.'));
    }
}
