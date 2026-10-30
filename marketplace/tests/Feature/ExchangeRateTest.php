<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\ExchangeRateLog;
use App\Models\User;
use App\Services\Currency\CurrencyResolver;
use App\Services\Currency\ExchangeRateService;
use App\Services\Currency\PriceFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    private function rates(): ExchangeRateService
    {
        return app(ExchangeRateService::class);
    }

    public function test_refresh_stores_provider_rate_without_float_loss(): void
    {
        Http::fake(['api.frankfurter.app/*' => Http::response('{"amount":1.0,"base":"USD","date":"2026-10-30","rates":{"TRY":40.1234}}')]);

        $rate = $this->rates()->refresh('TRY');

        $this->assertSame(40_123_400, $rate->rate_micros);
        $this->assertSame('frankfurter', $rate->source);
        $this->assertTrue(ExchangeRateLog::query()->sole()->succeeded);
    }

    public function test_provider_failure_keeps_last_successful_rate(): void
    {
        $this->setUsdTryRate('40');
        Http::fake(['api.frankfurter.app/*' => Http::response('down', 503)]);

        $this->assertNull($this->rates()->refresh('TRY'));
        $this->assertSame(40_000_000, $this->rates()->current('TRY')->rate_micros);
        $this->assertFalse(ExchangeRateLog::query()->sole()->succeeded);
    }

    public function test_outlier_rate_is_rejected(): void
    {
        $this->setUsdTryRate('40');
        Http::fake(['api.frankfurter.app/*' => Http::response('{"rates":{"TRY":4.0}}')]);

        $this->assertNull($this->rates()->refresh('TRY'));
        $this->assertSame(1, ExchangeRate::query()->count());
    }

    public function test_manual_rate_is_used_and_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $this->setUsdTryRate('40');

        $this->rates()->setManualRate('TRY', '41.25', $admin);

        $this->assertSame(41_250_000, $this->rates()->current('TRY')->rate_micros);
        $this->assertDatabaseHas('audit_logs', ['event' => 'exchange_rate.manual_set', 'actor_id' => $admin->id]);
    }

    public function test_turkish_user_sees_try_and_others_see_usd(): void
    {
        $this->setUsdTryRate('40');
        $resolver = app(CurrencyResolver::class);
        $formatter = app(PriceFormatter::class);

        $turkish = User::factory()->turkish()->create();
        $international = User::factory()->create(['country_code' => 'DE']);

        $this->assertSame('TRY', $resolver->displayCurrency($turkish));
        $this->assertSame('USD', $resolver->displayCurrency($international));

        $price = $formatter->present(1000, 'TRY');
        $this->assertSame(1000, $price['points']);
        $this->assertSame('400,00 TL', $price['formatted']);
        $this->assertSame('$10.00', $formatter->present(1000, 'USD')['formatted']);
    }

    public function test_manual_display_currency_overrides_country_and_ip_hint(): void
    {
        $resolver = app(CurrencyResolver::class);
        $user = User::factory()->turkish()->create(['display_currency' => 'USD']);
        $request = Request::create('/', server: ['HTTP_CF_IPCOUNTRY' => 'TR']);

        $this->assertSame('USD', $resolver->displayCurrency($user, $request));
        $this->assertSame('TRY', $resolver->displayCurrency(null, $request), 'Ziyaretçide IP yalnızca ipucu olarak kullanılır');
        $this->assertSame('TRY', $resolver->paymentCurrency($user), 'Ödeme para birimi fatura ülkesine göre belirlenir');
    }

    public function test_missing_rate_falls_back_to_usd_display(): void
    {
        $price = app(PriceFormatter::class)->present(399, 'TRY');

        $this->assertSame('USD', $price['currency']);
        $this->assertSame('$3.99', $price['formatted']);
    }
}
