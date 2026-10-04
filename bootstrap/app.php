<?php

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
        // Hosting platforms (e.g. Laravel Cloud) sit behind a load balancer that terminates HTTPS.
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'staff' => \App\Http\Middleware\EnsureStaff::class,
        ]);
        $middleware->redirectUsersTo(fn () => \App\Http\Controllers\AuthController::homeFor(auth()->user()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
