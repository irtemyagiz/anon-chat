<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireTwoFactor;
use App\Services\Security\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthenticator $twoFactor) {}

    public function setup(Request $request): View|RedirectResponse
    {
        if ($this->twoFactor->isEnabled($request->user()) && ! session()->has('recovery_codes')) {
            return redirect()->route('admin.dashboard');
        }

        $secret = $request->session()->get('two_factor_setup_secret') ?? $this->twoFactor->generateSecret();
        $request->session()->put('two_factor_setup_secret', $secret);

        return view('admin.two-factor.setup', [
            'secret' => $secret,
            'uri' => $this->twoFactor->otpauthUri($request->user(), $secret),
            'recoveryCodes' => session('recovery_codes'),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $secret = $request->session()->get('two_factor_setup_secret');

        if ($secret === null || ! $this->twoFactor->verify($secret, $request->string('code'), $request->user())) {
            return back()->withErrors(['code' => __('Kod doğrulanamadı. Uygulamadaki güncel 6 haneli kodu girin.')]);
        }

        $codes = $this->twoFactor->enable($request->user(), $secret);
        $request->session()->forget('two_factor_setup_secret');
        $request->session()->put(RequireTwoFactor::SESSION_KEY, $request->user()->id);

        return redirect()->route('admin.two-factor.setup')->with('recovery_codes', $codes);
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $this->twoFactor->isEnabled($request->user())) {
            return redirect()->route('admin.two-factor.setup');
        }

        return view('admin.two-factor.challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['nullable', 'string'], 'recovery_code' => ['nullable', 'string']]);
        $user = $request->user();
        $key = 'admin-2fa:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => __('Çok fazla deneme yapıldı. Lütfen :seconds saniye sonra tekrar deneyin.', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        $valid = $request->filled('recovery_code')
            ? $this->twoFactor->useRecoveryCode($user, (string) $request->input('recovery_code'))
            : $this->twoFactor->verify($user->two_factor_secret, (string) $request->input('code'), $user);

        if (! $valid) {
            RateLimiter::hit($key);
            DB::table('security_events')->insert([
                'user_id' => $user->id,
                'type' => 'admin.two_factor_failed',
                'severity' => RateLimiter::attempts($key) >= 3 ? 'high' : 'medium',
                'context' => json_encode(['attempts' => RateLimiter::attempts($key)]),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return back()->withErrors(['code' => __('Kod geçersiz.')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put(RequireTwoFactor::SESSION_KEY, $user->id);

        return redirect()->intended(route('admin.dashboard'));
    }
}
