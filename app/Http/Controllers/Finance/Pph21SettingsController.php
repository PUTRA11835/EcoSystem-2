<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Payroll\Pph21RateRepository;
use App\Services\Payroll\Pph21SettingsService;
use App\Support\Payroll\Money;
use App\Support\Payroll\PtkpRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/**
 * Finance → PPh 21 → Settings: PTKP, lapisan Pasal 17, dan TER per tahun pajak.
 *
 * Izin: `menu:finance.pph21.settings` (lihat) dan `menu.can:…,create|edit` di rute. Hanya GET/POST (verb DELETE
 * diblokir edge produksi). Tahun tidak dapat dihapus — hanya disalin dari tahun lain lalu dikoreksi.
 */
class Pph21SettingsController extends Controller
{
    public function __construct(
        private readonly Pph21RateRepository $rates,
        private readonly Pph21SettingsService $service,
    ) {
    }

    /** Pintu masuk sidebar: tab pertama yang boleh dibuka (Settings → Report). */
    public function home(): RedirectResponse
    {
        $me = Employee::find(session('user.id'));
        foreach (['settings' => 'finance.pph21.settings', 'report' => 'finance.pph21.report'] as $route => $slug) {
            if ($me?->canAccessMenu($slug)) {
                return redirect()->route('finance.pph21.' . $route);
            }
        }

        return redirect()->route('dashboard');
    }

    public function index(Request $request): View
    {
        $years = $this->rates->years();
        $year  = (int) $request->query('year', $years[0] ?? (int) now()->year);
        if ($years && !in_array($year, $years, true)) {
            $year = $years[0];
        }

        $tab = in_array($request->query('tab'), ['ptkp', 'progressive', 'ter'], true) ? $request->query('tab') : 'ptkp';
        $cat = in_array($request->query('cat'), ['A', 'B', 'C'], true) ? $request->query('cat') : 'A';

        $ready = $years && in_array($year, $years, true);
        $me    = Employee::find(session('user.id'));

        return view('finance.pph21.settings', [
            'years'       => $years,
            'year'        => $year,
            'ready'       => $ready,
            'tab'         => $tab,
            'cat'         => $cat,
            'ptkp'        => $ready ? $this->rates->ptkp($year) : [],
            'ptkpLabels'  => $ready ? DB::table('pph21_ptkp')->where('year', $year)->pluck('description', 'code')->all() : [],
            'brackets'    => $ready ? $this->rates->brackets($year) : [],
            'ter'         => $ready ? $this->rates->ter($year) : [],
            'settings'    => $ready ? $this->rates->settings($year) : null,
            'ptkpCodes'   => PtkpRules::CODES,
            'locked'      => $ready && $this->service->isYearLocked($year),
            // Kotak C/E di Menu Access: tombol disembunyikan bila tak berhak (server tetap menegakkan lewat menu.can).
            'canCreate'   => (bool) $me?->hasMenuPermission('finance.pph21.settings', 'can_create'),
            'canEdit'     => (bool) $me?->hasMenuPermission('finance.pph21.settings', 'can_edit'),
        ]);
    }

    public function savePtkp(Request $request, int $year): RedirectResponse
    {
        $errors = $this->service->savePtkp($year, array_map(fn ($v) => Money::parse($v) ?? $v, (array) $request->input('ptkp', [])), $this->actorId());

        return $this->back($year, 'ptkp', null, $errors, 'PTKP saved.');
    }

    public function saveBrackets(Request $request, int $year): RedirectResponse
    {
        $errors = $this->service->saveBrackets($year, $this->rows($request), $this->actorId());

        return $this->back($year, 'progressive', null, $errors, 'Progressive rates saved.');
    }

    public function saveTer(Request $request, int $year, string $category): RedirectResponse
    {
        $errors = $this->service->saveTer($year, $category, $this->rows($request), $this->actorId());

        return $this->back($year, 'ter', $category, $errors, "TER category {$category} saved.");
    }

    public function saveSettings(Request $request, int $year): RedirectResponse
    {
        $errors = $this->service->saveSettings($year, [
            'occupational_cost_rate'        => $request->input('occupational_cost_rate'),
            'occupational_cost_monthly_max' => Money::parse($request->input('occupational_cost_monthly_max')) ?? $request->input('occupational_cost_monthly_max'),
            'include_employer_premiums'     => $request->boolean('include_employer_premiums'),
            'notes'                         => $request->input('notes'),
        ], $this->actorId());

        return $this->back($year, 'ptkp', null, $errors, 'Tax settings saved.');
    }

    public function createYear(Request $request): RedirectResponse
    {
        $new    = (int) $request->input('new_year');
        $source = (int) $request->input('source_year');
        $errors = $this->service->createYearFrom($new, $source, $this->actorId());

        return $errors
            ? redirect()->route('finance.pph21.settings', ['year' => $source])->withErrors($errors)->withInput()
            : redirect()->route('finance.pph21.settings', ['year' => $new])
                ->with('success', "Tax year {$new} created from {$source}. Review every table before using it.");
    }

    /** @param string[] $errors */
    private function back(int $year, string $tab, ?string $cat, array $errors, string $ok): RedirectResponse
    {
        $to = redirect()->route('finance.pph21.settings', array_filter(['year' => $year, 'tab' => $tab, 'cat' => $cat]));

        return $errors ? $to->withErrors($errors)->withInput() : $to->with('success', $ok);
    }

    /**
     * Baris tabel dari formulir; batas atas diterima dalam format uang Indonesia (1.000.000,00).
     * Nilai yang bukan angka dibiarkan apa adanya agar validasi menolaknya dengan pesan jelas.
     *
     * @return array<int,array{upper_limit:mixed,rate:mixed}>
     */
    private function rows(Request $request): array
    {
        return array_map(function ($r) {
            $u = $r['upper_limit'] ?? null;

            return [
                'upper_limit' => ($u === null || $u === '') ? null : (Money::parse($u) ?? $u),
                'rate'        => $r['rate'] ?? null,
            ];
        }, array_values((array) $request->input('rows', [])));
    }

    private function actorId(): ?int
    {
        $id = session('user.id');

        return $id ? (int) $id : null;
    }
}
