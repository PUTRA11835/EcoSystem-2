<?php

namespace App\Http\Controllers\HR_General;

use App\Exports\OvertimeExport;
use App\Http\Controllers\Controller;
use App\Models\Overtime\OvertimeRequest;
use App\Models\Overtime\OvertimeSetting;
use App\Services\Overtime\OvertimeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Peninjauan dan persetujuan lembur (sisi HR / penyetuju).
 *
 * Pemisahan dua izin yang sengaja tidak digabung:
 *   general.overtime.approve  boleh membuka halaman ini dan bertindak pada
 *                             langkah yang sedang menunggu DIRINYA
 *   general.overtime.manage   boleh menyentuh pengajuan yang bagi karyawan
 *                             sudah tertutup — ditolak, atau periodenya dikunci
 *
 * Tanpa pemisahan itu, memberi hak meninjau otomatis memberi hak membongkar
 * pengajuan yang sudah selesai.
 */
class OvertimeReviewController extends Controller
{
    public function index(Request $request, OvertimeService $overtime)
    {
        $filters = $this->filters($request);
        $actorId = (int) session('user.id');

        // Id yang menjadi giliran orang ini. Dihitung sekali lalu dipakai untuk
        // menandai baris DAN untuk menyaring — supaya penyetuju tidak perlu
        // menebak mana yang menunggu dirinya.
        $mineIds = $overtime->pendingIdsFor($actorId);

        // A person who is only NAMED on the workflow (no Overtime menu) sees the requests waiting for them and nothing else — not the
        // whole company's overtime, and not its totals.
        $restricted = !$this->holds('general.overtime');
        if ($restricted) {
            $filters['scope'] = 'mine';
        }

        $requests = $this->baseQuery($filters)
            ->when($filters['scope'] === 'mine', fn ($q) => $q->whereIn('id', $mineIds ?: [0]))
            ->orderByRaw("FIELD(status, 'submitted', 'in_review') DESC")
            ->orderByDesc('overtime_date')
            ->orderByDesc('id')
            ->paginate($this->pageSize())
            ->withQueryString();

        $counts = OvertimeRequest::query()
            ->when($restricted, fn ($q) => $q->whereIn('id', $mineIds ?: [0]))
            ->selectRaw('status, COUNT(*) AS total, COALESCE(SUM(duration_minutes), 0) AS minutes')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('hr-general.overtime.review', [
            'requests' => $requests,
            'filters'  => $filters,
            'mineIds'  => $mineIds,
            'settings' => OvertimeSetting::current(),

            // Kolom aksi tampil bagi pemegang slug approve ATAU penyetuju yang
            // ditunjuk pada alur; siapa yang boleh bertindak pada SATU dokumen
            // tetap diputuskan service lewat langkahnya.
            'canAct'   => $this->holds('general.overtime.approve') || $overtime->isDesignatedApprover($actorId),
            'counts'   => [
                'pending'  => collect(OvertimeRequest::OPEN_STATUSES)
                    ->sum(fn ($s) => (int) ($counts[$s]->total ?? 0)),
                'approved' => (int) ($counts[OvertimeRequest::STATUS_APPROVED]->total ?? 0),
                'rejected' => (int) ($counts[OvertimeRequest::STATUS_REJECTED]->total ?? 0),
                'minutes'  => (int) ($counts[OvertimeRequest::STATUS_APPROVED]->minutes ?? 0),
                'mine'     => count($mineIds),
            ],
        ]);
    }

