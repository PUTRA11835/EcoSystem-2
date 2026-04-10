<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckAuthToken;
use App\Http\Middleware\CheckJarviesApiKey;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // TAMBAHKAN INI - Exclude API dari CSRF
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        
        $middleware->alias([
            'auth.session'    => CheckAuthToken::class,
            'jarvies.api_key' => CheckJarviesApiKey::class,
            'mobile.employee' => \App\Http\Middleware\EnsureMobileEmployee::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();