<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\CashAdvance\CashAdvanceApprovalStep;
use App\Models\Employee;
use App\Models\EmployeeRole;
use App\Models\Overtime\OvertimeApprovalStep;
use App\Models\PurchaseRequest\PurchaseRequestApprovalStep;
use App\Models\Reimbursement\ReimbursementApprovalStep;
use App\Services\CashAdvance\CashAdvanceReportService;
use App\Services\CashAdvance\CashAdvanceService;
use App\Services\PurchaseRequest\PurchaseRequestService;
use App\Services\Reimbursement\ReimbursementService;
use Illuminate\Support\Facades\DB;

/**
 * Hub "Approval Workflow" — satu halaman bertab untuk lima alur persetujuan
 * (Keputusan D180): Overtime, Reimbursement, Purchase Request, Cash Advance,
 * Cash Advance Report.
 *
 * 🔴 KENAPA CONTROLLER TERPISAH, BUKAN METHOD TAMBAHAN DI *SettingController
 * YANG SUDAH ADA. Masing-masing `*SettingController` mengurus SATU modul;
 * controller ini mengurus TAMPILAN GABUNGAN lintas modul. Memisahnya membuat
 * keduanya tetap mudah dibaca sebagai satu kesatuan — alasan yang sama dengan
 * mengapa hub Attendance (D175) tidak menggabungkan BranchController dan
 * ShiftController jadi satu controller raksasa.
 *
 * Data yang diambil di sini PERSIS SAMA dengan yang dulu diambil
 * `*SettingController::edit()` untuk BAGIAN 1 (Alur Persetujuan) sebelum
 * bagian itu dipindah keluar — dipindah, bukan ditulis ulang, supaya
 * perilakunya tidak melenceng dari sebelumnya.
 *
 * Rute POST (simpan/ubah/hapus/pindah langkah) TETAP di *SettingController
 * masing-masing — TIDAK dipindah ke sini. Controller ini HANYA menyediakan
 * lima halaman GET; form-form di dalam view-nya menunjuk ke rute yang sama
 * seperti sebelumnya (lihat routes/hr-general.php, blok yang dikomentari D180).
 */
class ApprovalWorkflowController extends Controller
{
    /** Karyawan aktif, kolom minimal — dipakai kelima tab. */
    private function activeEmployees()
    {
        return Employee::with('basicData:basic_data_id,employee_id,nick_name,department')
            ->where('is_active', 1)
            ->get(['employee_id', 'eci'])
            ->sortBy(fn ($e) => $e->basicData?->nick_name ?? $e->eci)
            ->values();
    }

    /** Daftar posisi — dipakai kelima tab. */
    private function roles()
    {
        return EmployeeRole::orderBy('name')->get(['id', 'name']);
    }

    public function overtime()
    {
        return view('hr-general.approval-workflow.overtime', [
            'steps' => OvertimeApprovalStep::forOvertime()
                ->with('role')
                ->orderBy('order_seq')
                ->get(),
            'roles'     => $this->roles(),
            'employees' => $this->activeEmployees(),
        ]);
    }

    public function reimbursement(ReimbursementService $reimbursement)
    {
        $steps = ReimbursementApprovalStep::forReimbursement()
            ->with('role')
            ->orderBy('order_seq')
            ->get();

        return view('hr-general.approval-workflow.reimbursement', [
            'steps' => $steps,

            // Berapa dokumen berjalan yang akan terkena bila langkah baru
            // ditambahkan. Angkanya disebut SEBELUM tombolnya ditekan.
            'openCount' => $reimbursement->countOpenRequestsBefore(
                (int) $steps->max('order_seq') + 1
            ),
            'roles'     => $this->roles(),
            'employees' => $this->activeEmployees(),
        ]);
    }

    public function purchaseRequest(PurchaseRequestService $purchaseRequest)
    {
        $steps = PurchaseRequestApprovalStep::forPurchaseRequest()
            ->with('role')
            ->orderBy('order_seq')
            ->get();

        return view('hr-general.approval-workflow.purchase-request', [
            'steps' => $steps,

            // Berapa dokumen berjalan yang akan terkena bila langkah baru
            // ditambahkan. Angkanya disebut SEBELUM tombolnya ditekan (D116).
            'openCount' => $purchaseRequest->countOpenRequestsBefore(
                (int) $steps->max('order_seq') + 1
            ),
            'roles'     => $this->roles(),
            'employees' => $this->activeEmployees(),
        ]);
    }

    public function cashAdvance(CashAdvanceService $ca)
    {
        $steps = CashAdvanceApprovalStep::query()
            ->forModule(CashAdvanceApprovalStep::MODULE_CA)
            ->with('role')
            ->orderBy('order_seq')
            ->get();

        return view('hr-general.approval-workflow.cash-advance', [
            'steps'     => $steps,
            'openCount' => $ca->countOpenRequestsBefore((int) $steps->max('order_seq') + 1),
            'roles'     => $this->roles(),
            'employees' => $this->activeEmployees(),
            'roleCandidates' => $this->roleCandidates(),
        ]);
    }

    public function cashAdvanceReport(CashAdvanceReportService $car)
    {
        $steps = CashAdvanceApprovalStep::query()
            ->forModule(CashAdvanceApprovalStep::MODULE_CAR)
            ->with('role')
            ->orderBy('order_seq')
            ->get();

        return view('hr-general.approval-workflow.cash-advance-report', [
            'steps'     => $steps,
            'openCount' => $car->countOpenReportsBefore((int) $steps->max('order_seq') + 1),
            'roles'     => $this->roles(),
            'employees' => $this->activeEmployees(),
            'roleCandidates' => $this->roleCandidates(),
        ]);
    }

    /**
     * 🔴 Berapa karyawan yang MEMEGANG tiap posisi — dipakai kolom Reference
     * pada editor Cash Advance/Cash Advance Report. Tanpa angka ini, memilih
     * posisi adalah tebakan buta: layar tidak memberi tahu apakah posisi itu
     * punya pemegang sama sekali, dan langkah tanpa kandidat melahirkan
     * dokumen yang menunggu orang yang tidak ada.
     *
     * Dihitung SATU kueri berkelompok, bukan per posisi. Definisinya sengaja
     * sama dengan scope withRole(): lewat tabel pivot
     * employee_role_assignment, tanpa menyaring is_active, supaya angka yang
     * DITAMPILKAN sama persis dengan kandidat yang nanti BENAR-BENAR dipakai
     * CashAdvanceApprovalStep::candidateEmployeeIds().
     */
    private function roleCandidates()
    {
        return DB::table('employee_role_assignment')
            ->select('role_id', DB::raw('COUNT(DISTINCT employee_id) AS total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id');
    }
}
