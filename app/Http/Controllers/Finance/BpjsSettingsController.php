<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Payroll\BpjsSettingsService;
use App\Support\Payroll\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Finance → BPJS → Settings: tarif, batas upah, dan tanggal berlaku (append-only), simulasi dasar upah, riwayat.
 *
 * Izin: `menu:finance.bpjs.settings` (lihat) dan `menu.can:…,create` (membuat versi baru) di rute. Versi lama
 * tidak dapat diubah/dihapus dari layar ini — itu disengaja (lihat BpjsSettingsService).
 */
class BpjsSettingsController extends Controller
{
    public function __construct(private readonly BpjsSettingsService $service)
    {
    }

    /** Pintu masuk sidebar: tab pertama yang boleh dibuka (Settings → Report → Letters). */
    public function home(): RedirectResponse
    {
        $me = Employee::find(session('user.id'));
        foreach (['settings' => 'finance.bpjs.settings', 'report' => 'finance.bpjs.report', 'letters' => 'finance.bpjs.letters'] as $route => $slug) {
            if ($me?->canAccessMenu($slug)) {
                return redirect()->route('finance.bpjs.' . $route);
            }
        }

        return redirect()->route('dashboard');
    }

    public function index(): View
    {
        $history = $this->service->all();
        $today   = now()->toDateString();

        $active = null;
        foreach ($history as $h) {                       // terbaru dulu → yang pertama yang tidak melewati hari ini
            if ($h['effective_date'] <= $today) { $active = $h; break; }
        }
        $me = Employee::find(session('user.id'));

        return view('finance.bpjs.settings', [
            'history'   => $history,
            'active'    => $active,
            // Formulir versi baru diisi dari versi yang sedang berlaku (atau terbaru bila belum ada yang berlaku).
            'template'  => $active ?? ($history[0] ?? null),
            'today'     => $today,
            'canCreate' => (bool) $me?->hasMenuPermission('finance.bpjs.settings', 'can_create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $errors = $this->service->create($request->only([
            'effective_date', 'notes',
            'health_employer_rate', 'health_employee_rate', 'health_min_base', 'health_cap',
            'jht_employer_rate', 'jht_employee_rate', 'jp_employer_rate', 'jp_employee_rate', 'jp_cap',
            'jkk_rate', 'jkm_rate',
        ]) + [
            'health_in_payroll'     => $request->boolean('health_in_payroll'),
            'employment_in_payroll' => $request->boolean('employment_in_payroll'),
        ], session('user.id') ? (int) session('user.id') : null);

        return $errors
            ? redirect()->route('finance.bpjs.settings')->withErrors($errors)->withInput()
            : redirect()->route('finance.bpjs.settings')->with('success', 'New BPJS setting saved. Earlier versions were left unchanged.');
    }

    /** Simulasi (GET, JSON): tidak menyimpan apa pun. */
    public function simulate(Request $request): JsonResponse
    {
        $wage = Money::parse($request->query('wage'));
        if ($wage === null || $wage < 0 || $wage > 9_999_999_999_999) {
            return response()->json(['success' => false, 'message' => 'Enter a valid wage amount.'], 422);
        }
        $date = (string) $request->query('date', '');
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['success' => false, 'message' => 'Enter a valid date.'], 422);
        }

        try {
            $out = $this->service->simulate(
                $wage, $date ?: null,
                $request->boolean('health', true), $request->boolean('employment', true),
                max(0, min(5, (int) $request->query('dependents', 0)))
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true] + $out);
    }
}
