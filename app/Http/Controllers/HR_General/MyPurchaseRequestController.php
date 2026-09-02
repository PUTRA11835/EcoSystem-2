<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PurchaseRequest\PurchaseRequest;
use App\Models\PurchaseRequest\PurchaseRequestSetting;
use App\Services\PurchaseRequest\PurchaseRequestService;
use Illuminate\Http\Request;

/**
 * Pengajuan purchase request mandiri (ESS).
 *
 * 🔴 `employee_id` SELALU diambil dari sesi, tidak pernah dari badan request.
 * Menyisipkan employee_id orang lain lewat DevTools tidak berpengaruh apa pun.
 *
 * 🔴 KEPEMILIKAN diperiksa pada `show`, `print`, dan `cancel` — bukan hanya lewat
 * slug menu. Slug hanya menjawab "boleh membuka halaman ini?"; ia tidak tahu
 * dokumen SIAPA yang sedang dibuka. Tanpa pemeriksaan ini, siapa pun yang boleh
 * mengajukan dapat membaca — atau membatalkan — dokumen rekannya hanya dengan
 * menebak id-nya.
 */
class MyPurchaseRequestController extends Controller
{
    public function index(PurchaseRequestService $purchaseRequest)
    {
        $employeeId = (int) session('user.id');
        $now        = now();

        return view('hr-general.purchase-request.my-purchase-request', [
            'requests' => $purchaseRequest->history($employeeId),
            'summary'  => $purchaseRequest->monthlySummary($employeeId, (int) $now->format('Y'), (int) $now->format('n')),
            'settings' => PurchaseRequestSetting::current(),
            'month'    => $now->format('F Y'),
        ]);
    }

    public function create(PurchaseRequestService $purchaseRequest)
    {
        $settings = PurchaseRequestSetting::current();

        // Batas tanggal dihitung di sini, bukan di Blade, supaya atribut min/max
        // pada input tanggal selalu sejalan dengan aturan yang ditegakkan service
        // — pengguna tidak menemui penolakan setelah menekan kirim.
        $today = now()->startOfDay();
        $steps = $purchaseRequest->activeSteps();

        return view('hr-general.purchase-request.submit', array_merge([
            'settings'    => $settings,
            'costCenters' => $purchaseRequest->costCenterOptions(),
            'steps'       => $steps,
            'minDate'     => $settings->hasBackdateLimit()
                ? $today->copy()->subDays($settings->max_backdate_days)->toDateString()
                : null,
            'maxDate'     => $settings->allow_future_date ? null : $today->toDateString(),
            'requesterName' => $this->nameOf((int) session('user.id')),
        ], $this->approverChoice($steps)));
    }

