<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\ChecksExternalAccess;
use App\Services\Legal\LegalBasisService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Business & Legal → Legal Basis (slug finance.legal-basis, URL /legal/basis): Register Dasar Hukum — peraturan di balik tiap aturan perhitungan (PPh 21, BPJS, lembur,
 * PKWT, Offboarding): nomor, pasal, berlaku sejak, TERAKHIR DIUBAH, sumber, status ulasan, dan tabel tarif untuk kalkulator.
 *
 * V lihat · C tambah aturan / versi baru · E ubah, tandai Reviewed / Needs review · D tandai Superseded.
 * Mengubah isi sebuah versi menurunkannya ke "Needs review" (ulasan berlaku untuk isi yang diulas).
 * TIDAK mengubah rumus atau angka Payroll — Payroll tetap membaca tabel Settings-nya sendiri; register ini mendokumentasikan dasar hukumnya.
 */
class LegalBasisController extends Controller
{
    use ChecksExternalAccess;

    public function __construct(private LegalBasisService $legal) {}

    /** Legal → Legal Basis tab (the Dashboard tab is LegalDashboardController). */
    public function index(Request $request)
    {
        return view('legal.basis', ['basis' => $this->legal->registerPage((string) $request->query('module'))]);
    }

    /** Tab Settings: saklar tampil / Draft mode / Enforce per modul (izin sendiri: finance.legal-basis.settings). */
    public function settings()
    {
        return view('finance.legal-basis.settings', [
            'modules' => LegalBasisService::MODULES, 'switches' => $this->legal->allSwitches(),
            'pending' => collect(array_keys(LegalBasisService::MODULES))->mapWithKeys(fn ($m) => [$m => $this->legal->pending($m)]),
        ]);
    }

    public function store(Request $request)
    {
        return $this->save($request, null);
    }

    public function update(Request $request, int $id)
    {
        return $this->save($request, $id);
    }

    /** Saklar penerapan per modul (panel, peringatan, enforce). */
    public function saveSwitches(Request $request)
    {
        $this->legal->saveSwitches((array) $request->input('sw', []), $this->actorId());
        $this->legal->setDisplay($request->boolean('legal_display'));

        return redirect()->route('finance.legal-basis.settings')->with('success', 'Legal basis settings saved. They take effect immediately.');
    }

    public function review(int $id)
    {
        return $this->act(fn () => $this->legal->markReviewed($id, $this->actorId()), 'Marked as reviewed.');
    }

    /** Accept review untuk semua aturan satu modul sekaligus (setelah Legal/pihak berwenang selesai memeriksa). */
    public function reviewModule(string $module)
    {
        try {
            $n = $this->legal->markModuleReviewed($module, $this->actorId());
        } catch (\DomainException $e) {
            return back()->withErrors(['legal' => $e->getMessage()]);
        }

        return back()->with('success', $n ? "{$n} rule(s) accepted as reviewed." : 'Nothing to accept: every rule of this module is already reviewed.');
    }

    /** Kembalikan versi Superseded ke Needs review. */
    public function restore(int $id)
    {
        return $this->act(fn () => $this->legal->restore($id), 'Restored. It is back to “Needs review” and is used again by calculators.');
    }

    public function needsReview(int $id)
    {
        return $this->act(fn () => $this->legal->markNeedsReview($id), 'Marked as needs review.');
    }

    public function supersede(int $id)
    {
        return $this->act(fn () => $this->legal->supersede($id), 'Marked as superseded. It is no longer used for calculations.');
    }

    private function save(Request $request, ?int $id)
    {
        $d = $request->validate([
            'rule_key' => 'required|string|max:60|regex:/^[a-z0-9_.]+$/', 'module' => ['required', Rule::in(array_keys(LegalBasisService::MODULES))],
            'regulation_type' => ['required', Rule::in(array_keys(LegalBasisService::TYPES))], 'number' => 'required|string|max:40', 'year' => 'required|integer|min:1945|max:2100',
            'title' => 'required|string|max:400', 'article' => 'nullable|string|max:400', 'effective_date' => 'required|date', 'last_amended_date' => 'nullable|date',
            'amended_by' => 'nullable|string|max:400', 'source_url' => 'nullable|url|max:500', 'confidence' => ['required', Rule::in(array_keys(LegalBasisService::CONFIDENCE))],
            'payload' => 'nullable|string|max:20000', 'notes' => 'nullable|string|max:3000',
        ]);

        try {
            $this->legal->save($id, $d, $this->actorId());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['legal' => $e->getMessage()]);
        }

        return back()->with('success', $id ? 'Saved. The entry is back to “Needs review” until someone reviews the change.' : 'Added as “Needs review”.');
    }

    private function act(\Closure $do, string $message)
    {
        try {
            $do();
        } catch (\DomainException $e) {
            return back()->withErrors(['legal' => $e->getMessage()]);
        }

        return back()->with('success', $message);
    }
}
