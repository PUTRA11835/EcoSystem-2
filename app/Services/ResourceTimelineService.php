<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ResourceTimeline;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Query/sorting layer untuk halaman Reporting → Resource Timeline.
 *
 * Ditaruh di sini (bukan Controller/Blade) supaya logika grouping module +
 * urutan Lead bisa dipakai ulang (mis. untuk export Excel nanti) tanpa
 * duplikasi.
 */
class ResourceTimelineService
{
    /** Position yang masuk sebagai resource di timeline. */
    public const RESOURCE_POSITIONS = ['SAP CONSULTANT', 'PROJECT MANAGEMENT OFFICER'];

    /**
     * Query dasar: seluruh employee aktif dengan position di RESOURCE_POSITIONS
     * (SAP Consultant & Project Management Officer) yang tidak di-block dan
     * tidak kena deletion_flag.
     *
     * $homeBase opsional: batasi ke satu lokasi kantor (App\Enums\HomeBase).
     */
    public function consultantsQuery(?string $homeBase = null)
    {
        return Employee::with(['basicData', 'qualifications.module', 'ledModules.groups'])
            ->where('is_active', true)
            ->whereHas('basicData', function ($q) use ($homeBase) {
                // Blocked / deletion-flagged consultants are not resources anymore.
                $q->whereIn('position', self::RESOURCE_POSITIONS)
                  ->where('block', false)
                  ->where('deletion_flag', false);
                if ($homeBase) {
                    $q->where('home_base', $homeBase);
                }
            });
    }

