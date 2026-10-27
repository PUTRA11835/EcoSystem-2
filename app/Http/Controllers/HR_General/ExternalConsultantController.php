<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\ChecksExternalAccess;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Services\External\ExternalConsultantService;
use App\Services\HrProfile\EngagementRules;
use App\Support\External\ExternalRules;
use Illuminate\Http\Request;

/**
 * External → Consultants (slug general.external.consultants): daftar dan profil konsultan eksternal.
 *
 * HANYA MEMBACA. Konsultan = karyawan `External` di Master Employee + blok Engagement-nya; data diubah di sana
 * (ExternalConsultantService). Tarif butuh izin `external.section.engagement_rate.view`; tiap blok profil mengikuti izin seksinya
 * di Master Employee (identitas, alamat, rekening, lampiran).
 */
class ExternalConsultantController extends Controller
{
    use ChecksExternalAccess;
    use HandlesRecruitmentTables;

    public function __construct(private ExternalConsultantService $consultants) {}

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'scheme' => (string) $request->query('scheme'),
            'status' => (string) $request->query('status'),
        ];
        $perPage = $this->perPage($request);
        $canRate = $this->canView('external.section.engagement_rate.view');

        $data = [
            'rows'           => $this->consultants->listing($filters, $perPage, $canRate),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'canRate'        => $canRate,
            'filterForm'     => 'consultantFilters',
            'schemes'        => EngagementRules::SCHEMES + ['other_unset' => 'Other / not set', 'other' => 'Other scheme', 'unset' => 'No scheme set'],
            'periodLabels'   => ExternalRules::PERIOD_LABELS,
        ];

        // Penyaring langsung hanya menyegarkan tabel + halaman (components/list-table).
        if ($request->ajax()) {
            return view('hr-general.external.consultants.components.list-table', $data);
        }

        return view('hr-general.external.consultants.index', $data + ['summary' => $this->consultants->summary()]);
    }

    public function show(int $employeeId)
    {
        $canRate = $this->canView('external.section.engagement_rate.view');
        $consultant = $this->consultants->find($employeeId, $canRate);
        abort_if($consultant === null, 404);

        $profile = $this->consultants->profile($employeeId, [
            'identification' => $this->canView('external.section.identification.view'),
            'address'        => $this->canView('external.section.address.view'),
            'bank'           => $this->canView('external.section.bank.view'),
            'attachment'     => $this->canView('external.section.attachment.view'),
        ]);

        return view('hr-general.external.consultants.show', [
            'c'            => $consultant,
            'profile'      => $profile,
            'canRate'      => $canRate,
            'periodLabels' => ExternalRules::PERIOD_LABELS,
        ]);
    }

    /**
     * Urutan Employee ID (ECI) konsultan + cek ketersediaan, untuk formulir "Add consultant" (ECI diketik manual, tidak dibuat otomatis).
     * Pola yang berlaku: huruf E + 2 digit tahun + 3 digit urut (mis. E26060). Yang ditampilkan: usulan berikutnya, beberapa ID terakhir,
     * nomor yang masih kosong (celah), dan — bila `eci` dikirim — apakah ID itu sudah dipakai siapa pun (karyawan internal maupun konsultan,
     * juga nama pengguna akun) serta apakah Nick Name (`nick`) sudah dipakai. Hanya MEMBACA.
     */
    public function eciSequence(Request $request)
    {
        $prefix = 'E' . now()->format('y');
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d{3})$/i';

        $used = \Illuminate\Support\Facades\DB::table('employee')->where('eci', 'like', $prefix . '%')->pluck('eci')
            ->merge(\Illuminate\Support\Facades\DB::table('auth_users')->where('username', 'like', $prefix . '%')->pluck('username'))
            ->map(fn ($v) => strtoupper((string) $v))->unique();
        $numbers = $used->map(fn ($v) => preg_match($pattern, $v, $m) ? (int) $m[1] : null)->filter()->sort()->values();

        $max = (int) ($numbers->last() ?? 0);
        $gaps = [];
        for ($i = 1; $i < $max && count($gaps) < 8; $i++) {
            if (!$numbers->contains($i)) {
                $gaps[] = $prefix . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            }
        }
        $names = \Illuminate\Support\Facades\DB::table('employee as e')->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->whereIn('e.eci', $numbers->sortDesc()->take(6)->map(fn ($n) => $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT))->all())
            ->get(['e.eci', 'b.first_name', 'b.last_name'])->keyBy(fn ($r) => strtoupper($r->eci));
        $recent = $numbers->sortDesc()->take(6)->map(function ($n) use ($prefix, $names) {
            $eci = $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $r = $names->get($eci);

            return ['eci' => $eci, 'name' => $r ? trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) : ''];
        })->values()->all();

        $out = [
            'success' => true, 'prefix' => $prefix, 'format' => $prefix . '###',
            'next' => $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT), 'recent' => $recent, 'gaps' => $gaps,
        ];

        $eci = trim((string) $request->query('eci', ''));
        if ($eci !== '') {
            $holder = \Illuminate\Support\Facades\DB::table('employee as e')->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
                ->where('e.eci', $eci)->first(['e.eci', 'b.first_name', 'b.last_name']);
            $login = \Illuminate\Support\Facades\DB::table('auth_users')->where('username', $eci)->exists();
            $out['eci_taken'] = $holder !== null || $login;
            $out['eci_used_by'] = $holder ? trim(($holder->first_name ?? '') . ' ' . ($holder->last_name ?? '')) : null;
            $out['eci_format_ok'] = (bool) preg_match('/^E\d{5}$/i', $eci);
        }
        $nick = trim((string) $request->query('nick', ''));
        if ($nick !== '') {
            $out['nick_taken'] = \Illuminate\Support\Facades\DB::table('employee_basic_data')->where('nick_name', $nick)->exists();
        }

        return response()->json($out);
    }

    /** Token CSRF segar (JSON) — formulir Add consultant memintanya sekali bila token di halaman ditolak (419). */
    public function csrf(Request $request)
    {
        return response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store');
    }

    /**
     * Tambah konsultan baru (tombol "Add Consultant"): memakai logika pembuatan karyawan yang sama dengan Master Employee,
     * tetapi Employee Group dikunci EXTERNAL dan izinnya = Create pada `general.external.consultants` (diatur Admin).
     */
    public function store(Request $request)
    {
        $request->merge(['employee_group' => 'EXTERNAL', 'home_base' => $request->input('home_base') ?: 'Others']);

        return app(\App\Http\Controllers\EmployeeController::class)->store($request);
    }
}
