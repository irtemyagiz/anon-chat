<?php

namespace App\Http\Middleware;

use App\Services\Security\TwoFactorAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yönetim panelinde iki aşamalı doğrulama zorunludur: kurulmamışsa kurulum
 * sayfasına, bu oturumda doğrulanmamışsa doğrulama sayfasına yönlendirir.
 */
class RequireTwoFactor
{
    public const SESSION_KEY = 'admin_two_factor_passed';

    public function __construct(private readonly TwoFactorAuthenticator $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $this->twoFactor->isEnabled($user)) {
            return redirect()->route('admin.two-factor.setup');
        }

        if ($request->session()->get(self::SESSION_KEY) !== $user->id) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('admin.two-factor.challenge');
        }

        return $next($request);
    }
}
