<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PointPurchaseStatus;
use App\Enums\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Models\PointPackage;
use App\Models\PointPurchase;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Puan paketleri, minimum yükleme ve puan ekonomisi raporu.
 * Paket fiyatı her zaman 1 USD = 100 POINT kuralından hesaplanır; elle girilmez.
 */
class PointController extends Controller
{
    public function index(SettingsService $settings): View
    {
        $userWallets = Wallet::query()->whereNotNull('user_id');

        return view('admin.points', [
            'packages' => PointPackage::query()->orderBy('sort_order')->withCount('purchases')->get(),
            'minimum' => $settings->integer('points.minimum_top_up'),
            'economy' => [
                'loaded' => (int) PointPurchase::query()->where('status', PointPurchaseStatus::Paid)->sum('points'),
                'outstanding' => (int) (clone $userWallets)->sum('balance'),
                'negative_wallets' => (clone $userWallets)->where('balance', '<', 0)->count(),
                'adjustments' => WalletTransaction::query()->where('type', WalletTransactionType::AdminAdjustment)->count(),
                'refunded' => (int) PointPurchase::query()->where('status', PointPurchaseStatus::Refunded)->sum('points'),
                'disputed' => (int) PointPurchase::query()->where('status', PointPurchaseStatus::Disputed)->sum('points'),
            ],
        ]);
    }

    public function updateMinimum(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate(['minimum' => ['required', 'integer', 'min:100', 'max:100000']]);
        $settings->set('points.minimum_top_up', (int) $data['minimum'], $request->user(), 'points');

        return back()->with('status', __('Minimum yükleme güncellendi.'));
    }

    public function storePackage(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validatePackage($request);

        $package = PointPackage::query()->create([
            'code' => Str::slug($data['name_tr']).'-'.Str::lower(Str::random(4)),
            'name' => ['tr' => $data['name_tr'], 'en' => ($data['name_en'] ?? null) ?: $data['name_tr']],
            'points' => $data['points'],
            'price_usd_cents' => $data['points'],
            'bonus_points' => 0,
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? ((int) PointPackage::query()->max('sort_order') + 10),
        ]);

        $audit->record('point_package.created', $package, null, ['points' => $package->points], $request->user());

        return back()->with('status', __('Paket eklendi.'));
    }

    public function updatePackage(Request $request, PointPackage $package, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validatePackage($request);
        $old = $package->only('points', 'is_active');

        $package->update([
            'name' => ['tr' => $data['name_tr'], 'en' => ($data['name_en'] ?? null) ?: $data['name_tr']],
            'points' => $data['points'],
            'price_usd_cents' => $data['points'],
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? $package->sort_order,
        ]);

        $audit->record('point_package.updated', $package, $old, $package->only('points', 'is_active'), $request->user());

        return back()->with('status', __('Paket güncellendi.'));
    }

    /**
     * Satın alımda kullanılmış paket silinmez (geçmiş kayıtlar korunur), pasifleştirilir.
     */
    public function destroyPackage(Request $request, PointPackage $package, AuditLogger $audit): RedirectResponse
    {
        if ($package->purchases()->exists()) {
            $package->update(['is_active' => false]);
            $message = __('Paket geçmiş yüklemelerde kullanıldığı için silinmedi, pasifleştirildi.');
        } else {
            $package->delete();
            $message = __('Paket silindi.');
        }

        $audit->record('point_package.removed', null, ['code' => $package->code], null, $request->user());

        return back()->with('status', $message);
    }

    /**
     * @return array{name_tr: string, name_en: ?string, points: int, sort_order: ?int}
     */
    private function validatePackage(Request $request): array
    {
        return $request->validate([
            'name_tr' => ['required', 'string', 'max:40'],
            'name_en' => ['nullable', 'string', 'max:40'],
            'points' => ['required', 'integer', 'min:100', 'max:1000000', Rule::notIn([0])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
    }
}
