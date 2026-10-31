<?php

namespace App\Http\Controllers\Webhooks;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Services\Payments\InvalidWebhookSignatureException;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PointTopUpService;
use App\Services\Payments\RespondsToWebhook;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ödeme sağlayıcılarından gelen bildirimler. CSRF dışında tutulur; güvenlik imza
 * doğrulaması (veya iyzico'da sağlayıcıdan sonucun yeniden sorgulanması) ile sağlanır.
 * Sağlayıcı özel bir yanıt bekliyorsa (PayTR "OK", iyzico yönlendirme) adaptör verir.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PointTopUpService $topUps, PaymentGatewayManager $gateways): Response
    {
        try {
            $gateway = $gateways->gateway($provider);
        } catch (MarketplaceException|LogicException) {
            return response()->json(['message' => 'provider not available'], 404);
        }

        $record = null;
        $accepted = true;
        $status = 200;
        $message = null;

        try {
            $record = $topUps->handleWebhook($provider, $request);
        } catch (InvalidWebhookSignatureException) {
            [$accepted, $status, $message] = [false, 400, 'invalid signature'];
        } catch (MarketplaceException $exception) {
            [$accepted, $status, $message] = [false, 422, $exception->getMessage()];
        }

        if ($gateway instanceof RespondsToWebhook) {
            return $gateway->webhookResponse($request, $record, $accepted);
        }

        return $accepted
            ? response()->json(['status' => $record->status->value])
            : response()->json(['message' => $message], $status);
    }
}
