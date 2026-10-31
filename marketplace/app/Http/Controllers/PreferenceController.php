<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApplyUserPreferences;
use App\Services\Currency\CurrencyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tema, görüntüleme para birimi ve dil tercihleri. Giriş yapmış kullanıcıda
 * hesaba, ziyaretçide oturuma/çereze kaydedilir.
 */
class PreferenceController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
            'currency' => ['sometimes', Rule::in(config('marketplace.currency.supported'))],
            'locale' => ['sometimes', Rule::in(ApplyUserPreferences::LOCALES)],
        ]);

        if (isset($data['currency'])) {
            $request->session()->put(CurrencyResolver::SESSION_KEY, $data['currency']);
        }

        if (isset($data['locale'])) {
            $request->session()->put('locale', $data['locale']);
        }

        $request->user()?->forceFill(array_filter([
            'theme' => $data['theme'] ?? null,
            'display_currency' => $data['currency'] ?? null,
            'locale' => $data['locale'] ?? null,
        ]))->save();

        $response = $request->expectsJson() ? response()->json(['ok' => true]) : back();

        return isset($data['theme']) ? $response->withCookie(cookie()->forever('theme', $data['theme'], httpOnly: false)) : $response;
    }
}
