<?php

namespace App\Services\Overtime;

use App\Models\Attendance\AttendanceRecord;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Overtime\OvertimeApprovalStep;
use App\Models\Overtime\OvertimeRequest;
use App\Models\Overtime\OvertimeRequestApproval;
use App\Models\Overtime\OvertimeSetting;
use App\Models\ReportingPeriod;
use App\Services\HolidayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mesin pengajuan dan persetujuan lembur.
 *
 * AGNOSTIK TERHADAP TRANSPORT: menerima array biasa, bukan Request, sehingga
 * bila kelak endpoint mobile dibuka, cukup menambah controller tipis tanpa
 * menyentuh aturan bisnisnya. Pola yang sama dipakai AttendanceService.
 *
 * Seluruh gerbang bisnis mengembalikan ['allowed' => bool, 'reason' => string]
 * meniru PeriodService yang sudah jadi konvensi di basis kode ini.
 *
 * 🔴 `employee_id` SELALU diterima sebagai argumen dari pemanggil yang
 * mengambilnya dari sesi — tidak pernah dari badan request. Ini yang mencegah
 * seseorang mengajukan lembur atas nama rekannya.
 */
class OvertimeService
{
    public function __construct(private HolidayService $holidays)
    {
    }

    // =======================================================================
    // PENGAJUAN
    // =======================================================================

    /**
     * Ajukan lembur.
     *
     * @param  array{overtime_date: string, start_time: string, end_time: string, reason: string}  $payload
     * @return array{allowed: bool, reason: string, request: ?OvertimeRequest}
     */
    public function submit(int $employeeId, array $payload): array
    {
        $settings = OvertimeSetting::current();
        $date     = Carbon::parse($payload['overtime_date'])->startOfDay();

        $gate = $this->checkDateRules($date, $settings);
        if (!$gate['allowed']) {
            return $gate + ['request' => null];
        }

        $crosses  = $this->crossesMidnight($payload['start_time'], $payload['end_time']);
        $duration = $this->durationMinutes($payload['start_time'], $payload['end_time']);

        if ($crosses && !$settings->allow_crosses_midnight) {
            return [
                'allowed' => false,
                'reason'  => 'Overtime cannot pass midnight. Split it into two requests, '
                           . 'or ask HR to enable overnight overtime in Overtime Settings.',
                'request' => null,
            ];
        }

        if ($duration <= 0) {
            return [
                'allowed' => false,
                'reason'  => 'End time must be different from start time.',
                'request' => null,
            ];
        }

        $overlap = $this->findOverlap($employeeId, $date, $payload['start_time'], $payload['end_time'], $crosses);
        if ($overlap) {
            return [
                'allowed' => false,
                'reason'  => 'This overlaps with request ' . $overlap->request_no
                           . ' (' . substr((string) $overlap->start_time, 0, 5)
                           . ' - ' . substr((string) $overlap->end_time, 0, 5) . ').',
                'request' => null,
            ];
        }

        // Langkah persetujuan harus ada SEBELUM pengajuan dibuat. Tanpa satu pun
        // langkah aktif, pengajuan akan lahir tanpa jalan keluar — tidak dapat
        // disetujui maupun ditolak siapa pun.
        $steps = $this->activeSteps();
        if ($steps->isEmpty()) {
            return [
                'allowed' => false,
                'reason'  => 'No approval step is configured yet. Ask HR to set one up in Overtime Settings.',
                'request' => null,
            ];
        }

        $request = DB::transaction(function () use ($employeeId, $date, $payload, $crosses, $duration, $settings, $steps) {
            $record = $this->attendanceFor($employeeId, $date);

            $request = OvertimeRequest::create([
                'request_no'                  => $this->nextRequestNo($date),
                'employee_id'                 => $employeeId,
                'overtime_date'               => $date->toDateString(),
                'start_time'                  => $payload['start_time'],
                'end_time'                    => $payload['end_time'],
                'crosses_midnight'            => $crosses,
                'duration_minutes'            => $duration,
                'day_type'                    => $this->dayTypeFor($date),
                'reason'                      => $payload['reason'],
                'status'                      => OvertimeRequest::STATUS_SUBMITTED,
                'current_step_order'          => $steps->first()->order_seq,
                'attendance_record_id'        => $record?->id,
                'attendance_overtime_minutes' => $record?->overtime_minutes,
                'flags'                       => $this->buildFlags($employeeId, $date, $duration, $record, $settings),
                'period_year'                 => (int) $date->format('Y'),
                'period_month'                => (int) $date->format('n'),
            ]);

            $this->snapshotSteps($request, $steps);

            return $request;
        });

        return [
            'allowed' => true,
            'reason'  => '',
            'request' => $request->fresh('approvals'),
        ];
    }