    public function store(Request $request, PurchaseRequestService $purchaseRequest)
    {
        $employeeId = (int) session('user.id');
        $data       = $this->validatePayload($request, $purchaseRequest);

        $result = $purchaseRequest->submit($employeeId, $data);

        if (!$result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.my-purchase-request.index')
            ->with('success', 'Purchase request ' . $result['request']->request_no
                . ' submitted and is waiting for review.');
    }

    public function show(PurchaseRequest $purchaseRequest, PurchaseRequestService $service)
    {
        $this->authoriseOwner($purchaseRequest);

        $purchaseRequest->load([
            'items.branch', 'items.project',
            'approvals.actor.basicData', 'approvals.role',
            'employee.basicData',
        ]);

        $settings = PurchaseRequestSetting::current();

        return view('hr-general.purchase-request.show', [
            'request'      => $purchaseRequest,
            'signatures'   => $service->signatureColumns($purchaseRequest),
            'backRoute'    => route('general.my-purchase-request.index'),
            'printRoute'   => route('general.my-purchase-request.print', $purchaseRequest),
            // Tombol Cancel hanya milik sisi karyawan; sisi HR memakai Delete.
            'cancelRoute'  => $settings->allow_requester_cancel && $purchaseRequest->isCancellable()
                ? route('general.my-purchase-request.cancel', $purchaseRequest)
                : null,
        ]);
    }

    public function print(PurchaseRequest $purchaseRequest, PurchaseRequestService $service)
    {
        $this->authoriseOwner($purchaseRequest);

        return $this->renderPrint($purchaseRequest, $service);
    }

    /**
     * Tarik kembali dokumen sendiri (Keputusan D131).
     *
     * Seluruh syaratnya diperiksa ULANG di service — tombol yang disembunyikan
     * di layar bukan penjagaan, dan permintaan POST dapat disusun tanpa membuka
     * halamannya sama sekali.
     */
    public function cancel(PurchaseRequest $purchaseRequest, PurchaseRequestService $service)
    {
        $this->authoriseOwner($purchaseRequest);

        $result = $service->cancel($purchaseRequest, (int) session('user.id'));

        if (!$result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.my-purchase-request.index')
            ->with('success', 'Purchase request ' . $purchaseRequest->request_no . ' was cancelled.');
    }

    // ── internal ────────────────────────────────────────────────────────────

    /**
     * Halaman cetak. Dipisah supaya sisi HR dapat memakainya kembali tanpa
     * menyalin satu baris pun — dokumen yang dicetak karyawan dan yang dicetak
     * HR harus identik.
     */
    public static function renderPrint(PurchaseRequest $request, PurchaseRequestService $service)
    {
        $request->load(['items.branch', 'items.project', 'approvals.actor.basicData', 'employee.basicData']);

        return view('hr-general.purchase-request.print', [
            'request'    => $request,
            'heading'    => $service->documentHeading($request),
            'signatures' => $service->signatureColumns($request),
        ]);
    }

    private function authoriseOwner(PurchaseRequest $request): void
    {
        abort_if((int) $request->employee_id !== (int) session('user.id'), 403,
            'This purchase request belongs to another employee.');
    }

    /**
     * Data dropdown "Approver" pada form pengajuan (Keputusan D126).
     *
     * Hanya langkah PERTAMA yang ditanyakan ke pemohon di form ini. Langkah
     * berikutnya — kalau ada yang juga bertanda `requester_selectable` — tidak
     * ditanyakan: pemohon tidak dapat menilai siapa yang pantas meninjau di
     * tahap yang belum ia capai, dan service akan mengisinya dari kandidat
     * langkahnya sendiri.
     *
     * @return array{firstStep: ?object, chooseApprover: bool, approverCandidates: array}
     */
    private function approverChoice($steps): array
    {
        $first = $steps->first();

        if (!$first || !$first->offersChoice()) {
            return [
                'firstStep'          => $first,
                'chooseApprover'     => false,
                'approverCandidates' => [],
            ];
        }

        $ids = $first->candidateEmployeeIds();

        $candidates = Employee::with('basicData:basic_data_id,employee_id,nick_name')
            ->whereIn('employee_id', $ids)
            ->where('is_active', 1)
            ->get(['employee_id', 'eci'])
            ->map(fn (Employee $e) => [
                'id'   => (int) $e->employee_id,
                'name' => $e->basicData?->nick_name ?? $e->eci,
            ])
            ->sortBy('name')
            ->values()
            ->all();

        // Kandidat yang seluruhnya sudah non-aktif membuat dropdown kosong —
        // dan dokumen yang lahir dari situ tidak punya jalan keluar. Jatuh
        // kembali ke perilaku non-pilihan; service tetap menolak bila memang
        // tidak ada kandidat sama sekali, dengan pesan yang menyebut langkahnya.
        return [
            'firstStep'          => $first,
            'chooseApprover'     => $candidates !== [],
            'approverCandidates' => $candidates,
        ];
    }

    private function nameOf(int $employeeId): string
    {
        $employee = Employee::with('basicData')->find($employeeId);

        return $employee?->basicData?->nick_name ?? $employee?->eci ?? (session('user.name') ?? '—');
    }

    private function validatePayload(Request $request, PurchaseRequestService $purchaseRequest): array
    {
        $settings = PurchaseRequestSetting::current();
        $minTitle = $settings->require_title_min_chars;

        // Aturan baris item diambil dari service supaya pengajuan mandiri, form
        // "New PR", dan Edit menilai isi dokumen dengan aturan yang persis sama.
        $rules = $purchaseRequest->itemRules($settings, $request->all()) + [
            'request_date'    => ['required', 'date'],
            'title'           => ['required', 'string', 'min:' . $minTitle, 'max:200'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'approver_ids'    => ['nullable', 'array'],
            'approver_ids.*'  => ['integer', 'exists:employee,employee_id'],
        ];

        return $request->validate($rules, [
            'items.required'                  => 'Add at least one request item.',
            'items.*.description.required'    => 'Every item needs a description.',
            'items.*.qty.required'            => 'Every item needs a quantity.',
            'items.*.qty.min'                 => 'Item quantities must be greater than zero.',
            'items.*.unit.required'           => 'Every item needs a unit.',
            'items.*.unit.in'                 => 'That unit is not allowed. Allowed units: '
                                                 . implode(', ', $settings->unitOptions()) . '.',
            'items.*.cost_center_type.required' => 'Every item needs a cost center type.',
            'items.*.use_date.required'       => 'Every item needs a use date.',
            'items.*.period_from.required'    => 'Every item needs a period.',
            'title.min'                       => "Please describe the request in at least {$minTitle} characters.",
        ]);
    }
}
