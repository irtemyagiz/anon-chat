<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateLog;
use App\Services\Currency\ExchangeRateService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExchangeRateController extends Controller
{
    public function index(ExchangeRateService $rates, SettingsService $settings): View
    {
        return view('admin.exchange-rates', [
            'current' => $rates->current('TRY'),
            'history' => ExchangeRate::query()->where('quote_currency', 'TRY')->with('setter:id,username')->latest('fetched_at')->latest('id')->paginate(20),
            'logs' => ExchangeRateLog::query()->latest('id')->limit(15)->get(),
            'autoUpdate' => $settings->boolean('exchange_rates.auto_update', true),
            'provider' => config('marketplace.exchange_rates.provider'),
            'interval' => config('marketplace.exchange_rates.update_interval_minutes'),
            'maxAge' => config('marketplace.exchange_rates.max_age_hours'),
        ]);
    }

    public function manual(Request $request, ExchangeRateService $rates): RedirectResponse
    {
        $data = $request->validate(['rate' => ['required', 'string', 'regex:/^\d{1,4}([.,]\d{1,6})?$/']]);
        $rate = $rates->setManualRate('TRY', $data['rate'], $request->user());

        return back()->with('status', __('Manuel kur kaydedildi: 1 USD = :rate TL', ['rate' => $rate->formattedRate()]));
    }

    public function refresh(ExchangeRateService $rates): RedirectResponse
    {
        $rate = $rates->refresh('TRY');

        return $rate === null
            ? back()->withErrors(['rate' => __('Kur sağlayıcısından yanıt alınamadı; son başarılı kur kullanılmaya devam ediyor. Ayrıntı için aşağıdaki kayıtlara bakın.')])
            : back()->with('status', __('Kur güncellendi: 1 USD = :rate TL', ['rate' => $rate->formattedRate()]));
    }

    public function toggleAuto(Request $request, SettingsService $settings): RedirectResponse
    {
        $settings->set('exchange_rates.auto_update', ! $settings->boolean('exchange_rates.auto_update', true), $request->user(), 'exchange_rates');

        return back()->with('status', __('Otomatik güncelleme ayarı değiştirildi.'));
    }
}
