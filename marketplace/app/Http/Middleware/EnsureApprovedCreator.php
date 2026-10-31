<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Creator paneline yalnızca onaylanmış ve aktif creator'lar erişebilir.
 * Başvurusu bekleyenler durum sayfasına, diğerleri başvuru sayfasına yönlendirilir.
 */
class EnsureApprovedCreator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->isApprovedCreator() && $user->isActive()) {
            return $next($request);
        }

        return $user->creatorProfile !== null
            ? redirect()->route('creator.status')
            : redirect()->route('creator.apply');
    }
}
