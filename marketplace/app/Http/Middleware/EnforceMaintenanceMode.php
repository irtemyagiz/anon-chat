<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yönetim panelinden açılan bakım modu. Yöneticiler ve moderatörler siteyi
 * kullanmaya devam eder; giriş, 2FA ve ödeme webhook'ları her zaman açıktır.
 */
class EnforceMaintenanceMode
{
    private const ALWAYS_OPEN = ['giris', 'cikis', 'yonetim/*', 'webhooks/*', 'up'];

    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->boolean('site.maintenance') || $request->is(self::ALWAYS_OPEN) || $request->user()?->can('admin.access')) {
            return $next($request);
        }

        return response()->view('errors.503', ['maintenanceMessage' => $this->settings->get('site.maintenance_message')], 503, ['Retry-After' => '3600']);
    }
}
