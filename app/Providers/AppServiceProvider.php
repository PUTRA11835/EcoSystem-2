<?php

namespace App\Providers;

use App\Enums\Division;
use App\Enums\EmployeeGroup;
use App\Enums\EmployeeSubgroup;
use App\Enums\HomeBase;
use App\Enums\PersonnelArea;
use App\Enums\PersonnelSubarea;
use App\Enums\RoleId;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Grade;
use App\Models\Position;
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

        $this->app->singleton(\Anthropic\Client::class, function () {
            // anthropic-ai/sdk's own `timeout` RequestOption is advisory-only — it is
            // never read by the SDK, enforcement is entirely up to whatever PSR-18
            // client gets auto-discovered (see vendor/anthropic-ai/sdk/src/RequestOptions.php).
            // That auto-discovery resolves to a bare `new GuzzleHttp\Client()`, whose
            // default `timeout`/`connect_timeout` are both 0 (wait forever). A slow or
            // stalled Anthropic response (e.g. the Agent Skills code-execution container
            // path used by AiTicketAnalyzerService) then hangs the PHP process/worker
            // indefinitely instead of failing. Pass an explicit Guzzle transporter with
            // real bounds so a stuck call fails with a catchable exception instead.
            // 600s matches the SDK's own documented "intended default" (see the
            // RequestOptions docblock above) — restoring that intent rather than
            // inventing a shorter arbitrary bound, so existing long-running streaming
            // turns (AiChatService/AiResearchService tool loops) keep the headroom
            // they always assumed they had.
            return new \Anthropic\Client(
                apiKey: config('services.anthropic.api_key'),
                requestOptions: [
                    'transporter' => new \GuzzleHttp\Client([
                        'connect_timeout' => 15,
                        'timeout' => 600,
                    ]),
                ],
            );
        });

        // Sama alasannya dengan binding Anthropic\Client di atas: openai-php/client
        // mengandalkan PSR-18 HTTP Client Discovery bila tidak diberi httpClient
        // eksplisit, yang berujung ke `new GuzzleHttp\Client()` tanpa batas waktu.
        // Server tool web_search & giliran streaming panjang butuh timeout eksplisit
        // supaya panggilan yang macet gagal dengan exception yang bisa ditangkap,
        // bukan menggantung worker PHP selamanya.
        $this->app->singleton(\OpenAI\Client::class, function () {
            return \OpenAI::factory()
                ->withApiKey((string) config('services.openai.api_key'))
                ->withHttpClient(new \GuzzleHttp\Client([
                    'connect_timeout' => 15,
                    'timeout' => 600,
                ]))
                ->make();
        });
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

        // Suntik opsi Home Base, Position, Department & 4 enum organisasi ke form
        // employee (modal index + section basicdata yang dipakai detail & profile).
        // Satu sumber per field — view tidak lagi hardcode daftarnya.
        // Catatan: Grade TIDAK lagi dipakai di Basic Data — konsepnya pindah ke
        // "Level" pada Employee Qualification (lihat composer di bawah).
        View::composer(
            ['master.employee.index', 'master.employee.sections.basicdata'],
            function ($view) {
                // Guard: hindari error bila tabel belum ada (mis. saat migrate awal).
                $positionOptions   = Schema::hasTable('positions') ? Position::options() : [];
                $departmentOptions = Schema::hasTable('departments') ? Department::options() : [];

                // "Current Assignment" — dropdown-nya diambil dari daftar Business
                // Partner bertipe Customer (bukan free text lagi), sama sumbernya
                // dengan dropdown customer di form Create Ticket.
                $customerOptions = Schema::hasTable('customer')
                    ? Customer::with('basicData')
                        ->customers()
                        ->where('is_active', true)
                        ->get()
                        ->map(fn ($c) => $c->basicData->name_1 ?? $c->email ?? null)
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values()
                    : collect();

                $view->with('homeBaseOptions', HomeBase::options())
                     ->with('positionOptions', $positionOptions)
                     ->with('departmentOptions', $departmentOptions)
                     ->with('divisionOptions', Division::options())
                     ->with('personnelAreaOptions', PersonnelArea::options())
                     ->with('personnelSubareaOptions', PersonnelSubarea::options())
                     ->with('employeeGroupOptions', EmployeeGroup::options())
                     ->with('employeeSubgroupOptions', EmployeeSubgroup::options())
                     ->with('customerOptions', $customerOptions);
            }
        );

        // Suntik opsi "Level" ke form Employee Qualification (tipe Certification) —
        // dari tabel `grades` yang sama dengan Home Base/Grade lama, tapi nama
        // di-strip suffix " Consultant" (Grade::levelOptions()).
        View::composer(
            'master.employee.sections.qualification',
            function ($view) {
                $levelOptions = Schema::hasTable('grades') ? Grade::levelOptions() : [];
                $view->with('qualificationLevelOptions', $levelOptions);
            }
        );
    }
}
