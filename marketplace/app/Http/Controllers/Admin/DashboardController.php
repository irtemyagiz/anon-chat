<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CreatorStatus;
use App\Enums\OrderItemStatus;
use App\Enums\PayoutRequestStatus;
use App\Enums\PointPurchaseStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\CreatorPayoutRequest;
use App\Models\CreatorProfile;
use App\Models\OrderItem;
use App\Models\PointPurchase;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Currency\ExchangeRateService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ExchangeRateService $rates): View
    {
        $sales = OrderItem::query()->where('status', OrderItemStatus::Completed);

        return view('admin.dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'creators' => CreatorProfile::query()->where('status', CreatorStatus::Approved)->count(),
                'products' => Product::query()->where('status', ProductStatus::Published)->count(),
                'sales' => (clone $sales)->count(),
                'points_loaded' => (int) PointPurchase::query()->where('status', PointPurchaseStatus::Paid)->sum('points'),
                'points_spent' => (int) (clone $sales)->sum('price_points'),
                'platform_revenue_cents' => (int) (clone $sales)->sum('platform_amount_cents'),
                'outstanding_points' => (int) Wallet::query()->whereNotNull('user_id')->sum('balance'),
                'pending_payout_cents' => (int) CreatorPayoutRequest::query()->whereIn('status', [PayoutRequestStatus::Pending, PayoutRequestStatus::UnderReview, PayoutRequestStatus::Approved])->sum('amount_cents'),
                'active_users' => User::query()->where('last_login_at', '>=', now()->subDays(30))->count(),
                'new_users' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'queues' => [
                'creators' => CreatorProfile::query()->where('status', CreatorStatus::Pending)->count(),
                'products' => Product::query()->where('status', ProductStatus::PendingReview)->count(),
                'payouts' => CreatorPayoutRequest::query()->where('status', PayoutRequestStatus::Pending)->count(),
            ],
            'rate' => $rates->current('TRY'),
        ]);
    }
}
