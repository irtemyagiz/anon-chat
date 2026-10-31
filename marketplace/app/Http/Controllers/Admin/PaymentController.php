<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PointPurchaseStatus;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\PaymentDispute;
use App\Models\PaymentProvider;
use App\Models\PaymentWebhookEvent;
use App\Models\PointPurchase;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PointTopUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('durum');

        return view('admin.payments.index', [
            'status' => $status,
            'purchases' => PointPurchase::query()
                ->with('user:id,username,email')
                ->when(in_array($status, array_column(PointPurchaseStatus::cases(), 'value'), true), fn ($q) => $q->where('status', $status))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                    ->where('uuid', $request->string('q'))
                    ->orWhereHas('user', fn ($u) => $u->where('email', $request->string('q')))))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'providers' => PaymentProvider::query()->orderBy('sort_order')->get(),
            'disputes' => PaymentDispute::query()->with('pointPurchase.user:id,email')->latest('id')->limit(10)->get(),
        ]);
    }

    public function webhooks(): View
    {
        return view('admin.payments.webhooks', [
            'events' => PaymentWebhookEvent::query()->latest('id')->paginate(40),
        ]);
    }

    public function refund(Request $request, PointPurchase $purchase, PointTopUpService $topUps): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $topUps->refund($purchase, $request->user(), $data['reason']);
        } catch (InsufficientPointsException $exception) {
            return back()->withErrors(['refund' => __('Kullanıcı bu yüklemenin puanlarını harcamış (mevcut bakiye: :points). Kullanılmamış puan iadesi yapılamaz.', ['points' => $exception->available])]);
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with('status', __('İade işlendi ve puanlar geri alındı.'));
    }

    /**
     * Sağlayıcıyı açıp kapatır. Kod tarafında adaptörü olmayan sağlayıcı açılamaz.
     */
    public function toggleProvider(Request $request, PaymentProvider $provider, AuditLogger $audit, PaymentGatewayManager $gateways): RedirectResponse
    {
        if (! $provider->is_enabled && ! $gateways->isConfigured($provider->code)) {
            return back()->withErrors(['provider' => __(':name için API anahtarları girilmedi (:vars); sözleşme ve anahtarlar tamamlandıktan sonra etkinleştirilebilir.', ['name' => $provider->name, 'vars' => implode(', ', $gateways->missingSettings($provider->code)) ?: '-'])]);
        }

        if (! $provider->is_enabled && $provider->code === 'sandbox' && app()->isProduction()) {
            return back()->withErrors(['provider' => __('Test sağlayıcısı production ortamında açılamaz.')]);
        }

        $provider->update(['is_enabled' => ! $provider->is_enabled]);
        $audit->record('payment_provider.toggled', $provider, null, ['is_enabled' => $provider->is_enabled], $request->user());

        return back()->with('status', __('Ödeme sağlayıcısı güncellendi.'));
    }
}
