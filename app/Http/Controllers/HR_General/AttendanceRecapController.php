<?php

namespace App\Http\Controllers\HR_General;

use App\Exports\AttendanceDailyExport;
use App\Exports\AttendanceMonthlyExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\Branch;
use App\Models\Employee;
use App\Services\HolidayService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Rekap presensi untuk HR: harian (default) dan bulanan.
 *
 * 🔴 ANGGARAN QUERY: halaman ini merender puluhan karyawan × 31 kolom, jadi
 * syaratnya bukan "sedikit query" melainkan **jumlah query yang KONSTAN** —
 * tidak bertambah seiring jumlah baris. Terukur 6–7 query per halaman: satu
 * untuk daftar karyawan, satu untuk catatan presensi, satu untuk hari libur,
 * sisanya eager load relasi. Angkanya sama pada halaman 1 maupun halaman 3.
 *
 * Yang DILARANG adalah query di dalam loop Blade: pada 207 karyawan, satu
 * query per sel berarti ribuan query per halaman. Karena itu seluruh
 * pencocokan tanggal→catatan dilakukan di PHP atas hasil satu query.
 */
class AttendanceRecapController extends Controller
{
    /** Baris karyawan per halaman pada rekap bulanan. */
    private const MONTHLY_PER_PAGE = 25;

    // ── Rekap harian ────────────────────────────────────────────────────────

    public function daily(Request $request)
    {
        $filters = $this->dailyFilters($request);
        $records = $this->dailyRecords($filters);

        return view('hr-general.attendance.daily', [
            'records'     => $records,
            'filters'     => $filters,
            'departments' => $this->departmentOptions(),
            'summary'     => $this->dailySummary($records),
        ]);
    }

    public function exportDaily(Request $request)
    {
        $filters = $this->dailyFilters($request);
        $records = $this->dailyRecords($filters);

        return Excel::download(
            new AttendanceDailyExport($records, $filters['date']),
            'attendance-daily-' . $filters['date']->format('Y-m-d') . '.xlsx'
        );
    }

    // ── Rekap bulanan ───────────────────────────────────────────────────────

    public function monthly(Request $request, HolidayService $holidays)
    {
        $filters = $this->monthlyFilters($request);
        $data    = $this->monthlyMatrix($filters, $holidays);

        return view('hr-general.attendance.monthly', array_merge($data, [
            'filters'  => $filters,
            'branches' => Branch::active()->orderBy('name')->get(['id', 'name']),
        ]));
    }

    public function exportMonthly(Request $request, HolidayService $holidays)
    {
        $filters = $this->monthlyFilters($request);
        $data    = $this->monthlyMatrix($filters, $holidays, forExport: true);

        return Excel::download(
            new AttendanceMonthlyExport($data['rows'], $data['days'], $filters['month']),
            'attendance-monthly-' . $filters['month']->format('Y-m') . '.xlsx'
        );
    }

    // ── internal: harian ────────────────────────────────────────────────────

    /** @return array{date: Carbon, department: string, status: string, search: string} */
    private function dailyFilters(Request $request): array
    {
        return [
            'date'       => $this->parseDate($request->query('date')) ?? now(),
            'department' => (string) $request->query('department', ''),
            'status'     => (string) $request->query('status', ''),
            'search'     => trim((string) $request->query('search', '')),
        ];
    }

