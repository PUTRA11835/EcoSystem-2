<?php

namespace App\Providers;

use App\Enums\HomeBase;
use App\Enums\RoleId;
use App\Models\Grade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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
        // Scheme URL TIDAK lagi dipaksa ke https. Sistem harus kompatibel diakses
        // baik via http maupun https tanpa memunculkan error / Mixed Content.
        //
        // Caranya: biarkan Laravel mengikuti scheme request yang sebenarnya.
        // Di belakang reverse proxy / load balancer yang terminate SSL, scheme
        // asli (https) sudah terbaca otomatis lewat header X-Forwarded-Proto yang
        // di-trust di bootstrap/app.php (trustProxies). Jadi:
        //   - diakses via https  -> route()/url()/asset() menghasilkan https://
        //   - diakses via http   -> menghasilkan http://
        // Tidak ada lagi pemaksaan yang membuat link error saat dibuka via http.

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
