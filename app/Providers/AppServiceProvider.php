<?php

namespace App\Providers;

use App\Enums\HomeBase;
use App\Enums\RoleId;
use App\Models\Grade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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

        // Suntik opsi Grade & Home Base ke form employee (modal index + section
        // basicdata yang dipakai detail & profile). Satu sumber: tabel `grades`
        // + enum HomeBase — view tidak lagi hardcode daftarnya.
        View::composer(
            ['master.employee.index', 'master.employee.sections.basicdata'],
            function ($view) {
                // Guard: hindari error bila tabel belum ada (mis. saat migrate awal).
                $gradeOptions = Schema::hasTable('grades') ? Grade::options() : [];
                $view->with('gradeOptions', $gradeOptions)
                     ->with('homeBaseOptions', HomeBase::options());
            }
        );
    }
}
