<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Askıya alınmış hesapların API belirteçleri kullanılamaz (askıya alma belirteçleri
 * zaten siler; bu katman ek güvencedir).
 */
class EnsureApiUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && ! $request->user()->isActive()) {
            $request->user()->currentAccessToken()?->delete();

            return response()->json(['message' => __('Hesabınız aktif değil.')], 403);
        }

        return $next($request);
    }
}
