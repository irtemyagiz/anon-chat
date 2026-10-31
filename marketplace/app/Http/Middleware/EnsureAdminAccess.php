<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yönetim paneline erişim: "admin.access" izni olan aktif kullanıcılar (yönetici, moderatör).
 */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->isActive() && $user->can('admin.access'), 403);

        return $next($request);
    }
}
