<?php

namespace App\Services\Earnings;

use App\Enums\EarningStatus;
use App\Models\CreatorBalance;
use App\Models\CreatorEarning;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Creator hakedişleri (USD sent). Puan cüzdanından tamamen bağımsızdır.
 *
 * Yaşam döngüsü: pending (iade/itiraz bekleme süresi) → available → paid,
 * veya iade halinde reversed. creator_balances özet tablosu her geçişte
 * kilitli satır üzerinde güncellenir.
 */
class CreatorEarningService
{
    public function recordSale(OrderItem $item): CreatorEarning
    {
        return DB::transaction(function () use ($item): CreatorEarning {
            $balance = $this->lockBalance($item->creator_id);

            $earning = CreatorEarning::forceCreate([
                'order_item_id' => $item->id,
                'creator_id' => $item->creator_id,
                'gross_cents' => $item->creator_amount_cents + $item->platform_amount_cents,
                'commission_bps' => $item->commission_bps,
                'platform_cents' => $item->platform_amount_cents,
                'net_cents' => $item->creator_amount_cents,
                'status' => EarningStatus::Pending,
                'available_at' => now()->addDays((int) config('marketplace.earnings.hold_days')),
            ]);

            $balance->pending_cents += $earning->net_cents;
            $balance->save();

            return $earning;
        });
    }

    /**
     * Bekleme süresi dolan kazançları kullanılabilir bakiyeye aktarır. Zamanlayıcı ile çalışır.
     */
    public function releaseMatured(): int
    {
        $released = 0;

        CreatorEarning::query()
            ->where('status', EarningStatus::Pending)
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->chunkById(200, function ($earnings) use (&$released): void {
                foreach ($earnings as $earning) {
                    DB::transaction(function () use ($earning, &$released): void {
                        $earning = CreatorEarning::query()->lockForUpdate()->find($earning->id);

                        if ($earning->status !== EarningStatus::Pending) {
                            return;
                        }

                        $balance = $this->lockBalance($earning->creator_id);
                        $balance->pending_cents -= $earning->net_cents;
                        $balance->available_cents += $earning->net_cents;
                        $balance->save();

                        $earning->forceFill(['status' => EarningStatus::Available, 'released_at' => now()])->save();
                        $released++;
                    });
                }
            });

        return $released;
    }

    /**
     * İade edilen satışın kazancını geri alır. Ödenmiş kazanç geri alınırsa
     * kullanılabilir bakiye eksiye düşer ve sonraki kazançlardan mahsup edilir.
     */
    public function reverse(OrderItem $item): ?CreatorEarning
    {
        return DB::transaction(function () use ($item): ?CreatorEarning {
            $earning = CreatorEarning::query()->where('order_item_id', $item->id)->lockForUpdate()->first();

            if ($earning === null || $earning->status === EarningStatus::Reversed) {
                return $earning;
            }

            $balance = $this->lockBalance($earning->creator_id);

            if ($earning->status === EarningStatus::Pending) {
                $balance->pending_cents -= $earning->net_cents;
            } else {
                $balance->available_cents -= $earning->net_cents;
            }

            $balance->reversed_cents += $earning->net_cents;
            $balance->save();

            $earning->forceFill(['status' => EarningStatus::Reversed, 'reversed_at' => now()])->save();

            return $earning;
        });
    }

    public function lockBalance(int $creatorId): CreatorBalance
    {
        CreatorBalance::unguarded(fn () => CreatorBalance::query()->createOrFirst(['creator_id' => $creatorId]));

        return CreatorBalance::query()->where('creator_id', $creatorId)->lockForUpdate()->firstOrFail();
    }
}
