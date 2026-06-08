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
        // Trust load balancer / reverse proxy (AWS ALB, Nginx, CloudFront).
        // Tanpa ini, di belakang proxy yang terminate SSL, Laravel melihat request
        // sebagai HTTP biasa sehingga route()/url() menghasilkan URL http:// ->
        // browser memblokirnya sebagai Mixed Content saat halaman dibuka via HTTPS.
        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // TAMBAHKAN INI - Exclude API dari CSRF
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->encryptCookies(except: [
            'ecosystem-session',
        ]);
        
        $middleware->alias([
            'auth.session'      => CheckAuthToken::class,
            'jarvies.api_key'   => CheckJarviesApiKey::class,
            'mobile.employee'   => \App\Http\Middleware\EnsureMobileEmployee::class,
            'external.api_key'  => \App\Http\Middleware\CheckExternalApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();