    /**
     * Daftar ringkas consultant untuk dropdown (Create Timeline modal).
     * Return: [['employee_id' => .., 'name' => ..], ...] terurut nama A-Z.
     */
    public function consultantOptions(): array
    {
        return $this->consultantsQuery()->get()
            ->map(fn (Employee $emp) => [
                'employee_id' => $emp->employee_id,
                'name'        => $this->employeeName($emp),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Bangun data grid bulanan: daftar hari + baris per consultant, sudah
     * terurut & terkelompok sesuai aturan (group by Module A-Z, Lead di atas
     * tiap group, sisanya A-Z nama), dengan map tanggal -> lokasi.
     *
     * @return array{days: array<int,array{day:int,label:string,is_weekend:bool}>, rows: array}
     */
    public function buildGrid(int $month, int $year, ?string $homeBase = null): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd   = $monthStart->copy()->endOfMonth();

        $consultants = $this->consultantsQuery($homeBase)->get();
        $employeeIds = $consultants->pluck('employee_id')->all();

        $locationsByEmployee = ResourceTimeline::whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('id')
            ->get()
            ->groupBy('employee_id')
            // date => [location, ...] — a consultant can be on several projects
            // on the same day (overlapping assignments).
            ->map(fn ($rows) => $rows
                ->groupBy(fn (ResourceTimeline $rt) => $rt->date->format('Y-m-d'))
                ->map(fn ($day) => $day->pluck('location')->filter()->values()->all())
            );

        $rows = $consultants->map(function (Employee $emp) use ($locationsByEmployee) {
            $modules = $emp->qualifications
                ->map(fn ($q) => $q->module?->name)
                ->filter()
                ->unique()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            $leadModules = $emp->ledModules
                ->pluck('name')
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            // Status shows Module *Groups* the person leads (e.g. "Lead Technical")
            // instead of every led module, to keep the column short. A module
            // that belongs to no active group falls back to its own name.
            $leadLabels = $emp->ledModules
                ->flatMap(function ($module) {
                    $groups = $module->groups->where('is_active', true)->pluck('name');
                    return $groups->isNotEmpty() ? $groups : collect([$module->name]);
                })
                ->unique()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            $dates = $locationsByEmployee->get($emp->employee_id, collect());

            return [
                'employee_id'  => $emp->employee_id,
                'name'         => $this->employeeName($emp),
                'group_key'    => $modules->first(), // null -> sorted last
                'module_label' => $modules->implode(', '),
                'is_lead'      => $leadModules->isNotEmpty(),
                'status_label' => $leadLabels->map(fn ($g) => "Lead {$g}")->implode(', '),
                'dates'        => $dates->all(),
            ];
        });

        $sorted = $rows
            ->sortBy([
                fn ($a, $b) => strnatcasecmp($a['group_key'] ?? "\xFF", $b['group_key'] ?? "\xFF"),
                fn ($a, $b) => ($b['is_lead'] <=> $a['is_lead']), // leads first
                fn ($a, $b) => strnatcasecmp($a['name'], $b['name']),
            ])
            ->values()
            ->map(function ($row, $index) {
                $row['no'] = $index + 1;
                return $row;
            });

        return [
            'days' => $this->dayColumns($monthStart, $monthEnd),
            'rows' => $sorted->values()->all(),
        ];
    }

    /**
     * Ringkasan range untuk satu consultant (dipakai list "existing entries"
     * di Create Timeline modal). Hari-hari berurutan dengan lokasi sama
     * digabung jadi satu range. Consultant boleh punya beberapa lokasi di
     * tanggal yang sama (project overlap), jadi digabung per lokasi dulu —
     * range antar lokasi boleh saling tumpang-tindih.
     *
     * @return array<int,array{start:string,end:string,location:string}>
     */
    public function collapseToRanges(int $employeeId): array
    {
        $entries = ResourceTimeline::where('employee_id', $employeeId)
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->orderBy('date')
            ->get(['date', 'location'])
            ->groupBy('location');

        $ranges = [];

        foreach ($entries as $location => $rows) {
            $current = null;

            foreach ($rows as $entry) {
                if ($current && Carbon::parse($current['end'])->addDay()->isSameDay($entry->date)) {
                    $current['end'] = $entry->date->format('Y-m-d');
                    continue;
                }

                if ($current) {
                    $ranges[] = $current;
                }

                $current = [
                    'start'    => $entry->date->format('Y-m-d'),
                    'end'      => $entry->date->format('Y-m-d'),
                    'location' => (string) $location,
                ];
            }

            if ($current) {
                $ranges[] = $current;
            }
        }

        usort($ranges, fn ($a, $b) => [$a['start'], $a['end'], $a['location']] <=> [$b['start'], $b['end'], $b['location']]);

        return $ranges;
    }

    /**
     * Create/update: expand range jadi satu baris per tanggal PER LOKASI.
     * Lokasi lain di tanggal yang sama tidak ditimpa (project overlap tetap
     * terlihat dua-duanya). Location kosong/blank pada create berarti
     * "kosongkan" -> semua lokasi di range tsb dihapus.
     *
     * $previousStartDate/$previousEndDate/$previousLocation (opsional) =
     * range ASLI sebelum di-edit. Kalau diisi, range lama itu (hanya untuk
     * lokasi lama) dihapus dulu, baru range baru ditulis — supaya
     * menyusutkan/menggeser tanggal atau mengganti lokasi saat edit tidak
     * meninggalkan sisa hari lama, tanpa menyentuh assignment lokasi lain.
     */
    public function upsertRange(
        int $employeeId,
        string $startDate,
        string $endDate,
        ?string $location,
        ?string $previousStartDate = null,
        ?string $previousEndDate = null,
        ?string $previousLocation = null
    ): void {
        $location = trim((string) $location);

        DB::transaction(function () use ($employeeId, $startDate, $endDate, $location, $previousStartDate, $previousEndDate, $previousLocation) {
            if ($previousStartDate && $previousEndDate) {
                $this->deleteRange($employeeId, $previousStartDate, $previousEndDate, $previousLocation);
            }

            if ($location === '') {
                $this->deleteRange($employeeId, $startDate, $endDate);
                return;
            }

            foreach ($this->eachDate($startDate, $endDate) as $date) {
                ResourceTimeline::firstOrCreate([
                    'employee_id' => $employeeId,
                    'date'        => $date,
                    'location'    => $location,
                ]);
            }
        });
    }

    /**
     * Hapus range. Dengan $location hanya lokasi itu yang dihapus (tombol
     * delete di list entries); tanpa $location semua lokasi di range ikut.
     */
    public function deleteRange(int $employeeId, string $startDate, string $endDate, ?string $location = null): void
    {
        $query = ResourceTimeline::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate]);

        if ($location !== null && $location !== '') {
            $query->where('location', $location);
        }

        $query->delete();
    }

    private function employeeName(Employee $emp): string
    {
        return $emp->basicData?->full_name ?: $emp->eci;
    }

    /**
     * @return array<int,array{day:int,label:string,is_weekend:bool}>
     */
    private function dayColumns(Carbon $monthStart, Carbon $monthEnd): array
    {
        $days = [];
        $cursor = $monthStart->copy();

        while ($cursor->lte($monthEnd)) {
            $days[] = [
                'day'        => $cursor->day,
                'date'       => $cursor->toDateString(),
                'label'      => $cursor->format('D'),
                'is_weekend' => $cursor->isWeekend(),
            ];
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return \Generator<string>
     */
    private function eachDate(string $startDate, string $endDate): \Generator
    {
        $cursor = Carbon::parse($startDate)->startOfDay();
        $end    = Carbon::parse($endDate)->startOfDay();

        while ($cursor->lte($end)) {
            yield $cursor->toDateString();
            $cursor->addDay();
        }
    }
}
