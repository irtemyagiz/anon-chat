<?php

namespace App\Services\Analytics;

use App\Enums\OrderItemStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creator istatistikleri: satış, gelir, indirme ve yeni takipçi sayılarını günlük,
 * haftalık veya aylık dilimlerde verir. Toplama veritabanında gün bazında yapılır
 * (DATE() hem MySQL hem SQLite'ta çalışır), haftalık/aylık gruplama PHP'dedir.
 */
class CreatorStatistics
{
    /**
     * Dönem adı => [dilim sayısı, dilim birimi].
     *
     * @var array<string, array{0: int, 1: string}>
     */
    public const PERIODS = [
        'gunluk' => [30, 'day'],
        'haftalik' => [12, 'week'],
        'aylik' => [12, 'month'],
    ];

    /**
     * @return list<array{start: CarbonImmutable, label: string, sales: int, revenue_cents: int, downloads: int, followers: int}>
     */
    public function series(int $creatorId, string $period): array
    {
        [$count, $unit] = self::PERIODS[$period] ?? self::PERIODS['gunluk'];
        $buckets = $this->buckets($count, $unit);
        $since = $buckets[0]['start'];

        $sales = DB::table('order_items')
            ->where('creator_id', $creatorId)
            ->where('status', OrderItemStatus::Completed->value)
            ->where('created_at', '>=', $since)
            ->groupByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as sales, SUM(creator_amount_cents) as revenue')
            ->get();

        $downloads = DB::table('downloads')
            ->join('products', 'products.id', '=', 'downloads.product_id')
            ->where('products.creator_id', $creatorId)
            ->where('downloads.created_at', '>=', $since)
            ->groupByRaw('DATE(downloads.created_at)')
            ->selectRaw('DATE(downloads.created_at) as day, COUNT(*) as total')
            ->get();

        $followers = DB::table('follows')
            ->where('creator_id', $creatorId)
            ->where('created_at', '>=', $since)
            ->groupByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->get();

        foreach ($sales as $row) {
            $index = $this->bucketIndex($buckets, $row->day);
            $buckets[$index]['sales'] += (int) $row->sales;
            $buckets[$index]['revenue_cents'] += (int) $row->revenue;
        }

        foreach ($downloads as $row) {
            $buckets[$this->bucketIndex($buckets, $row->day)]['downloads'] += (int) $row->total;
        }

        foreach ($followers as $row) {
            $buckets[$this->bucketIndex($buckets, $row->day)]['followers'] += (int) $row->total;
        }

        return $buckets;
    }

    /**
     * Ürün bazında performans: görüntülenme, satış, gelir, indirme, dönüşüm oranı.
     *
     * @return Collection<int, object{id: int, uuid: string, title: string, slug: string, views_count: int, likes_count: int, downloads_count: int, sales_count: int, revenue_cents: int, conversion_bps: int}>
     */
    public function products(int $creatorId): Collection
    {
        $revenue = DB::table('order_items')
            ->where('creator_id', $creatorId)
            ->where('status', OrderItemStatus::Completed->value)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(creator_amount_cents) as revenue_cents');

        return DB::table('products')
            ->leftJoinSub($revenue, 'revenue', 'revenue.product_id', '=', 'products.id')
            ->where('products.creator_id', $creatorId)
            ->whereNull('products.deleted_at')
            ->select('products.id', 'products.uuid', 'products.title', 'products.slug', 'products.status', 'products.views_count', 'products.likes_count', 'products.downloads_count', 'products.sales_count')
            ->selectRaw('COALESCE(revenue.revenue_cents, 0) as revenue_cents')
            ->orderByDesc('products.sales_count')
            ->orderByDesc('products.views_count')
            ->get()
            ->map(function (object $row): object {
                $row->revenue_cents = (int) $row->revenue_cents;
                $row->conversion_bps = $row->views_count > 0 ? intdiv($row->sales_count * 10_000, $row->views_count) : 0;

                return $row;
            });
    }

    /**
     * @return list<array{start: CarbonImmutable, label: string, sales: int, revenue_cents: int, downloads: int, followers: int}>
     */
    private function buckets(int $count, string $unit): array
    {
        $current = match ($unit) {
            'week' => CarbonImmutable::now()->startOfWeek(),
            'month' => CarbonImmutable::now()->startOfMonth(),
            default => CarbonImmutable::now()->startOfDay(),
        };

        $buckets = [];

        for ($offset = $count - 1; $offset >= 0; $offset--) {
            $start = $current->sub($unit, $offset);
            $buckets[] = [
                'start' => $start,
                'label' => match ($unit) {
                    'week' => $start->format('d.m'),
                    'month' => $start->translatedFormat('M Y'),
                    default => $start->format('d.m'),
                },
                'sales' => 0,
                'revenue_cents' => 0,
                'downloads' => 0,
                'followers' => 0,
            ];
        }

        return $buckets;
    }

    /**
     * @param  list<array{start: CarbonImmutable}>  $buckets
     */
    private function bucketIndex(array $buckets, string $day): int
    {
        $date = CarbonImmutable::parse($day);

        for ($index = count($buckets) - 1; $index > 0; $index--) {
            if ($date->greaterThanOrEqualTo($buckets[$index]['start'])) {
                return $index;
            }
        }

        return 0;
    }
}
