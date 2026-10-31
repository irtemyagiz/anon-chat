<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\CreatorEarning;
use App\Models\OrderItem;
use App\Services\Earnings\CreatorEarningService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function sales(Request $request): View
    {
        return view('creator.sales', [
            'items' => OrderItem::query()
                ->where('creator_id', $request->user()->id)
                ->with(['order:id,number,created_at', 'license:id,code,name', 'earning:id,order_item_id,status,available_at'])
                ->latest('id')
                ->paginate(25),
        ]);
    }

    public function earnings(Request $request, CreatorEarningService $earnings): View
    {
        return view('creator.earnings', [
            'balance' => $earnings->lockBalance($request->user()->id),
            'earnings' => CreatorEarning::query()
                ->where('creator_id', $request->user()->id)
                ->with('orderItem:id,product_title')
                ->latest('id')
                ->paginate(25),
            'holdDays' => (int) config('marketplace.earnings.hold_days'),
        ]);
    }
}
