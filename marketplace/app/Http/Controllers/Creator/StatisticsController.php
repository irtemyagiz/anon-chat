<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Services\Analytics\CreatorStatistics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatisticsController extends Controller
{
    public function __invoke(Request $request, CreatorStatistics $statistics): View
    {
        $period = array_key_exists((string) $request->query('donem'), CreatorStatistics::PERIODS) ? (string) $request->query('donem') : 'gunluk';
        $series = $statistics->series($request->user()->id, $period);
        $products = $statistics->products($request->user()->id);

        return view('creator.statistics', [
            'period' => $period,
            'series' => $series,
            'totals' => [
                'sales' => array_sum(array_column($series, 'sales')),
                'revenue_cents' => array_sum(array_column($series, 'revenue_cents')),
                'downloads' => array_sum(array_column($series, 'downloads')),
                'followers' => array_sum(array_column($series, 'followers')),
            ],
            'products' => $products,
            'mostViewed' => $products->sortByDesc('views_count')->filter(fn (object $product) => $product->views_count > 0)->take(5)->values(),
        ]);
    }
}
