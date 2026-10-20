<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\ChecksExternalAccess;
use App\Services\External\ExternalConsultantService;
use App\Services\External\ExternalWorkflowService;
use App\Support\External\ExternalTimesheetRules as R;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * External → Timesheet & Payment → Settings (slug general.external.timesheet.settings): siapa menyetujui lembar jam dan memverifikasi
 * invoice — per tahap (role atau orang tertentu) dan pengecualian per konsultan — serta beberapa opsi.
 *
 * V lihat · C tambah langkah / pengecualian · E ubah langkah, pengecualian, opsi · D hapus. Hak diatur Admin di Menu Access.
 * Perubahan berlaku untuk lembar yang DIAJUKAN sesudahnya; lembar yang sedang berjalan memakai penyetuju yang dibekukan saat Submit.
 */
class ExternalWorkflowSettingsController extends Controller
{
    use ChecksExternalAccess;

    public function __construct(private ExternalWorkflowService $workflow, private ExternalConsultantService $consultants) {}

    public function index()
    {
        $options = $this->workflow->options();

        return view('hr-general.external.timesheet.settings', [
            'settings'    => $this->workflow->settings(),
            'steps'       => collect(R::STAGES)->map(fn ($label, $stage) => $this->workflow->steps($stage))->all(),
            'overrides'   => $this->workflow->overrides(),
            'roles'       => $options['roles'],
            'people'      => $options['people'],
            'consultants' => $this->consultants->options(),
            'stages'      => R::STAGES,
            'tabTimesheets' => $this->canView(ExternalTimesheetController::SLUG),
            'tabInvoices'   => $this->canView(ExternalTimesheetController::SLUG_INVOICES),
            'tabSettings'   => true,
        ]);
    }

    public function storeStep(Request $request)
    {
        return $this->saveStep($request, null);
    }

    public function updateStep(Request $request, int $stepId)
    {
        return $this->saveStep($request, $stepId);
    }

    public function destroyStep(int $stepId)
    {
        try {
            $this->workflow->deleteStep($stepId);
        } catch (\DomainException $e) {
            return back()->withErrors(['settings' => $e->getMessage()]);
        }

        return back()->with('success', 'Step deleted.');
    }

    public function storeOverride(Request $request)
    {
        $d = $request->validate([
            'employee_id' => 'required|integer', 'stage' => ['required', Rule::in(array_keys(R::STAGES))],
            'approver_employee_ids' => 'required|array|min:1', 'approver_employee_ids.*' => 'integer',
        ], ['approver_employee_ids.required' => 'Pick at least one approver.']);

        // Mengubah pengecualian yang sudah ada = Edit; menambah yang baru = Create (rute hanya menjaga Create, sisanya diperiksa di sini).
        $exists = \Illuminate\Support\Facades\DB::table('external_approver_overrides')->where('employee_id', $d['employee_id'])->where('stage', $d['stage'])->exists();
        abort_if($exists && !$this->canEdit(), 403, 'Changing an exception needs the Edit permission.');

        try {
            $this->workflow->saveOverride((int) $d['employee_id'], $d['stage'], $d['approver_employee_ids'], $this->actorId());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['settings' => $e->getMessage()]);
        }

        return back()->with('success', 'Exception saved.');
    }

    public function destroyOverride(int $overrideId)
    {
        $this->workflow->deleteOverride($overrideId);

        return back()->with('success', 'Exception deleted.');
    }

    public function saveOptions(Request $request)
    {
        $d = $request->validate(['backfill_days' => 'required|integer|min:0|max:365']);
        $this->workflow->saveOptions($request->boolean('allow_self_approval'), (int) $d['backfill_days']);

        return back()->with('success', 'Options saved.');
    }

    private function saveStep(Request $request, ?int $id)
    {
        $d = $request->validate([
            'stage' => ['required', Rule::in(array_keys(R::STAGES))], 'name' => 'required|string|max:120',
            'approver_type' => ['required', Rule::in(['role', 'employee'])],
            'role_ids' => 'nullable|array', 'role_ids.*' => 'integer', 'employee_ids' => 'nullable|array', 'employee_ids.*' => 'integer',
        ]);

        try {
            $this->workflow->saveStep($id, $d['stage'], $d['name'], $d['approver_type'], $d['role_ids'] ?? [], $d['employee_ids'] ?? []);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['settings' => $e->getMessage()]);
        }

        return back()->with('success', $id ? 'Step saved.' : 'Step added.');
    }

    private function canEdit(): bool
    {
        $employee = \App\Models\Employee::find($this->actorId());

        return $employee !== null && $employee->hasMenuPermission('general.external.timesheet.settings', 'can_edit');
    }
}
