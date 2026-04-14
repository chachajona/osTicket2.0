<?php

use App\Http\Middleware\AuthenticateStaff;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LegacyAuthBridge;
use App\Http\Middleware\RequireDepartmentAccess;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['OSTSESSID']);
        $middleware->redirectUsersTo(function (Request $request): string {
            if ($request->route()?->named('scp.*')) {
                return route('scp.dashboard');
            }

            return '/';
        });

        $middleware->web(append: [
            LegacyAuthBridge::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'auth.staff' => AuthenticateStaff::class,
            'dept.access' => RequireDepartmentAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