    public function approve(Request $request, OvertimeRequest $overtimeRequest, OvertimeService $overtime)
    {
        $settings = OvertimeSetting::current();

        $rules = ['notes' => ['nullable', 'string', 'max:255']];

        // Penyesuaian jam hanya divalidasi bila memang diizinkan — kalau tidak,
        // field-nya diabaikan sepenuhnya, bukan ditolak dengan pesan galat yang
        // membingungkan.
        if ($settings->allow_approver_adjust_time) {
            $rules['start_time'] = ['nullable', 'date_format:H:i', 'required_with:end_time'];
            $rules['end_time']   = ['nullable', 'date_format:H:i', 'required_with:start_time'];
        }

        // Kolom jam peramban kadang mengirim H:i:s; samakan ke H:i agar tidak ditolak karena format.
        $validator = Validator::make($this->normalizeTimes($request->all()), $rules, [
            'notes.max'                => 'The note is too long: the maximum is 255 characters.',
            'start_time.date_format'   => 'Use the HH:MM format for the start time.',
            'end_time.date_format'     => 'Use the HH:MM format for the end time.',
            'start_time.required_with' => 'Fill in both the start time and the end time, or leave both as they are.',
            'end_time.required_with'   => 'Fill in both the start time and the end time, or leave both as they are.',
        ]);

        // Validasi gagal HARUS terlihat: layar ini hanya menampilkan pesan flash `error`, bukan daftar galat validasi,
        // sehingga dulu Approve tampak "tidak berbuat apa-apa".
        if ($validator->fails()) {
            return back()->withInput()->with('error', $validator->errors()->first());
        }

        $validated = $validator->validated();

        // Jam yang dikirim sama dengan jam pengajuan = TIDAK ada penyesuaian (kolom di layar terisi jam asli). Tanpa ini,
        // pengajuan lintas tengah malam tak bisa disetujui setelah pengaturan "lintas tengah malam" dimatikan.
        if (!empty($validated['start_time']) && !empty($validated['end_time'])
            && $validated['start_time'] === substr((string) $overtimeRequest->start_time, 0, 5)
            && $validated['end_time'] === substr((string) $overtimeRequest->end_time, 0, 5)) {
            $validated['start_time'] = $validated['end_time'] = null;
        }

        if ($settings->allow_approver_adjust_time
            && !empty($validated['start_time'])
            && !empty($validated['end_time'])) {

            if ($validated['start_time'] === $validated['end_time']) {
                return back()->withInput()->with('error', 'The end time must be different from the start time.');
            }

            $crosses = $overtime->crossesMidnight($validated['start_time'], $validated['end_time']);

            if ($crosses && !$settings->allow_crosses_midnight) {
                return back()->withInput()->with('error',
                    'The adjusted time passes midnight, which is currently not allowed. Keep the end time later than the start time on the same day, or ask HR to allow overnight overtime in Overtime Settings.');
            }
        }

        $result = $overtime->approve(
            $overtimeRequest,
            (int) session('user.id'),
            $validated,
            $this->canManage()
        );

        if (!$result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return back()->with('success', $result['completed']
            ? 'Overtime request ' . $overtimeRequest->request_no . ' fully approved.'
            : 'Step approved. The request moved on to the next approver.');
    }

    public function reject(Request $request, OvertimeRequest $overtimeRequest, OvertimeService $overtime)
    {
        // Catatan WAJIB saat menolak. Penolakan tanpa alasan hanya memindahkan
        // pertanyaan karyawan ke jalur lain — biasanya ke meja HR langsung.
        $validator = Validator::make($request->all(), [
            'notes' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'notes.required' => 'Please explain why the request is rejected.',
            'notes.min'      => 'The rejection note is too short: write at least 5 characters.',
            'notes.max'      => 'The rejection note is too long: the maximum is 255 characters.',
        ]);

        if ($validator->fails()) {
            return back()->withInput()->with('error', $validator->errors()->first());
        }

        $validated = $validator->validated();

        $result = $overtime->reject(
            $overtimeRequest,
            (int) session('user.id'),
            $validated['notes'],
            $this->canManage()
        );

        return $result['allowed']
            ? back()->with('success', 'Overtime request rejected.')
            : back()->with('error', $result['reason']);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        $label   = now()->format('Y-m-d');

        // Ekspor per periode: minggu (ISO, Senin–Minggu), bulan, tahun, atau rentang tanggal sendiri. Tanpa `period`,
        // ekspor tetap memakai filter layar apa adanya (perilaku lama).
        $period = (string) $request->query('period', '');
        if ($period !== '') {
            $range = $this->periodRange($period, (string) $request->query('value', ''), $filters);
            if (is_string($range)) {
                return back()->with('error', $range);
            }
            [$filters['from'], $filters['to'], $label] = $range;
        }

        // Ekspor memakai query yang SAMA dengan layar, hanya tanpa paginasi,
        // supaya isi berkas selalu sama dengan yang dilihat pengguna.
        $rows = $this->baseQuery($filters)
            ->orderByDesc('overtime_date')
            ->orderByDesc('id')
            ->get();

        $name = 'overtime-' . $label . '.xlsx';

        return Excel::download(new OvertimeExport($rows), $name);
    }

    // ── internal ────────────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        return [
            // Bawaan "All": daftar tidak tampak kosong bagi yang belum memilih; yang menunggu keputusan tetap diurutkan paling atas.
            'status' => (string) $request->query('status', 'all') ?: 'all',
            'scope'  => (string) $request->query('scope', 'all'),
            'from'   => (string) $request->query('from', ''),
            'to'     => (string) $request->query('to', ''),
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    /** Kolom jam peramban kadang H:i:s → H:i. */
    private function normalizeTimes(array $input): array
    {
        foreach (['start_time', 'end_time'] as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $input[$key])) {
                $input[$key] = substr($input[$key], 0, 5);
            }
        }

