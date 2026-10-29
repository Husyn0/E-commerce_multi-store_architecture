<?php

use App\Http\Middleware\RequireActiveTenant;
use App\Http\Middleware\RequireTenantAdmin;
use App\Http\Middleware\RequireTenantHostType;
use App\Http\Middleware\ResolveTenantFromHost;
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
        $middleware->alias([
            'tenant.resolve' => ResolveTenantFromHost::class,
            'tenant.host' => RequireTenantHostType::class,
            'tenant.active' => RequireActiveTenant::class,
            'tenant.admin' => RequireTenantAdmin::class,
        ]);

        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : route('login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
