<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dil tercihini uygular: kullanıcı hesabı > oturum > varsayılan (tr).
 *
 * Tarayıcı diline göre otomatik seçim, İngilizce arayüz çevirileri (lang/en.json)
 * tamamlandığında eklenecek; aksi halde yarı çevrilmiş sayfalar oluşur.
 */
class ApplyUserPreferences
{
    public const LOCALES = ['tr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? config('app.locale');

        app()->setLocale(in_array($locale, self::LOCALES, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
