<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yönetim panelindeki her değişiklik isteğini (POST/PUT/PATCH/DELETE) admin_logs
 * tablosuna yazar. Şifre, token ve dosya içerikleri kaydedilmez.
 */
class LogAdminAction
{
    private const HIDDEN = ['_token', '_method', 'password', 'password_confirmation', 'code', 'recovery_code', 'secret'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodSafe() && $request->user() instanceof User) {
            DB::table('admin_logs')->insert([
                'admin_id' => $request->user()->id,
                'action' => (string) ($request->route()?->getName() ?? 'unknown'),
                'route' => substr($request->path(), 0, 160),
                'method' => $request->method(),
                'payload' => json_encode(Arr::except($request->except(array_keys($request->allFiles())), self::HIDDEN), JSON_UNESCAPED_UNICODE),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}