    /**
     * Batalkan pengajuan sendiri.
     *
     * Hanya selama belum ada satu langkah pun yang bertindak. Setelah ada yang
     * menyetujui, membatalkannya akan menghapus jejak persetujuan yang sudah
     * terjadi — dan itu justru yang membuat dokumen ini dapat dipertanggung-
     * jawabkan.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function cancel(OvertimeRequest $request, int $employeeId): array
    {
        if ($request->employee_id !== $employeeId) {
            return ['allowed' => false, 'reason' => 'That request belongs to someone else.'];
        }

        if (!$request->isCancellable()) {
            return [
                'allowed' => false,
                'reason'  => 'This request has already been reviewed and can no longer be cancelled.',
            ];
        }

        DB::transaction(function () use ($request) {
            $request->update([
                'status'             => OvertimeRequest::STATUS_CANCELLED,
                'current_step_order' => null,
                'cancelled_at'       => now(),
            ]);

            $request->approvals()
                ->where('status', OvertimeRequestApproval::STATUS_WAITING)
                ->update(['status' => OvertimeRequestApproval::STATUS_SKIPPED]);
        });

        return ['allowed' => true, 'reason' => ''];
    }

    // =======================================================================
    // PERSETUJUAN
    // =======================================================================

    /**
     * Bolehkah karyawan ini bertindak pada pengajuan tersebut sekarang?
     *
     * Memeriksa empat hal yang berbeda dan sengaja tidak digabung, supaya pesan
     * penolakannya menyebut sebab yang tepat:
     *   1. pengajuannya masih berjalan
     *   2. ada langkah yang sedang menunggu
     *   3. orang ini memang penyetuju langkah tersebut
     *   4. bila ia pemohonnya sendiri — apakah itu diizinkan
     *
     * @return array{allowed: bool, reason: string, approval: ?OvertimeRequestApproval}
     */
    public function canAct(OvertimeRequest $request, int $actorId, bool $canManage = false): array
    {
        $deny = fn (string $reason) => ['allowed' => false, 'reason' => $reason, 'approval' => null];

        if (!$request->isOpen()) {
            return $deny('This request has already been ' . $request->status . '.');
        }

        $approval = $request->currentApproval();
        if (!$approval || !$approval->isWaiting()) {
            return $deny('This request has no step waiting for a decision.');
        }

        $settings = OvertimeSetting::current();

        if ($request->employee_id === $actorId && !$settings->allow_self_approval) {
            $fallback = $settings->self_approval_fallback_role_id;

            return $deny('You cannot approve your own overtime request.'
                . ($fallback ? ' Ask a holder of the fallback approver role to review it.' : ''));
        }

        // Periode terkunci: siapa pun ditahan hanya pada kebijakan block_all.
        // Pada block_employee, penyetuju tetap boleh bertindak — pembatasannya
        // memang ditujukan kepada karyawan pengaju.
        if ($this->periodLocked($request->overtime_date)
            && $settings->locked_period_policy === OvertimeSetting::LOCK_BLOCK_ALL
            && !$canManage) {
            return $deny('The period for this date is closed. Only a holder of '
                . 'Overtime — Manage Locked / Rejected Requests can act on it.');
        }

        $roleIds = $this->roleIdsOf($actorId);

        if (!$approval->allows($actorId, $roleIds)) {
            return $deny('You are not an approver for step "' . $approval->step_name . '".');
        }

        return ['allowed' => true, 'reason' => '', 'approval' => $approval];
    }

