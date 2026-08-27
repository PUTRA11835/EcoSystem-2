<?php

namespace App\Http\Controllers\HR_General;

use App\Exports\OvertimeExport;
use App\Http\Controllers\Controller;
use App\Models\Overtime\OvertimeRequest;
use App\Models\Overtime\OvertimeSetting;
use App\Services\Overtime\OvertimeService;
use Illuminate\Http\Request;
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

        $requests = $this->baseQuery($filters)
            ->when($filters['scope'] === 'mine', fn ($q) => $q->whereIn('id', $mineIds ?: [0]))
            ->orderByRaw("FIELD(status, 'submitted', 'in_review') DESC")
            ->orderByDesc('overtime_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $counts = OvertimeRequest::query()
            ->selectRaw('status, COUNT(*) AS total, COALESCE(SUM(duration_minutes), 0) AS minutes')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('hr-general.overtime.review', [
            'requests' => $requests,
            'filters'  => $filters,
            'mineIds'  => $mineIds,
            'settings' => OvertimeSetting::current(),
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

        $validated = $request->validate($rules);

        if ($settings->allow_approver_adjust_time
            && !empty($validated['start_time'])
            && !empty($validated['end_time'])) {

            $crosses = $overtime->crossesMidnight($validated['start_time'], $validated['end_time']);

            if ($crosses && !$settings->allow_crosses_midnight) {
                return back()->with('error',
                    'The adjusted time passes midnight, which is currently not allowed.');
            }

            if ($overtime->durationMinutes($validated['start_time'], $validated['end_time']) <= 0) {
                return back()->with('error', 'Adjusted end time must be different from start time.');
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
        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'notes.required' => 'Please explain why the request is rejected.',
        ]);

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

        // Ekspor memakai query yang SAMA dengan layar, hanya tanpa paginasi,
        // supaya isi berkas selalu sama dengan yang dilihat pengguna.
        $rows = $this->baseQuery($filters)
            ->orderByDesc('overtime_date')
            ->orderByDesc('id')
            ->get();

        $name = 'overtime-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new OvertimeExport($rows), $name);
    }

    // ── internal ────────────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        return [
            'status' => (string) $request->query('status', 'open'),
            'scope'  => (string) $request->query('scope', 'all'),
            'from'   => (string) $request->query('from', ''),
            'to'     => (string) $request->query('to', ''),
            'search' => trim((string) $request->query('search', '')),
        ];
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

    /** Apakah pengguna aktif memegang izin mengelola pengajuan tertutup? */
    private function canManage(): bool
    {
        $employee = \App\Models\Employee::where('employee_id', session('user.id'))->first();

        return $employee?->canAccessMenu('general.overtime.manage') ?? false;
    }
}
