<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\ChecksExternalAccess;
use App\Services\Legal\LegalBasisService;
use App\Services\Offboarding\OffboardingService;
use Illuminate\Http\Request;

/**
 * Offboarding → Settings (slug general.offboarding.settings; C tambah tugas · E ubah saklar/tugas · D hapus tugas): saklar modul, kalkulator,
 * penonaktifan otomatis, sertakan External, pengingat, pembagi cuti, dan templat tugas. Kalkulator hanya dapat dinyalakan bila seluruh aturan
 * hukum yang dibutuhkan (Register Dasar Hukum) sudah berstatus Reviewed.
 */
class OffboardingSettingsController extends Controller
{
    use ChecksExternalAccess;

    public function __construct(private OffboardingService $offboarding, private LegalBasisService $legal) {}

    public function index()
    {
        return view('hr-general.offboarding.settings', [
            'settings' => $this->offboarding->settings(), 'templates' => $this->offboarding->templates(),
            'required' => LegalBasisService::OFFBOARDING_REQUIRED, 'missingLegal' => $this->legal->notReviewed(LegalBasisService::OFFBOARDING_REQUIRED),
            'legalRows' => $this->legal->summary('offboarding')['rows'], 'canLegal' => $this->canView('finance.legal-basis'),
            'canList' => $this->canView(OffboardingController::SLUG),
        ]);
    }

    public function save(Request $request)
    {
        $d = $request->validate(['reminder_days' => 'required|integer|min:0|max:30', 'leave_divisor' => 'required|numeric|min:1|max:31']);
        $warnings = $this->offboarding->saveSettings([
            'module_enabled' => $request->boolean('module_enabled'), 'calculator_enabled' => $request->boolean('calculator_enabled'),
            'auto_deactivate' => $request->boolean('auto_deactivate'), 'include_external' => $request->boolean('include_external'),
            'require_tasks_before_deactivate' => $request->boolean('require_tasks_before_deactivate'), 'allow_force_deactivate' => $request->boolean('allow_force_deactivate'),
            'reminder_days' => (int) $d['reminder_days'], 'leave_divisor' => (float) $d['leave_divisor'],
        ]);

        $back = back()->with('success', 'Settings saved.');

        return $warnings ? $back->with('warning', implode(' ', $warnings)) : $back;
    }

    public function storeTemplate(Request $request)
    {
        return $this->template($request, null);
    }

    public function updateTemplate(Request $request, int $templateId)
    {
        return $this->template($request, $templateId);
    }

    public function destroyTemplate(int $templateId)
    {
        try {
            $this->offboarding->deleteTemplate($templateId);
        } catch (\DomainException $e) {
            return back()->withErrors(['settings' => $e->getMessage()]);
        }

        return back()->with('success', 'Task deleted. Offboardings already started keep their tasks.');
    }

    private function template(Request $request, ?int $id)
    {
        $d = $request->validate(['name' => 'required|string|max:150', 'description' => 'nullable|string|max:500', 'due_offset_days' => 'nullable|integer|min:-365|max:365', 'applies_to' => 'nullable|in:internal,external,both']);

        try {
            $this->offboarding->saveTemplate($id, $d + ['is_active' => $request->boolean('is_active')]);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['settings' => $e->getMessage()]);
        }

        return back()->with('success', $id ? 'Task saved.' : 'Task added. It applies to offboardings started from now on.');
    }
}