    /**
     * Setujui langkah yang sedang menunggu, lalu majukan alurnya.
     *
     * @param  array{notes?: ?string, start_time?: ?string, end_time?: ?string}  $payload
     * @return array{allowed: bool, reason: string, completed: bool}
     */
    public function approve(OvertimeRequest $request, int $actorId, array $payload = [], bool $canManage = false): array
    {
        $gate = $this->canAct($request, $actorId, $canManage);
        if (!$gate['allowed']) {
            return ['allowed' => false, 'reason' => $gate['reason'], 'completed' => false];
        }

        $settings  = OvertimeSetting::current();
        $approval  = $gate['approval'];
        $completed = false;

        DB::transaction(function () use ($request, $approval, $actorId, $payload, $settings, &$completed) {
            $this->applyTimeAdjustment($request, $actorId, $payload, $settings);

            // Disetujui pemohonnya sendiri tetap ditandai meski diizinkan —
            // pengaturan yang membolehkannya tidak membuat kejadiannya lumrah,
            // hanya membuatnya tidak diblokir.
            if ($request->employee_id === $actorId) {
                $this->addFlag($request, OvertimeRequest::FLAG_SELF_APPROVED);
            }

            if ($this->periodLocked($request->overtime_date)) {
                $this->addFlag($request, OvertimeRequest::FLAG_LOCKED_PERIOD);
            }

            $approval->update([
                'status'   => OvertimeRequestApproval::STATUS_APPROVED,
                'acted_by' => $actorId,
                'acted_at' => now(),
                'notes'    => $payload['notes'] ?? null,
            ]);

            $next = $request->approvals()
                ->where('status', OvertimeRequestApproval::STATUS_WAITING)
                ->where('order_seq', '>', $approval->order_seq)
                ->orderBy('order_seq')
                ->first();

            if ($next) {
                $request->status             = OvertimeRequest::STATUS_IN_REVIEW;
                $request->current_step_order = $next->order_seq;
            } else {
                $request->status             = OvertimeRequest::STATUS_APPROVED;
                $request->current_step_order = null;
                $request->completed_at       = now();
                $completed                   = true;
            }

            $request->save();
        });

        $this->notify(
            $request,
            $completed ? 'approved' : 'progressed',
            $payload['notes'] ?? null
        );

        return ['allowed' => true, 'reason' => '', 'completed' => $completed];
    }

