<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Reimbursement\ReimbursementRequest;
use App\Models\Reimbursement\ReimbursementSetting;
use App\Services\Reimbursement\ReimbursementService;
use Illuminate\Http\Request;

/**
 * Pengajuan reimbursement mandiri (ESS).
 *
 * 🔴 `employee_id` SELALU diambil dari sesi, tidak pernah dari badan request.
 * Menyisipkan employee_id orang lain lewat DevTools tidak berpengaruh apa pun.
 *
 * 🔴 KEPEMILIKAN diperiksa pada `show` dan `print`, bukan hanya lewat slug menu.
 * Slug hanya menjawab "boleh membuka halaman ini?" — ia tidak tahu dokumen SIAPA
 * yang sedang dibuka. Tanpa pemeriksaan ini, siapa pun yang boleh mengajukan
 * dapat membaca dokumen keuangan rekannya hanya dengan menebak id-nya.
 */
class MyReimbursementController extends Controller
{
    public function index(ReimbursementService $reimbursement)
    {
        $employeeId = (int) session('user.id');
        $now        = now();

        return view('HR_General.reimbursement.my_reimbursement', [
            'requests' => $reimbursement->history($employeeId),
            'summary'  => $reimbursement->monthlySummary($employeeId, (int) $now->format('Y'), (int) $now->format('n')),
            'settings' => ReimbursementSetting::current(),
            'month'    => $now->format('F Y'),
        ]);
    }

    public function create(ReimbursementService $reimbursement)
    {
        $settings = ReimbursementSetting::current();

        // Batas tanggal dihitung di sini, bukan di Blade, supaya atribut min/max
        // pada input tanggal selalu sejalan dengan aturan yang ditegakkan service
        // — pengguna tidak menemui penolakan setelah menekan kirim.
        $today = now()->startOfDay();

        return view('HR_General.reimbursement.submit', [
            'settings' => $settings,
            'branches' => $reimbursement->branchOptions(),
            'steps'    => $reimbursement->activeSteps(),
            'minDate'  => $settings->hasBackdateLimit()
                ? $today->copy()->subDays($settings->max_backdate_days)->toDateString()
                : null,
            'maxDate'  => $settings->allow_future_date ? null : $today->toDateString(),
        ]);
    }

    public function store(Request $request, ReimbursementService $reimbursement)
    {
        $employeeId = (int) session('user.id');
        $data       = $this->validatePayload($request, $reimbursement);

        $result = $reimbursement->submit($employeeId, $data);

        if (!$result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.my-reimbursement.index')
            ->with('success', 'Reimbursement ' . $result['request']->request_no
                . ' submitted and is waiting for review.');
    }

    public function show(ReimbursementRequest $reimbursementRequest, ReimbursementService $reimbursement)
    {
        $this->authoriseOwner($reimbursementRequest);

        $reimbursementRequest->load(['items.branch', 'approvals.actor.basicData', 'approvals.role', 'employee.basicData']);

        return view('HR_General.reimbursement.show', [
            'request'      => $reimbursementRequest,
            'signatories'  => $reimbursement->signatories($reimbursementRequest),
            'backRoute'    => route('general.my-reimbursement.index'),
            'printRoute'   => route('general.my-reimbursement.print', $reimbursementRequest),
        ]);
    }

    public function print(ReimbursementRequest $reimbursementRequest, ReimbursementService $reimbursement)
    {
        $this->authoriseOwner($reimbursementRequest);

        return $this->renderPrint($reimbursementRequest, $reimbursement);
    }

    // ── internal ────────────────────────────────────────────────────────────

    /**
     * Halaman cetak. Dipisah supaya sisi HR dapat memakainya kembali tanpa
     * menyalin satu baris pun — dokumen yang dicetak karyawan dan yang dicetak
     * HR harus identik.
     */
    public static function renderPrint(ReimbursementRequest $request, ReimbursementService $reimbursement)
    {
        $request->load(['items.branch', 'approvals.actor.basicData', 'employee.basicData']);

        return view('HR_General.reimbursement.print', [
            'request'     => $request,
            'heading'     => $reimbursement->documentHeading($request),
            'signatories' => $reimbursement->signatories($request),
        ]);
    }

    private function authoriseOwner(ReimbursementRequest $request): void
    {
        abort_if((int) $request->employee_id !== (int) session('user.id'), 403,
            'This reimbursement belongs to another employee.');
    }

    private function validatePayload(Request $request, ReimbursementService $reimbursement): array
    {
        $settings = ReimbursementSetting::current();
        $minTitle = $settings->require_title_min_chars;

        // Aturan baris item diambil dari service supaya pengajuan mandiri, form
        // "New RB", dan Edit menilai isi dokumen dengan aturan yang persis sama.
        $rules = $reimbursement->itemRules($settings, $request->all()) + [
            'request_date' => ['required', 'date'],
            'title'        => ['required', 'string', 'min:' . $minTitle, 'max:200'],
        ];

        $rules['supporting_url'] = array_filter([
            $settings->require_supporting_url ? 'required' : 'nullable',
            'url',
            'max:1000',
            $this->allowedHostRule($settings),
        ]);

        return $request->validate($rules, [
            'items.required'                   => 'Add at least one reimbursement item.',
            'items.*.description.required'     => 'Every item needs a description.',
            'items.*.branch_id.required'       => 'Every item needs a branch.',
            'items.*.receipt_no.required'      => 'Every item needs a receipt number.',
            'items.*.receipt_date_from.required' => 'Every item needs a receipt date.',
            'items.*.amount.required'          => 'Every item needs an amount.',
            'items.*.amount.min'               => 'Item amounts must be greater than zero.',
            'title.min'                        => "Please describe the reimbursement in at least {$minTitle} characters.",
            'supporting_url.required'          => 'A supporting document link is required.',
            'supporting_url.url'               => 'The supporting document must be a full link, starting with https://',
        ]);
    }

    /**
     * Batasi tautan bukti ke host yang diizinkan pengaturan.
     *
     * Dikembalikan sebagai closure rule, bukan diperiksa setelah validasi, supaya
     * pesan galatnya menempel pada field-nya sendiri dan isian pengguna tidak
     * hilang. Daftar host kosong berarti tidak dibatasi — dan halaman Settings
     * sudah mencegah kombinasi "wajib diisi tetapi daftarnya kosong".
     */
    private function allowedHostRule(ReimbursementSetting $settings): ?\Closure
    {
        $allowed = $settings->allowedSupportingHosts();

        if ($allowed === []) {
            return null;
        }

        return function (string $attribute, $value, \Closure $fail) use ($allowed) {
            $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
            $host = preg_replace('#^www\.#', '', $host);

            if ($host === '' || !in_array($host, $allowed, true)) {
                $fail('The supporting document link must point to one of: ' . implode(', ', $allowed) . '.');
            }
        };
    }
}
