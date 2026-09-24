<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureApiPrincipal;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureAccountIsActive::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'api.principal' => EnsureApiPrincipal::class,
        ]);

        // An unauthenticated request to a child-guard route must land on the
        // child's own PIN/device entry screen, never the staff login form.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('crianca*')
            ? route('child.login')
            : route('login'));

        $middleware->redirectUsersTo(fn (Request $request) => $request->is('crianca*')
            ? route('child.home')
            : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
