<?php

namespace App\Services\Payments;

use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sağlayıcı webhook'a belirli bir yanıt bekliyorsa (ör. PayTR düz metin "OK",
 * iyzico kullanıcı tarayıcısının sonuç sayfasına yönlendirilmesi) adaptör bunu uygular.
 */
interface RespondsToWebhook
{
    public function webhookResponse(Request $request, ?PaymentWebhookEvent $record, bool $accepted): Response;
}
