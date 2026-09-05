<?php

use App\Http\Middleware\EnsureAccessContext;
use App\Http\Middleware\EnsureActiveOffice;
use App\Http\Middleware\RequireApplicationAssignment;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active.office' => EnsureAccessContext::class,
            'access.context' => EnsureAccessContext::class,
        ]);
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            RequireApplicationAssignment::class,
        );
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            EnsureActiveOffice::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
