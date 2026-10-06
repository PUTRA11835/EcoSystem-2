<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\JoinDateService;
use App\Services\Onboarding\OnboardingProgressService;
use App\Services\HrProfile\ProfileLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Onboarding — progres kelengkapan data master employee (sisi HR).
 *
 * v1 TANPA tabel baru (HC-D14): daftar = SEMUA karyawan aktif beserta progresnya,
 * dihitung OnboardingProgressService dari data master. Halaman ini hanya MEMBACA;
 * melengkapi data dilakukan di halaman master employee (HR) atau My Profile
 * (pemilik data) lewat tautan "Open" pada tiap butir yang kurang.
 *
 * Dijaga slug `general.onboarding` (halaman ini menampilkan progres SEMUA
 * karyawan). Karyawan biasa melihat progres DIRINYA SENDIRI lewat banner di
 * My Profile, yang tidak memerlukan slug ini.
 */
class OnboardingController extends Controller
{
    private const PER_PAGE = 25;
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    private const BULK_LOCK_MAX = 500;

    public function index(Request $request, OnboardingProgressService $progress)
    {
        $data    = $progress->all();
        $filters = $this->filters($request);

        $rows = collect($data['employees'])
            ->filter(fn (array $e) => $this->matches($e, $filters))
            ->sort(fn (array $a, array $b) => $this->compare($a, $b, $filters['sort']))
            ->values();

        $perPage = (int) $request->query('per_page', self::PER_PAGE);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE;

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('hr-general.onboarding.index', [
            'rows'    => $paginator,
            'readyCount' => $this->readyCount($data['employees'], $filters),
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'summary' => $data['summary'],
            'filters' => $filters,
            'groups'  => config('hc_onboarding.groups'),
        ]);
    }

    /** Berapa profil "Ready to lock" pada filter yang sedang dipakai (sama dengan yang dikunci tombol massal). */
    private function readyCount(array $employees, array $filters): int
    {
        $f = array_merge($filters, ['lock' => 'ready', 'status' => 'all']);

        return collect($employees)->filter(fn (array $e) => $this->matches($e, $f))->count();
    }

    public function show(int $employeeId, OnboardingProgressService $progress)
    {
        $row = $progress->forEmployee($employeeId);

        // Karyawan nonaktif / ditandai hapus tidak masuk perhitungan (lihat service).
        abort_if($row === null, 404, 'Employee not found or not active.');

        return view('hr-general.onboarding.show', [
            'row'    => $row,
            'groups' => config('hc_onboarding.groups'),
        ]);
    }

    // ── Kunci profil (H3.11; HC-D29) ────────────────────────────────────────
    // Hanya POST (konvensi HR_General) + header AJAX. Izin: general.onboarding.lock / .unlock (di rute).

