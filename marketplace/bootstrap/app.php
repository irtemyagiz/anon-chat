<?php

use App\Http\Middleware\ApplyUserPreferences;
use App\Http\Middleware\DetectRequestLocale;
use App\Http\Middleware\EnforceMaintenanceMode;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureApiUserIsActive;
use App\Http\Middleware\EnsureApprovedCreator;
use App\Http\Middleware\LogAdminAction;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestForgery(except: ['webhooks/*']);
        $middleware->prepend(DetectRequestLocale::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->encryptCookies(except: ['theme']);
        $middleware->alias([
            'creator' => EnsureApprovedCreator::class,
            'admin' => EnsureAdminAccess::class,
            'admin.2fa' => RequireTwoFactor::class,
            'admin.log' => LogAdminAction::class,
            'api.active' => EnsureApiUserIsActive::class,
        ]);
        $middleware->web(append: [ApplyUserPreferences::class, EnforceMaintenanceMode::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
