<?php

namespace App\Http\Controllers\Creator;

use App\Enums\OrderItemStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Earnings\CreatorEarningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CreatorEarningService $earnings): View
    {
        $creatorId = $request->user()->id;
        $sales = OrderItem::query()->where('creator_id', $creatorId)->where('status', OrderItemStatus::Completed);
        $products = Product::query()->where('creator_id', $creatorId);

        $since = now()->subDays(29)->startOfDay();
        $daily = (clone $sales)
            ->where('created_at', '>=', $since)
            ->get(['created_at', 'creator_amount_cents'])
            ->groupBy(fn (OrderItem $item) => $item->created_at->toDateString());

        $chart = collect(range(0, 29))->map(function (int $offset) use ($since, $daily): array {
            $day = $since->copy()->addDays($offset);
            $items = $daily->get($day->toDateString(), collect());

            return ['date' => $day, 'count' => $items->count(), 'cents' => (int) $items->sum('creator_amount_cents')];
        });

        return view('creator.dashboard', [
            'balance' => $earnings->lockBalance($creatorId),
            'stats' => [
                'sales' => (clone $sales)->count(),
                'revenue_cents' => (int) (clone $sales)->sum('creator_amount_cents'),
                'downloads' => (int) (clone $products)->sum('downloads_count'),
                'views' => (int) (clone $products)->sum('views_count'),
                'followers' => DB::table('follows')->where('creator_id', $creatorId)->count(),
                'active_products' => (clone $products)->where('status', ProductStatus::Published)->count(),
            ],
            'chart' => $chart,
            'chartMax' => max(1, $chart->max('count')),
            'topProducts' => (clone $products)->where('sales_count', '>', 0)->orderByDesc('sales_count')->limit(5)->get(['id', 'uuid', 'title', 'slug', 'sales_count', 'views_count']),
            'attention' => (clone $products)->whereIn('status', [ProductStatus::Draft, ProductStatus::Rejected])->latest('updated_at')->limit(5)->get(['id', 'uuid', 'title', 'status', 'moderation_note']),
        ]);
    }
}