    private function dailyRecords(array $filters): Collection
    {
        return AttendanceRecord::query()
            ->with([
                'employee.basicData',
                'shift',
                'checkInBranch:id,name',
                'checkOutBranch:id,name',
                'checkInProjectSite:id,name',
                'checkOutProjectSite:id,name',
            ])
            ->whereDate('attendance_date', $filters['date']->toDateString())
            ->when($filters['status'] !== '', fn ($q) => $q->where('day_status', $filters['status']))
            ->when($filters['department'] !== '', fn ($q) => $q->whereHas(
                'employee.basicData',
                fn ($b) => $b->where('department', $filters['department'])
            ))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($inner) use ($search) {
                    $inner->whereHas('employee', fn ($e) => $e->where('eci', 'like', "%{$search}%"))
                          ->orWhereHas('employee.basicData', fn ($b) => $b
                              ->where('nick_name', 'like', "%{$search}%")
                              ->orWhere('position', 'like', "%{$search}%")
                              ->orWhere('department', 'like', "%{$search}%"));
                });
            })
            ->get()
            ->sortBy(fn ($r) => $r->employee?->basicData?->nick_name ?? '')
            ->values();
    }

    /**
     * Kartu ringkasan.
     *
     * Absent / Sick / Leave sengaja dihitung dari `day_status` dan akan tetap 0
     * sampai modul Cuti ada — sistem belum dapat membedakan alpa dari cuti,
     * dan menebaknya dari "karyawan aktif dikurangi yang hadir" akan menuduh
     * seluruh karyawan yang sedang cuti sebagai alpa.
     */
    private function dailySummary(Collection $records): array
    {
        return [
            'recorded' => $records->count(),
            'present'  => $records->whereIn('day_status', [AttendanceRecord::STATUS_PRESENT, AttendanceRecord::STATUS_LATE])->count(),
            'late'     => $records->where('late_minutes', '>', 0)->count(),
            'absent'   => $records->where('day_status', AttendanceRecord::STATUS_ABSENT)->count(),
            'sick'     => $records->where('day_status', AttendanceRecord::STATUS_SICK)->count(),
            'leave'    => $records->where('day_status', AttendanceRecord::STATUS_LEAVE)->count(),
        ];
    }

    /** Daftar departemen untuk dropdown filter. */
    private function departmentOptions(): Collection
    {
        return Employee::query()
            ->join('employee_basic_data as b', 'b.employee_id', '=', 'employee.employee_id')
            ->whereNotNull('b.department')
            ->where('b.department', '!=', '')
            ->distinct()
            ->orderBy('b.department')
            ->pluck('b.department');
    }

    // ── internal: bulanan ───────────────────────────────────────────────────

    /** @return array{month: Carbon, search: string, branch: string} */
    private function monthlyFilters(Request $request): array
    {
        $raw   = (string) $request->query('month', '');
        $month = $raw !== '' ? $this->parseDate($raw . '-01') : null;

        return [
            'month'  => ($month ?? now())->startOfMonth(),
            'search' => trim((string) $request->query('search', '')),
            'branch' => (string) $request->query('branch', ''),
        ];
    }

    /**
     * Matriks karyawan × tanggal.
     *
     * Tepat tiga query: karyawan, catatan presensi, hari libur.
     */
    private function monthlyMatrix(array $filters, HolidayService $holidays, bool $forExport = false): array
    {
        $month     = $filters['month'];
        $daysCount = $month->daysInMonth;
        $start     = $month->copy()->startOfMonth();
        $end       = $month->copy()->endOfMonth();

        // Query 1 — karyawan
        $employeeQuery = Employee::query()
            ->with('basicData:employee_id,nick_name,department,position,home_base')
            ->where('is_active', true)
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($inner) use ($search) {
                    $inner->where('eci', 'like', "%{$search}%")
                          ->orWhereHas('basicData', fn ($b) => $b->where('nick_name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('eci');

        // Ekspor mengambil seluruh baris; layar dipaginasi agar tetap ringan.
        $employees = $forExport
            ? $employeeQuery->get()
            : $employeeQuery->paginate(self::MONTHLY_PER_PAGE)->withQueryString();

        // pluck() bekerja pada Collection maupun paginator — paginator
        // meneruskan pemanggilan ke koleksi di dalamnya. Memakai items() akan
        // gagal pada jalur ekspor yang mengambil seluruh baris tanpa paginasi.
        $employeeIds = $employees->pluck('employee_id');

        // Query 2 — catatan presensi bulan itu, hanya untuk karyawan di halaman ini
        $records = AttendanceRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->when($filters['branch'] !== '', fn ($q) => $q->where('check_in_branch_id', $filters['branch']))
            ->get(['employee_id', 'attendance_date', 'check_in_at', 'check_out_at', 'day_status', 'late_minutes', 'work_minutes']);

        // Diagregasi di PHP: [employee_id][hari] => catatan
        $matrix = [];
        foreach ($records as $record) {
            $matrix[$record->employee_id][(int) $record->attendance_date->format('j')] = $record;
        }

        // Query 3 — hari libur
        $holidayDates = collect($holidays->getHolidayDates((int) $month->format('Y'), (int) $month->format('Y')))
            ->filter(fn ($d) => str_starts_with($d, $month->format('Y-m')))
            ->map(fn ($d) => (int) substr($d, 8, 2))
            ->values()
            ->all();

        // Metadata tiap kolom tanggal
        $days = [];
        for ($day = 1; $day <= $daysCount; $day++) {
            $date      = $month->copy()->day($day);
            $isWeekend = in_array((int) $date->format('N'), [6, 7], true);
            $isHoliday = in_array($day, $holidayDates, true);

            $days[$day] = [
                'day'        => $day,
                'initial'    => mb_substr($date->translatedFormat('l'), 0, 1),
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'is_workday' => !$isWeekend && !$isHoliday,
            ];
        }

        $workDays = collect($days)->where('is_workday', true)->count();

        // Baris siap render
        $rows = [];
        foreach ($employees as $employee) {
            $cells   = $matrix[$employee->employee_id] ?? [];
            $present = count($cells);

            $rows[] = [
                'employee' => $employee,
                'cells'    => $cells,
                'present'  => $present,
                'complete' => collect($cells)->filter(fn ($r) => $r->check_out_at !== null)->count(),
            ];
        }

        $totalPresent  = collect($rows)->sum('present');
        $totalComplete = collect($rows)->sum('complete');
        $employeeCount = count($rows);

        return [
            'employees' => $employees,
            'rows'      => $rows,
            'days'      => $days,
            'stats'     => [
                'employees'       => $employeeCount,
                'work_days'       => $workDays,
                'average_present' => $employeeCount > 0 ? round($totalPresent / $employeeCount, 1) : 0,
                'average_complete'=> $employeeCount > 0 ? round($totalComplete / $employeeCount, 1) : 0,
            ],
        ];
    }

    /** Parse tanggal tanpa melempar galat pada masukan yang tidak wajar. */
    private function parseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            // Masukan tanggal yang rusak tidak boleh menampilkan halaman galat;
            // pemanggil memakai nilai bawaan.
            return null;
        }
    }
}
