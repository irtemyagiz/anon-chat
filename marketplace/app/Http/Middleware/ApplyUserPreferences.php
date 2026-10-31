<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dil tercihini uygular: ?lang= parametresi > kullanıcı hesabı > oturum > tarayıcı dili > tr.
 *
 * ?lang=en adresleri arama motorlarına hreflang ile ayrı dil sürümü olarak bildirilir.
 * Yönetim paneli personel içindir ve Türkçe kalır.
 */
class ApplyUserPreferences
{
    public const LOCALES = ['tr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $queryLocale = $request->query('lang');

        if (is_string($queryLocale) && in_array($queryLocale, self::LOCALES, true)) {
            $request->session()->put('locale', $queryLocale);
        }

        $locale = (is_string($queryLocale) && in_array($queryLocale, self::LOCALES, true) ? $queryLocale : null)
            ?? $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $request->getPreferredLanguage(self::LOCALES);

        $locale = in_array($locale, self::LOCALES, true) ? $locale : config('app.locale');
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
