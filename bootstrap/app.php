<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckAuthToken;
use App\Http\Middleware\CheckJarviesApiKey;
use App\Http\Middleware\ShareMenuPermissions;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::prefix('api')
                ->group(base_path('routes/lite.php'));
        },
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
        
        $middleware->web(append: [
            ShareMenuPermissions::class,
        ]);

        $middleware->alias([
            'auth.session'      => CheckAuthToken::class,
            'jarvies.api_key'   => CheckJarviesApiKey::class,
            'external.api_key'  => \App\Http\Middleware\CheckExternalApiKey::class,
            'menu'              => \App\Http\Middleware\CheckMenuAccess::class,
            'lite.auth'         => \App\Http\Middleware\LiteApiAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // CSRF token expired/invalid (session timeout, stale form tab, dsb).
        // Tanpa ini, Laravel merender halaman 419 langsung sebagai response
        // dari POST tsb (bukan redirect) -> browser mengira halaman itu "hasil
        // POST" -> reload/back kemudian memicu "Confirm Form Resubmission"
        // dan bisa berujung ERR_CACHE_MISS. Redirect back mengembalikan alur
        // ke pola redirect-setelah-POST yang aman untuk reload/back.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session expired. Please refresh the page and try again.',
                ], 419);
            }

            return redirect()
                ->back()
                ->withInput($request->except('_token', 'password', 'password_confirmation'))
                ->with('error', 'Your session expired. Please try again.');
        });
    })->create();