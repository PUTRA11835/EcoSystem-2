<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\ChecksExternalAccess;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Services\Legal\LegalBasisService;
use App\Services\Offboarding\OffboardingService;
use App\Support\Offboarding\OffboardingSettlementRules as R;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * HR & General → Offboarding (slug general.offboarding.list): daftar, detail dengan checklist dan Final Settlement, nonaktifkan akses, selesaikan, batalkan.
 *
 * V daftar & detail · C mulai Offboarding · E simpan checklist / hitung settlement / nonaktifkan akses / selesaikan · D batalkan.
 * Nominal Final Settlement hanya untuk pemegang `general.offboarding.settlement`. Semua aksi tulis mati bila saklar modul di Settings mati.
 */
class OffboardingController extends Controller
{
    use ChecksExternalAccess;
    use HandlesRecruitmentTables;

    public const SLUG = 'general.offboarding.list';

    public function __construct(private OffboardingService $offboarding, private LegalBasisService $legal) {}

    public function index(Request $request)
    {
        $filters = ['search' => trim((string) $request->query('search')), 'status' => (string) $request->query('status'), 'type' => (string) $request->query('type')];
        $perPage = $this->perPage($request);

        $data = [
            'rows' => $this->offboarding->listing($filters, $perPage), 'filters' => $filters, 'perPage' => $perPage, 'perPageOptions' => self::PER_PAGE_OPTIONS,
            'filterForm' => 'offboardingFilters', 'types' => R::TYPES,
        ];
        if ($request->ajax()) {
            return view('hr-general.offboarding.components.list-table', $data);
        }

        $settings = $this->offboarding->settings();

        return view('hr-general.offboarding.index', $data + [
            'summary' => $this->offboarding->summary(), 'settings' => $settings, 'enabled' => (bool) $settings->module_enabled,
            'canCreate' => $this->employeeCan(self::SLUG, 'create') && $settings->module_enabled,
            'employees' => $settings->module_enabled ? $this->offboarding->eligibleEmployees() : [],
            'typesByKind' => R::TYPES_BY_KIND, 'terminationReasons' => R::TERMINATION_REASONS,
            'canSettings' => $this->canView('general.offboarding.settings'),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'employee_id' => 'required|integer', 'offboarding_type' => ['required', Rule::in(array_keys(R::TYPES))], 'reason_code' => 'nullable|string|max:30',
            'effective_date' => 'required|date', 'last_working_day' => 'nullable|date', 'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $id = $this->offboarding->create($d, $this->actorId());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['offboarding' => $e->getMessage()]);
        }

        return redirect()->route('general.offboarding.show', $id)->with('success', 'Offboarding started. Work through the checklist below.');
    }

    public function show(int $id)
    {
        $o = $this->offboarding->find($id);
        abort_if($o === null, 404);

        $settings = $this->offboarding->settings();
        $amounts = $this->canView('general.offboarding.settlement');
        $used = $o->settlement && $o->settlement->legal_basis_ids
            ? DB::table('legal_basis_register')->whereIn('id', json_decode($o->settlement->legal_basis_ids, true) ?: [])->orderBy('rule_key')->get() : collect();
        $editable = $o->status === 'in_progress' && $settings->module_enabled && $this->employeeCan(self::SLUG, 'edit');

        return view('hr-general.offboarding.show', [
            'o' => $o, 'settings' => $settings, 'enabled' => (bool) $settings->module_enabled, 'amounts' => $amounts,
            'canEdit' => $editable, 'canCancel' => $o->status === 'in_progress' && $this->employeeCan(self::SLUG, 'delete'),
            'calculatorReady' => $this->offboarding->calculatorReady(), 'missingLegal' => $this->legal->notReviewed(LegalBasisService::OFFBOARDING_REQUIRED),
            'legalRows' => $used->isNotEmpty() ? $used->map(fn ($r) => $this->legal->decorate($r)) : $this->legal->summary('offboarding')['rows'], 'types' => R::TYPES, 'terminationReasons' => R::TERMINATION_REASONS,
            'canLegal' => $this->canView('finance.legal-basis'), 'canSettings' => $this->canView('general.offboarding.settings'),
            'suggestedWage' => $this->offboarding->wageBase($o->contract),
        ]);
    }

    public function saveTasks(Request $request, int $id)
    {
        try {
            $this->offboarding->saveTasks($id, (array) $request->input('tasks', []), $this->actorId());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['offboarding' => $e->getMessage()]);
        }

        return back()->with('success', 'Checklist saved.');
    }

    public function calculate(Request $request, int $id)
    {
        abort_unless($this->canView('general.offboarding.settlement'), 403, 'The final settlement needs the “View Settlement Amounts” permission.');
        $d = $request->validate([
            'wage_base_override' => 'nullable|string|max:30', 'leave_days' => 'nullable|string|max:10', 'retirement_benefit' => 'nullable|string|max:30',
            'separation_pay' => 'nullable|string|max:30', 'other_compensation' => 'nullable|string|max:30', 'legal_notes' => 'nullable|string|max:2000',
            'internal_notes' => 'nullable|string|max:2000', 'reason_code' => 'nullable|string|max:30', 'last_working_day' => 'nullable|date',
        ]);

        try {
            $this->offboarding->calculate($id, $d, $this->actorId());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['offboarding' => $e->getMessage()]);
        }

        return back()->with('success', 'Final settlement calculated and saved.');
    }

    public function deactivate(int $id)
    {
        return $this->step(fn () => $this->offboarding->deactivateAccess($id, $this->actorId()), 'Access deactivated: the person can no longer log in.');
    }

    public function complete(int $id)
    {
        return $this->step(fn () => $this->offboarding->complete($id, $this->actorId()), 'Offboarding completed. The running contract was closed.');
    }

    public function cancel(Request $request, int $id)
    {
        return $this->step(fn () => $this->offboarding->cancel($id, $this->actorId(), (string) $request->input('reason')), 'Offboarding cancelled.');
    }

    private function step(\Closure $act, string $message)
    {
        try {
            $act();
        } catch (\DomainException $e) {
            return back()->withErrors(['offboarding' => $e->getMessage()]);
        }

        return back()->with('success', $message);
    }
}
