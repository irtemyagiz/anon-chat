<?php

namespace App\Services\Sales;

use App\Enums\CommissionScope;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Services\Settings\SettingsService;

/**
 * Bir ürün satışı için platform komisyon oranını (bps) belirler.
 *
 * Öncelik: aktif kampanya > creator'a özel oran > kategori oranı (üst
 * kategorilere doğru) > varsayılan oran (yönetim paneli ayarı).
 */
class CommissionResolver
{
    public function __construct(private readonly SettingsService $settings) {}

    public function rateFor(Product $product): int
    {
        $rules = CommissionRule::query()->currentlyEffective()->latest('id')->get();

        $campaign = $rules->first(fn (CommissionRule $rule) => $rule->scope === CommissionScope::Campaign
            && ($rule->creator_id === null || $rule->creator_id === $product->creator_id)
            && ($rule->category_id === null || $rule->category_id === $product->category_id));

        if ($campaign !== null) {
            return $campaign->rate_bps;
        }

        $creatorRule = $rules->first(fn (CommissionRule $rule) => $rule->scope === CommissionScope::Creator
            && $rule->creator_id === $product->creator_id);

        if ($creatorRule !== null) {
            return $creatorRule->rate_bps;
        }

        $categoryRules = $rules->where('scope', CommissionScope::Category)->keyBy('category_id');
        $category = $product->category;

        while ($category instanceof Category) {
            if ($categoryRules->has($category->id)) {
                return $categoryRules[$category->id]->rate_bps;
            }

            $category = $category->parent;
        }

        return $this->settings->integer('commission.default_rate_bps');
    }

    /**
     * USD sent tutarını creator ve platform payına böler. Yuvarlama farkı platformda kalır.
     *
     * @return array{creator: int, platform: int}
     */
    public static function split(int $grossCents, int $rateBps): array
    {
        $creator = intdiv($grossCents * (10_000 - $rateBps), 10_000);

        return ['creator' => $creator, 'platform' => $grossCents - $creator];
    }
}