    public function lock(Request $request, int $employeeId, ProfileLockService $locks): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 400);
        }

        return $this->lockResponse($locks->lock($employeeId, (int) (session('user')['id'] ?? 0)));
    }

    public function unlock(Request $request, int $employeeId, ProfileLockService $locks): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 400);
        }

        return $this->lockResponse($locks->unlock($employeeId, (int) (session('user')['id'] ?? 0), (string) $request->input('reason', '')));
    }

    /** @param  array{ok: bool, code: string, message: string}  $r */
    private function lockResponse(array $r): JsonResponse
    {
        $status = $r['ok'] ? 200 : match ($r['code']) {
            'not_found' => 404,
            'already_locked', 'not_locked' => 409,
            default => 422,
        };

        return response()->json(['success' => $r['ok'], 'message' => $r['message'], 'code' => $r['code']], $status);
    }

    /**
     * Kunci massal: semua karyawan yang "Ready to lock" (progres 100%, belum terkunci) pada FILTER yang sedang
     * dipakai HR. Tetap memakai ProfileLockService::lock() per orang, jadi aturan, riwayat karyawan, dan pemeriksaan
     * "harus 100%" identik dengan tombol Verify & Lock satuan. Izin sama dengan satuan: general.onboarding.lock.
     */
    public function lockReady(Request $request, OnboardingProgressService $progress, ProfileLockService $locks): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 400);
        }

        $filters = $this->filters($request);
        $filters['lock'] = 'ready';                       // hanya yang siap
        $filters['status'] = 'all';
        $ids = collect($progress->all()['employees'])
            ->filter(fn (array $e) => $this->matches($e, $filters))
            ->pluck('employee_id')->take(self::BULK_LOCK_MAX)->all();

        $actor = (int) (session('user')['id'] ?? 0);
        $locked = 0;
        $skipped = 0;
        foreach ($ids as $id) {
            $r = $locks->lock((int) $id, $actor);
            $r['ok'] ? $locked++ : $skipped++;
        }

        $message = $locked . ' profile(s) verified and locked' . ($skipped ? ", {$skipped} skipped." : '.');

        return response()->json(['success' => true, 'message' => $message, 'data' => ['locked' => $locked, 'skipped' => $skipped]]);
    }

    // ── Alat join date untuk HR (HC-D64) ─────────────────────────────────────
    // Hanya POST + header AJAX. Izin: general.onboarding.join-date (di rute). Join date dikunci dari pegawai (HC-D62);
    // alat ini untuk MENGISI data lama yang kosong dan TIDAK PERNAH menimpa tanggal yang sudah ada.

    public function joinDatesSave(Request $request, JoinDateService $svc): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 400);
        }
        $data = $request->validate([
            'items'               => 'required|array|min:1|max:500',
            'items.*.employee_id' => 'required|integer|min:1',
            'items.*.date'        => 'required|string|max:30',
        ]);
        $r = $svc->setDates($data['items'], (int) (session('user')['id'] ?? 0), 'manual');

        return response()->json(['success' => true, 'message' => $this->joinDateMessage($r['set'], count($r['skipped'])), 'data' => $r]);
    }

    public function joinDatesPreview(Request $request, JoinDateService $svc): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 400);
        }
        $data = $request->validate(['csv' => 'required|string|max:200000']);

        return response()->json(['success' => true, 'data' => $svc->preview($data['csv'])]);
    }

    public function joinDatesImport(Request $request, JoinDateService $svc): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 400);
        }
        $data = $request->validate(['csv' => 'required|string|max:200000']);
        // Jangan percaya pratinjau dari klien: baca ulang teksnya di sini dan terapkan HANYA baris berstatus ok.
        $preview = $svc->preview($data['csv']);
        $items = collect($preview['rows'])->where('status', 'ok')->map(fn ($r) => ['employee_id' => $r['employee_id'], 'date' => $r['date']])->values()->all();
        if (!$items) {
            return response()->json(['success' => false, 'message' => 'There are no valid rows to import.', 'data' => $preview], 422);
        }
        $r = $svc->setDates($items, (int) (session('user')['id'] ?? 0), 'csv import');

        return response()->json(['success' => true, 'message' => $this->joinDateMessage($r['set'], count($r['skipped']) + $preview['summary']['skipped'] + $preview['summary']['errors']), 'data' => $r + ['preview' => $preview['summary']]]);
    }

    public function joinDatesRemind(Request $request, JoinDateService $svc): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 400);
        }
        $data = $request->validate(['employee_ids' => 'required|array|min:1|max:500', 'employee_ids.*' => 'integer|min:1']);
        $r = $svc->remind($data['employee_ids'], (int) (session('user')['id'] ?? 0));
        $msg = $r['sent'] . ' reminder(s) sent' . ($r['skipped'] ? ', ' . count($r['skipped']) . ' skipped (already has a date or was reminded in the last ' . JoinDateService::REMINDER_COOLDOWN_DAYS . ' days).' : '.');

        return response()->json(['success' => true, 'message' => $msg, 'data' => $r]);
    }

    private function joinDateMessage(int $set, int $skipped): string
    {
        return $set . ' join date(s) saved' . ($skipped ? ", {$skipped} skipped." : '.');
    }

    // ── Penyaringan & pengurutan ────────────────────────────────────────────

    /** @return array{search:string,status:string,type:string,missing:string,lock:string,sort:string} */
    private function filters(Request $request): array
    {
        $groups = array_keys(config('hc_onboarding.groups'));

        $status = (string) $request->query('status', 'all');
        $type   = (string) $request->query('type', 'all');
        $missing = (string) $request->query('missing', 'all');
        $sort   = (string) $request->query('sort', 'progress_asc');
        $lock   = (string) $request->query('lock', 'all');

        $status = in_array($status, ['all', 'in_progress', 'complete'], true) ? $status : 'all';
        $lock   = in_array($lock, ['all', 'ready', 'locked'], true) ? $lock : 'all';
        // "Ready to lock"/"Locked" hanya berisi karyawan yang progresnya 100%; filter status bawaan "In progress"
        // akan selalu mengosongkan hasilnya (bertentangan). Saat filter kunci dipakai, status "In progress" diabaikan.
        if ($lock !== 'all' && $status === 'in_progress') {
            $status = 'all';
        }

        return [
            'search'  => trim((string) $request->query('search', '')),
            'status'  => $status,
            'type'    => in_array($type, ['all', 'Internal', 'External'], true) ? $type : 'all',
            // Kelompok (profile/payroll/bpjs/contract) ATAU butir tertentu yang punya filter sendiri (join_date: HC-D62).
            'missing' => in_array($missing, array_merge($groups, ['join_date']), true) ? $missing : 'all',
            'lock'    => $lock,
            'sort'    => in_array($sort, ['progress_asc', 'progress_desc', 'name', 'join_date'], true) ? $sort : 'progress_asc',
        ];
    }

    private function matches(array $e, array $f): bool
    {
        if ($f['status'] !== 'all' && $e['status'] !== $f['status']) {
            return false;
        }
        if ($f['type'] !== 'all' && $e['type'] !== $f['type']) {
            return false;
        }
        if ($f['missing'] === 'join_date') {
            // Hanya karyawan yang butir join date-nya berlaku (Internal) dan belum terisi.
            $lacksJoinDate = false;
            foreach ($e['items'] ?? [] as $item) {
                if ($item['key'] === 'join_date' && !$item['done']) {
                    $lacksJoinDate = true;
                    break;
                }
            }
            if (!$lacksJoinDate) {
                return false;
            }
        } elseif ($f['missing'] !== 'all' && empty($e['groups'][$f['missing']]['missing'])) {
            return false;
        }
        // "ready" = progres 100% tetapi belum dikunci (juga yang dibuka kembali dan belum dikunci ulang).
        if ($f['lock'] === 'locked' && empty($e['locked_at'])) {
            return false;
        }
        if ($f['lock'] === 'ready' && ($e['status'] !== 'complete' || !empty($e['locked_at']))) {
            return false;
        }
        if ($f['search'] !== '') {
            $haystack = mb_strtolower(implode(' ', [$e['name'], $e['eci'], $e['position'], $e['department']]));
            if (!str_contains($haystack, mb_strtolower($f['search']))) {
                return false;
            }
        }

        return true;
    }

    private function compare(array $a, array $b, string $sort): int
    {
        return match ($sort) {
            'progress_desc' => [$b['percent'], $a['name']] <=> [$a['percent'], $b['name']],
            'name'          => strcasecmp($a['name'], $b['name']),
            // Tanggal kosong ditaruh paling akhir.
            'join_date'     => [$a['join_date'] === null, $a['join_date']] <=> [$b['join_date'] === null, $b['join_date']],
            default         => [$a['percent'], $a['name']] <=> [$b['percent'], $b['name']],
        };
    }
}
