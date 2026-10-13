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
     * Tambah konsultan baru (tombol "Add Consultant"): memakai logika pembuatan karyawan yang sama dengan Master Employee,
     * tetapi Employee Group dikunci EXTERNAL dan izinnya = Create pada `general.external.consultants` (diatur Admin).
     */
    public function store(Request $request)
    {
        $request->merge(['employee_group' => 'EXTERNAL', 'home_base' => $request->input('home_base') ?: 'Others']);

        return app(\App\Http\Controllers\EmployeeController::class)->store($request);
    }
}
