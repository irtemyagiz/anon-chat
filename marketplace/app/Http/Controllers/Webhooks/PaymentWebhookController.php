<?php

namespace App\Http\Controllers\Webhooks;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Services\Payments\InvalidWebhookSignatureException;
use App\Services\Payments\PointTopUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ödeme sağlayıcılarından gelen sunucu-sunucu bildirimleri.
 * CSRF dışında tutulur; güvenlik imza doğrulaması ile sağlanır.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PointTopUpService $topUps): JsonResponse
    {
        try {
            $event = $topUps->handleWebhook($provider, $request);
        } catch (InvalidWebhookSignatureException) {
            return response()->json(['message' => 'invalid signature'], 400);
        } catch (MarketplaceException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['status' => $event->status->value]);
    }
}
