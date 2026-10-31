<?php

namespace App\View\Components;

use App\Services\Currency\CurrencyResolver;
use App\Services\Currency\PriceFormatter;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Fiyatı puan + bölgesel para birimi karşılığıyla gösterir: "399 POINT · 159,60 TL".
 */
class Price extends Component
{
    /** @var array{points: int, usd_cents: int, currency: string, amount_minor: int, formatted: string} */
    public array $price;

    public function __construct(public int $cents, public string $size = 'md', ?string $currency = null)
    {
        $currency ??= app(CurrencyResolver::class)->displayCurrency(request()->user(), request());
        $this->price = app(PriceFormatter::class)->present($cents, $currency);
    }

    public function render(): View
    {
        return view('components.price');
    }
}