    /**
     * Tolak pengajuan. Catatan WAJIB — penolakan tanpa alasan hanya memindahkan
     * pertanyaan karyawan ke meja HR lewat jalur lain.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function reject(OvertimeRequest $request, int $actorId, string $notes, bool $canManage = false): array
    {
        $gate = $this->canAct($request, $actorId, $canManage);
        if (!$gate['allowed']) {
            return ['allowed' => false, 'reason' => $gate['reason']];
        }

        $approval = $gate['approval'];

        DB::transaction(function () use ($request, $approval, $actorId, $notes) {
            $approval->update([
                'status'   => OvertimeRequestApproval::STATUS_REJECTED,
                'acted_by' => $actorId,
                'acted_at' => now(),
                'notes'    => $notes,
            ]);

            // Langkah sesudahnya tidak pernah dijalani — ditandai `skipped`,
            // bukan dibiarkan `waiting`, supaya daftar tunggu penyetuju
            // berikutnya tidak berisi pengajuan yang sudah selesai.
            $request->approvals()
                ->where('status', OvertimeRequestApproval::STATUS_WAITING)
                ->update(['status' => OvertimeRequestApproval::STATUS_SKIPPED]);

            $request->update([
                'status'             => OvertimeRequest::STATUS_REJECTED,
                'current_step_order' => null,
                'completed_at'       => now(),
            ]);
        });

        $this->notify($request, 'rejected', $notes);

        return ['allowed' => true, 'reason' => ''];
    }

    // =======================================================================
    // PEMBACAAN
    // =======================================================================

    /** Riwayat pengajuan milik seorang karyawan. */
    public function history(int $employeeId, int $limit = 50): Collection
    {
        return OvertimeRequest::with('approvals')
            ->forEmployee($employeeId)
            ->orderByDesc('overtime_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Ringkasan bulan berjalan untuk kartu statistik.
     *
     * @return array{submitted: int, approved: int, pending: int, approved_minutes: int}
     */
    public function monthlySummary(int $employeeId, int $year, int $month): array
    {
        $rows = OvertimeRequest::forEmployee($employeeId)
            ->whereYear('overtime_date', $year)
            ->whereMonth('overtime_date', $month)
            ->selectRaw('status, COUNT(*) AS total, COALESCE(SUM(duration_minutes), 0) AS minutes')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $open = collect(OvertimeRequest::OPEN_STATUSES)
            ->sum(fn ($s) => (int) ($rows[$s]->total ?? 0));

        return [
            'submitted'        => (int) $rows->sum('total'),
            'approved'         => (int) ($rows[OvertimeRequest::STATUS_APPROVED]->total ?? 0),
            'pending'          => $open,
            'approved_minutes' => (int) ($rows[OvertimeRequest::STATUS_APPROVED]->minutes ?? 0),
        ];
    }

    /**
     * Pengajuan yang sedang menunggu tindakan orang ini.
     *
     * Dipakai menyaring daftar peninjauan supaya penyetuju tidak perlu menebak
     * mana yang menjadi gilirannya.
     *
     * @return array<int> id pengajuan
     */
    public function pendingIdsFor(int $actorId): array
    {
        $roleIds = $this->roleIdsOf($actorId);

        return OvertimeRequestApproval::query()
            ->where('status', OvertimeRequestApproval::STATUS_WAITING)
            ->get()
            ->filter(fn ($a) => $a->allows($actorId, $roleIds))
            ->pluck('overtime_request_id')
            ->unique()
            ->values()
            ->all();
    }

    // =======================================================================
    // PERHITUNGAN — dipakai juga oleh tampilan
    // =======================================================================

    /** Durasi dalam menit; jam selesai lebih awal dianggap hari berikutnya. */
    public function durationMinutes(string $start, string $end): int
    {
        $from = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $to   = Carbon::createFromFormat('H:i', substr($end, 0, 5));

        if ($to->lessThanOrEqualTo($from)) {
            $to->addDay();
        }

        return (int) $from->diffInMinutes($to);
    }

    public function crossesMidnight(string $start, string $end): bool
    {
        return substr($end, 0, 5) <= substr($start, 0, 5);
    }

    /**
     * Klasifikasi hari, DIBEKUKAN saat pengajuan.
     *
     * Libur resmi diperiksa lebih dulu daripada akhir pekan supaya labelnya
     * yang paling spesifik. Pengalinya sendiri sama, jadi ini hanya soal apa
     * yang terbaca manusia di layar.
     */
    public function dayTypeFor(Carbon $date): string
    {
        $isPublicHoliday = in_array(
            $date->format('Y-m-d'),
            $this->holidays->getHolidayDates($date->year, $date->year),
            true
        );

        if ($isPublicHoliday) {
            return OvertimeRequest::DAY_PUBLIC_HOLIDAY;
        }

        return $date->isWeekend()
            ? OvertimeRequest::DAY_WEEKEND
            : OvertimeRequest::DAY_WORKDAY;
    }

    /** Apakah periode pelaporan untuk tanggal ini sudah ditutup? */
    public function periodLocked($date): bool
    {
        $date   = $date instanceof Carbon ? $date : Carbon::parse($date);
        $coords = ReportingPeriod::periodFor($date);

        return ReportingPeriod::isClosed($coords['year'], $coords['month']);
    }

    /** Langkah persetujuan yang berlaku untuk pengajuan baru. */
    public function activeSteps(): Collection
    {
        return OvertimeApprovalStep::forOvertime()
            ->active()
            ->orderBy('order_seq')
            ->get();
    }

    // =======================================================================
    // internal
    // =======================================================================

    /** @return array{allowed: bool, reason: string} */
    private function checkDateRules(Carbon $date, OvertimeSetting $settings): array
    {
        $today = now()->startOfDay();

        if ($date->greaterThan($today) && !$settings->allow_future_date) {
            return [
                'allowed' => false,
                'reason'  => 'Overtime cannot be submitted for a future date.',
            ];
        }

        if ($settings->hasBackdateLimit()) {
            $earliest = $today->copy()->subDays($settings->max_backdate_days);

            if ($date->lessThan($earliest)) {
                return [
                    'allowed' => false,
                    'reason'  => "Overtime can only be submitted for the last {$settings->max_backdate_days} days.",
                ];
            }
        }

        if ($this->periodLocked($date)
            && $settings->locked_period_policy !== OvertimeSetting::LOCK_OFF) {
            return [
                'allowed' => false,
                'reason'  => 'The reporting period for that date is closed. Contact HR if you still need to claim it.',
            ];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Pengajuan lain yang jamnya bertumpuk.
     *
     * Tanggal sebelumnya ikut diperiksa karena pengajuan yang melewati tengah
     * malam berakhir di hari ini. Yang sudah dibatalkan atau ditolak diabaikan —
     * jamnya tidak lagi diklaim siapa pun.
     */
    private function findOverlap(
        int $employeeId,
        Carbon $date,
        string $start,
        string $end,
        bool $crosses,
        ?int $ignoreId = null
    ): ?OvertimeRequest {
        [$newStart, $newEnd] = $this->interval($date, $start, $end, $crosses);

        return OvertimeRequest::forEmployee($employeeId)
            ->whereIn('status', [
                OvertimeRequest::STATUS_SUBMITTED,
                OvertimeRequest::STATUS_IN_REVIEW,
                OvertimeRequest::STATUS_APPROVED,
            ])
            ->whereBetween('overtime_date', [
                $date->copy()->subDay()->toDateString(),
                $date->copy()->addDay()->toDateString(),
            ])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get()
            ->first(function (OvertimeRequest $other) use ($newStart, $newEnd) {
                [$otherStart, $otherEnd] = $this->interval(
                    Carbon::parse($other->overtime_date),
                    (string) $other->start_time,
                    (string) $other->end_time,
                    (bool) $other->crosses_midnight
                );

                // Dua rentang bertumpuk bila masing-masing mulai sebelum yang
                // lain berakhir. Bersentuhan di ujung (17:00-19:00 dan
                // 19:00-21:00) TIDAK dihitung bertumpuk.
                return $newStart->lessThan($otherEnd) && $otherStart->lessThan($newEnd);
            });
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function interval(Carbon $date, string $start, string $end, bool $crosses): array
    {
        $from = $date->copy()->setTimeFromTimeString(substr($start, 0, 5) . ':00');
        $to   = $date->copy()->setTimeFromTimeString(substr($end, 0, 5) . ':00');

        if ($crosses || $to->lessThanOrEqualTo($from)) {
            $to->addDay();
        }

        return [$from, $to];
    }

    private function attendanceFor(int $employeeId, Carbon $date): ?AttendanceRecord
    {
        return AttendanceRecord::where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date->toDateString())
            ->first();
    }

    /**
     * Sinyal yang perlu dilihat penyetuju.
     *
     * 🔴 Tidak satu pun dari sinyal ini menolak pengajuan. Semuanya hanya
     * menandai — pola yang sudah jadi sikap sistem ini sejak modul Attendance
     * (Keputusan W5 & D33): kumpulkan bukti, tandai yang janggal, serahkan
     * penilaian ke manusia.
     *
     * @return array<int, string>
     */
    private function buildFlags(
        int $employeeId,
        Carbon $date,
        int $duration,
        ?AttendanceRecord $record,
        OvertimeSetting $settings
    ): array {
        $flags = [];

        // Tanggal ke depan: pembanding absensi SENGAJA dilewati. Catatan
        // kehadiran untuk hari yang belum terjadi memang belum ada, dan
        // menandainya sebagai anomali akan membuat penanda itu selalu menyala
        // — penanda yang selalu menyala akhirnya diabaikan.
        if ($date->greaterThan(now()->startOfDay())) {
            $flags[] = OvertimeRequest::FLAG_FUTURE_CLAIM;
        } elseif (!$record) {
            $flags[] = OvertimeRequest::FLAG_NO_ATTENDANCE;
        } elseif (abs($duration - (int) $record->overtime_minutes) > $settings->mismatch_tolerance_minutes) {
            $flags[] = OvertimeRequest::FLAG_DURATION_MISMATCH;
        }

        if ($settings->hasMinimumDuration() && $duration < $settings->min_duration_minutes) {
            $flags[] = OvertimeRequest::FLAG_BELOW_MIN_DURATION;
        }

        if ($settings->hasDailyLimit()) {
            $sameDay = OvertimeRequest::forEmployee($employeeId)
                ->whereDate('overtime_date', $date->toDateString())
                ->whereIn('status', [
                    OvertimeRequest::STATUS_SUBMITTED,
                    OvertimeRequest::STATUS_IN_REVIEW,
                    OvertimeRequest::STATUS_APPROVED,
                ])
                ->sum('duration_minutes');

            if (($sameDay + $duration) > $settings->max_daily_minutes) {
                $flags[] = OvertimeRequest::FLAG_EXCEEDS_DAILY;
            }
        }

        if ($settings->hasWeeklyLimit()) {
            $week = OvertimeRequest::forEmployee($employeeId)
                ->whereBetween('overtime_date', [
                    $date->copy()->startOfWeek()->toDateString(),
                    $date->copy()->endOfWeek()->toDateString(),
                ])
                ->whereIn('status', [
                    OvertimeRequest::STATUS_SUBMITTED,
                    OvertimeRequest::STATUS_IN_REVIEW,
                    OvertimeRequest::STATUS_APPROVED,
                ])
                ->sum('duration_minutes');

            if (($week + $duration) > $settings->max_weekly_minutes) {
                $flags[] = OvertimeRequest::FLAG_EXCEEDS_WEEKLY;
            }
        }

        return array_values(array_unique($flags));
    }

    private function addFlag(OvertimeRequest $request, string $flag): void
    {
        $request->flags = collect($request->flags ?? [])
            ->push($flag)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Penyesuaian jam oleh penyetuju.
     *
     * Nilai semula disalin ke original_* HANYA sekali — bila jam disesuaikan
     * dua kali oleh dua penyetuju berbeda, yang tersimpan tetap angka yang
     * benar-benar diklaim karyawan, bukan hasil penyesuaian sebelumnya.
     */
    private function applyTimeAdjustment(
        OvertimeRequest $request,
        int $actorId,
        array $payload,
        OvertimeSetting $settings
    ): void {
        if (!$settings->allow_approver_adjust_time) {
            return;
        }

        $start = $payload['start_time'] ?? null;
        $end   = $payload['end_time'] ?? null;

        if (!$start || !$end) {
            return;
        }

        $start = substr($start, 0, 5);
        $end   = substr($end, 0, 5);

        if ($start === substr((string) $request->start_time, 0, 5)
            && $end === substr((string) $request->end_time, 0, 5)) {
            return; // tidak ada yang berubah
        }

        $crosses = $this->crossesMidnight($start, $end);

        if ($crosses && !$settings->allow_crosses_midnight) {
            return; // diamkan; validasi controller sudah menolaknya lebih dulu
        }

        if ($request->original_start_time === null) {
            $request->original_start_time = $request->start_time;
            $request->original_end_time   = $request->end_time;
        }

        $request->start_time       = $start;
        $request->end_time         = $end;
        $request->crosses_midnight = $crosses;
        $request->duration_minutes = $this->durationMinutes($start, $end);
        $request->adjusted_by      = $actorId;

        $this->addFlag($request, OvertimeRequest::FLAG_TIME_ADJUSTED);
    }

    /** Salin definisi langkah ke pengajuan — lihat docblock migrasinya. */
    private function snapshotSteps(OvertimeRequest $request, Collection $steps): void
    {
        foreach ($steps as $step) {
            OvertimeRequestApproval::create([
                'overtime_request_id'   => $request->id,
                'order_seq'             => $step->order_seq,
                'step_name'             => $step->name,
                'approver_type'         => $step->approver_type,
                'approver_role_id'      => $step->approver_role_id,
                'approver_employee_ids' => $step->approver_employee_ids,
                'status'                => OvertimeRequestApproval::STATUS_WAITING,
            ]);
        }
    }

    /**
     * Nomor dokumen berikutnya: OT/2026/08/00001.
     *
     * Dikunci di dalam transaksi supaya dua pengajuan bersamaan tidak mendapat
     * nomor yang sama. Indeks unik pada kolomnya menjadi jaring pengaman
     * terakhir bila penguncian gagal karena sebab di luar dugaan.
     */
    private function nextRequestNo(Carbon $date): string
    {
        $prefix = sprintf('OT/%s/%s/', $date->format('Y'), $date->format('m'));

        $last = OvertimeRequest::where('request_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('request_no')
            ->value('request_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /** @return array<int> */
    private function roleIdsOf(int $employeeId): array
    {
        return Employee::where('employee_id', $employeeId)->first()?->getRoleIds() ?? [];
    }

    /**
     * Beri tahu karyawan perkembangan pengajuannya.
     *
     * Dibungkus try/catch karena kegagalan mengirim notifikasi tidak boleh
     * membatalkan persetujuan yang sudah tersimpan — tetapi tetap dicatat ke
     * log supaya kegagalannya tidak hilang diam-diam (Keputusan D44).
     */
    private function notify(OvertimeRequest $request, string $outcome, ?string $notes = null): void
    {
        try {
            $date = Carbon::parse($request->overtime_date)->format('d M Y');

            $message = match ($outcome) {
                'approved'   => "Your overtime request {$request->request_no} for {$date} was approved.",
                'rejected'   => "Your overtime request {$request->request_no} for {$date} was rejected.",
                'progressed' => "Your overtime request {$request->request_no} for {$date} passed a review step.",
                default      => "Your overtime request {$request->request_no} for {$date} was updated.",
            };

            Notification::create([
                'employee_id'      => $request->employee_id,
                'type'             => 'overtime_' . $outcome,
                'from_employee_id' => session('user.id'),
                'preview'          => $message . ($notes ? ' Note: ' . $notes : ''),
                'link'             => '/general/my-overtime',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send overtime notification.', [
                'request_id' => $request->id,
                'outcome'    => $outcome,
                'message'    => $e->getMessage(),
            ]);
        }
    }
}
