<?php

namespace App\Http\Controllers;

use App\Enums\PointPurchaseStatus;
use App\Models\PointPurchase;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\Gateways\SandboxGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PointTopUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Yalnızca geliştirme ortamı: gerçek bir sağlayıcının ödeme sayfasını taklit eder.
 * "Ödemeyi tamamla" butonu, gerçek sağlayıcıların yaptığı gibi imzalı bir webhook
 * üretir ve normal webhook işleyicisinden geçirir. Production'da rotalar kayıtlı değildir.
 */
class SandboxCheckoutController extends Controller
{
    public function show(Request $request, PointPurchase $purchase): View
    {
        $this->authorizePurchase($request, $purchase);

        return view('wallet.sandbox-checkout', ['purchase' => $purchase]);
    }

    public function complete(Request $request, PointPurchase $purchase, PaymentGatewayManager $gateways, PointTopUpService $topUps): RedirectResponse
    {
        $this->authorizePurchase($request, $purchase);
        $outcome = $request->validate(['outcome' => ['required', Rule::in(['success', 'failure'])]])['outcome'];

        /** @var SandboxGateway $gateway */
        $gateway = $gateways->gateway('sandbox');

        $payload = json_encode([
            'id' => 'evt_'.Str::lower(Str::random(16)),
            'type' => $outcome === 'success' ? GatewayEvent::PaymentSucceeded : GatewayEvent::PaymentFailed,
            'purchase' => $purchase->uuid,
            'transaction' => 'sbx_tx_'.Str::lower(Str::random(16)),
            'amount' => $purchase->charge_amount_minor,
            'currency' => $purchase->charge_currency,
            'reason' => $outcome === 'failure' ? 'card_declined' : null,
        ], JSON_THROW_ON_ERROR);

        $webhook = Request::create(route('webhooks.payments', 'sandbox'), 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SANDBOX_SIGNATURE' => $gateway->sign($payload),
            'REMOTE_ADDR' => $request->ip(),
        ], content: $payload);

        $topUps->handleWebhook('sandbox', $webhook);

        return redirect()->route('top-up.result', $purchase);
    }

    private function authorizePurchase(Request $request, PointPurchase $purchase): void
    {
        abort_if(app()->isProduction(), 404);
        abort_unless($purchase->user_id === $request->user()->id && $purchase->provider === 'sandbox', 404);
        abort_unless($purchase->status === PointPurchaseStatus::Pending || $request->isMethod('GET'), 409);
    }
}
