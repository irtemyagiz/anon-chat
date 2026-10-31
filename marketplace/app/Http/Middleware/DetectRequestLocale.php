<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oturumdan bağımsız ilk dil tespiti (?lang veya tarayıcı dili). Rota bulunamadığında
 * oluşan hata sayfaları da doğru dilde gösterilir. Oturum ve hesap tercihi, rota
 * eşleştikten sonra ApplyUserPreferences ile uygulanır.
 */
class DetectRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('lang');
        $locale = is_string($locale) && in_array($locale, ApplyUserPreferences::LOCALES, true)
            ? $locale
            : ($request->getPreferredLanguage(ApplyUserPreferences::LOCALES) ?? config('app.locale'));

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
