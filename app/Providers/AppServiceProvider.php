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
        // Scheme URL dipaksa ke https HANYA bila APP_URL memang https (produksi).
        //
        // Mengandalkan trustProxies + header X-Forwarded-Proto saja tidak cukup:
        // sebagian hosting/reverse proxy TIDAK meneruskan header tsb ke PHP,
        // sehingga Laravel melihat request sebagai HTTP dan route()/url()/asset()
        // menghasilkan http:// -> browser memblokirnya sebagai Mixed Content pada
        // halaman HTTPS (mis. action <form> = http:// -> "form not secure").
        //
        // forceScheme('https') memaksa langsung tanpa bergantung pada header proxy.
        // Digate ke APP_URL agar lokal (http://localhost) TIDAK terpengaruh:
        //   - APP_URL https:// (produksi) -> semua URL absolut jadi https ✅
        //   - APP_URL http://  (lokal)    -> tetap http, kompatibel ✅
        if (str_starts_with((string) config('app.url'), 'https://')) {
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
