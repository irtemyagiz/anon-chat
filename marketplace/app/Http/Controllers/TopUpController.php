<?php

namespace App\Http\Controllers;

use App\Enums\PointPurchaseStatus;
use App\Exceptions\MarketplaceException;
use App\Models\PaymentProvider;
use App\Models\PointPackage;
use App\Models\PointPurchase;
use App\Services\Currency\CurrencyResolver;
use App\Services\Currency\PriceFormatter;
use App\Services\Payments\Gateways\PaytrGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PointTopUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Puan yükleme: paket seçimi → sağlayıcıya yönlendirme → sonuç sayfası.
 * Puanlar bu controller'da değil, yalnızca doğrulanmış webhook ile yüklenir.
 */
class TopUpController extends Controller
{
    public function create(Request $request, CurrencyResolver $currencies, PriceFormatter $prices, PointTopUpService $topUps, PaymentGatewayManager $gateways): View
    {
        $currency = $currencies->paymentCurrency($request->user());
        $shortfall = max(0, (int) $request->integer('eksik'));

        $packages = PointPackage::query()->active()->get()->map(fn (PointPackage $package) => [
            'package' => $package,
            'price' => $prices->present($package->price_usd_cents, $currency),
        ]);

        return view('wallet.top-up', [
            'packages' => $packages,
            'currency' => $currency,
            'minimum' => $topUps->minimumTopUpPoints(),
            'shortfall' => $shortfall,
            'suggested' => $shortfall > 0 ? ($packages->first(fn (array $row) => $row['package']->totalPoints() >= $shortfall)['package'] ?? null) : null,
            'providers' => PaymentProvider::query()->where('is_enabled', true)->orderBy('sort_order')->get()
                ->filter(fn (PaymentProvider $provider) => $provider->supportsCurrency($currency) && $gateways->isConfigured($provider->code)),
        ]);
    }

    public function store(Request $request, PointTopUpService $topUps): RedirectResponse
    {
        $data = $request->validate([
            'package' => ['required', Rule::exists('point_packages', 'id')->where('is_active', true)],
            'provider' => ['required', 'string', 'max:30'],
        ]);

        try {
            $purchase = $topUps->createPurchase($request->user(), PointPackage::query()->findOrFail($data['package']), $data['provider'], $request->ip());
            $session = $topUps->startCheckout($purchase);
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['package' => $exception->getMessage()]);
        }

        return redirect()->away($session->redirectUrl);
    }

    /**
     * PayTR ödeme formu site içinde iframe ile açılır (PayTR iFrame API).
     */
    public function paytr(Request $request, PointPurchase $purchase): View|RedirectResponse
    {
        abort_unless($purchase->user_id === $request->user()->id && $purchase->provider === 'paytr', 404);

        if ($purchase->status !== PointPurchaseStatus::Pending || blank($purchase->provider_reference)) {
            return redirect()->route('top-up.result', $purchase);
        }

        return view('wallet.paytr', ['purchase' => $purchase, 'iframeUrl' => PaytrGateway::BASE_URL.'/odeme/guvenli/'.$purchase->provider_reference]);
    }

    public function result(Request $request, PointPurchase $purchase): View
    {
        abort_unless($purchase->user_id === $request->user()->id, 404);

        return view('wallet.top-up-result', ['purchase' => $purchase]);
    }
}
