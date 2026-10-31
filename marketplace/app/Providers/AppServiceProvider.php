<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Currency\Providers\ExchangeRateProvider;
use App\Services\Currency\Providers\FrankfurterProvider;
use App\Services\Files\Scanners\ClamAvScanner;
use App\Services\Files\Scanners\FileScanner;
use App\Services\Files\Scanners\NullScanner;
use App\Services\Payments\PaymentGatewayManager;
use App\View\Composers\LayoutComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);

        $this->app->bind(FileScanner::class, fn (): FileScanner => match (config('marketplace.scanner.driver')) {
            'clamav' => new ClamAvScanner(config('marketplace.scanner.clamav_binary')),
            default => new NullScanner,
        });

        $this->app->bind(ExchangeRateProvider::class, function (): ExchangeRateProvider {
            return match (config('marketplace.exchange_rates.provider')) {
                'frankfurter' => new FrankfurterProvider(
                    config('marketplace.exchange_rates.providers.frankfurter.url'),
                    (int) config('marketplace.exchange_rates.providers.frankfurter.timeout'),
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        View::composer('components.layouts.app', LayoutComposer::class);

        /*
         | Yönetici her yetkiye sahiptir. Noktalı yetenekler ("payments.manage") rol
         | izinlerinden okunur; noktasız olanlar (ör. "update") Policy sınıflarına düşer.
         */
        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->isActive()) {
                return false;
            }

            if ($user->isAdmin()) {
                return true;
            }

            return str_contains($ability, '.') ? $user->hasPermission($ability) : null;
        });

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
