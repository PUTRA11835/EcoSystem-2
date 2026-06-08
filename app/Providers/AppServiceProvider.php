<?php

namespace App\Providers;

use App\Enums\RoleId;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fix OpenSSL EC key generation on Windows (required for Web Push VAPID signing)
        if (env('OPENSSL_CONF') && !getenv('OPENSSL_CONF')) {
            putenv('OPENSSL_CONF=' . env('OPENSSL_CONF'));
        }
    }

    public function boot(): void
    {
        // Jaring pengaman: di production paksa semua URL generator pakai scheme https
        // supaya tidak ada Mixed Content meski header proxy tidak terbaca sempurna.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::define('viewApiDocs', function ($user = null) {
            return (int) session('user.role.id') === RoleId::EC_ADMINISTRATOR->value;
        });
    }
}