        return $input;
    }

    /**
     * Rentang tanggal untuk ekspor.
     *
     * @return array{0:string,1:string,2:string}|string  [dari, sampai, label berkas] atau pesan galat
     */
    private function periodRange(string $period, string $value, array $filters)
    {
        try {
            switch ($period) {
                case 'week':
                    if (!preg_match('/^(\d{4})-W(\d{2})$/', $value, $m) || (int) $m[2] < 1 || (int) $m[2] > 53) {
                        return 'Choose a valid week (for example 2026-W43).';
                    }
                    $start = Carbon::now()->setISODate((int) $m[1], (int) $m[2])->startOfWeek();

                    return [$start->toDateString(), $start->copy()->endOfWeek()->toDateString(), $m[1] . '-W' . $m[2]];
                case 'month':
                    if (!preg_match('/^(\d{4})-(\d{2})$/', $value, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
                        return 'Choose a valid month (for example 2026-10).';
                    }
                    $start = Carbon::create((int) $m[1], (int) $m[2], 1)->startOfMonth();

                    return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString(), $m[1] . '-' . $m[2]];
                case 'year':
                    if (!preg_match('/^\d{4}$/', $value) || (int) $value < 2000 || (int) $value > 2100) {
                        return 'Choose a valid year (for example 2026).';
                    }

                    return [$value . '-01-01', $value . '-12-31', $value];
                case 'range':
                    if ($filters['from'] === '' || $filters['to'] === '') {
                        return 'Choose both the From and To dates.';
                    }
                    if ($filters['from'] > $filters['to']) {
                        return 'The From date must not be later than the To date.';
                    }

                    return [$filters['from'], $filters['to'], $filters['from'] . '_to_' . $filters['to']];
            }
        } catch (\Throwable $e) {
            return 'That period could not be read. Please choose it again.';
        }

        return 'Unknown export period.';
    }

    private function baseQuery(array $filters)
    {
        return OvertimeRequest::query()
            ->with(['employee.basicData', 'approvals.actor.basicData', 'approvals.role', 'adjuster.basicData'])
            ->when($filters['status'] === 'open',
                fn ($q) => $q->whereIn('status', OvertimeRequest::OPEN_STATUSES))
            ->when(!in_array($filters['status'], ['open', 'all'], true),
                fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['from'] !== '',
                fn ($q) => $q->whereDate('overtime_date', '>=', $filters['from']))
            ->when($filters['to'] !== '',
                fn ($q) => $q->whereDate('overtime_date', '<=', $filters['to']))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = $filters['search'];

                $q->where(function ($inner) use ($term) {
                    $inner->where('request_no', 'like', "%{$term}%")
                          ->orWhere('reason', 'like', "%{$term}%")
                          ->orWhereHas('employee', fn ($e) => $e->where('eci', 'like', "%{$term}%"))
                          ->orWhereHas('employee.basicData', fn ($b) => $b->where('nick_name', 'like', "%{$term}%"));
                });
            });
    }

    private function holds(string $slug): bool
    {
        $employee = \App\Models\Employee::where('employee_id', session('user.id'))->first();

        return $employee?->canAccessMenu($slug) ?? false;
    }

    /** Apakah pengguna aktif memegang izin mengelola pengajuan tertutup? */
    private function canManage(): bool
    {
        $employee = \App\Models\Employee::where('employee_id', session('user.id'))->first();

        return $employee?->canAccessMenu('general.overtime.manage') ?? false;
    }
}
