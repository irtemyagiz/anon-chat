<?php

namespace App\View\Composers;

use App\Models\Category;
use App\Models\Wallet;
use App\Services\Currency\CurrencyResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Ortak düzen (menü, alt bilgi) için gereken verileri sağlar.
 */
class LayoutComposer
{
    public function __construct(private readonly CurrencyResolver $currencies) {}

    public function compose(View $view): void
    {
        $user = request()->user();

        $view->with([
            'navCategories' => Cache::remember('nav.categories.'.app()->getLocale(), 600, fn (): array => Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'slug', 'name'])
                ->map(fn (Category $category): array => ['slug' => $category->slug, 'name' => $category->localizedName()])
                ->all()),
            'displayCurrency' => $this->currencies->displayCurrency($user, request()),
            'walletBalance' => $user ? (int) Wallet::query()->where('user_id', $user->id)->value('balance') : null,
            'recentNotifications' => $user ? $user->notifications()->limit(6)->get() : collect(),
            'unreadNotifications' => $user ? $user->unreadNotifications()->count() : 0,
            'themePreference' => $user?->theme ?? request()->cookie('theme', 'system'),
        ]);
    }
}
