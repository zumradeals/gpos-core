<?php

use App\Http\Middleware\RequireActiveCommercialContext;
use App\Http\Middleware\RequireCommercialPermission;
use App\Http\Middleware\RequireCurrentActor;
use App\Http\Middleware\ResolveActiveCommercialContext;
use App\Http\Middleware\ResolveCurrentActor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ResolveCurrentActor::class,
        ]);

        $middleware->alias([
            'actor.required' => RequireCurrentActor::class,
            'context.active' => ResolveActiveCommercialContext::class,
            'context.required' => RequireActiveCommercialContext::class,
            'commercial.permission' => RequireCommercialPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
