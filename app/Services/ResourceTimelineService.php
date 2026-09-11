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
    /**
     * Query dasar: seluruh employee aktif dengan position "SAP CONSULTANT".
     * Sama persis filter yang sudah dipakai ConsultantWorkloadController@list.
     *
     * $homeBase opsional: batasi ke satu lokasi kantor (App\Enums\HomeBase).
     */
    public function consultantsQuery(?string $homeBase = null)
    {
        return Employee::with(['basicData', 'qualifications.module', 'ledModules'])
            ->where('is_active', true)
            ->whereHas('basicData', function ($q) use ($homeBase) {
                $q->byPosition('SAP CONSULTANT');
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
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn (ResourceTimeline $rt) => [
                $rt->date->format('Y-m-d') => $rt->location,
            ]));

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

            $dates = $locationsByEmployee->get($emp->employee_id, collect());

            return [
                'employee_id'  => $emp->employee_id,
                'name'         => $this->employeeName($emp),
                'group_key'    => $modules->first(), // null -> sorted last
                'module_label' => $modules->implode(', '),
                'is_lead'      => $leadModules->isNotEmpty(),
                'status_label' => $leadModules->map(fn ($m) => "Lead {$m}")->implode(', '),
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
     * digabung jadi satu range.
     *
     * @return array<int,array{start:string,end:string,location:string}>
     */
    public function collapseToRanges(int $employeeId): array
    {
        $entries = ResourceTimeline::where('employee_id', $employeeId)
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->orderBy('date')
            ->get(['date', 'location']);

        $ranges  = [];
        $current = null;

        foreach ($entries as $entry) {
            if ($current
                && $current['location'] === $entry->location
                && Carbon::parse($current['end'])->addDay()->isSameDay($entry->date)
            ) {
                $current['end'] = $entry->date->format('Y-m-d');
                continue;
            }

            if ($current) {
                $ranges[] = $current;
            }

            $current = [
                'start'    => $entry->date->format('Y-m-d'),
                'end'      => $entry->date->format('Y-m-d'),
                'location' => $entry->location,
            ];
        }

        if ($current) {
            $ranges[] = $current;
        }

        return $ranges;
    }

    /**
     * Create/update: expand range jadi satu baris per tanggal. Location
     * kosong/blank berarti "kosongkan" -> baris di range tsb dihapus.
     *
     * $previousStartDate/$previousEndDate (opsional) = range ASLI sebelum
     * di-edit. Kalau diisi, bagian dari range lama yang jatuh DI LUAR range
     * baru ikut dihapus — supaya menyusutkan/menggeser tanggal saat edit
     * (bukan sekadar menambah assignment baru yang overlap) benar-benar
     * mengosongkan sisa hari yang tidak lagi terpakai, bukan meninggalkan
     * location lama nyangkut di sana.
     */
    public function upsertRange(
        int $employeeId,
        string $startDate,
        string $endDate,
        ?string $location,
        ?string $previousStartDate = null,
        ?string $previousEndDate = null
    ): void {
        $location = trim((string) $location);

        DB::transaction(function () use ($employeeId, $startDate, $endDate, $location, $previousStartDate, $previousEndDate) {
            if ($location === '') {
                $this->deleteRange($employeeId, $startDate, $endDate);
            } else {
                foreach ($this->eachDate($startDate, $endDate) as $date) {
                    ResourceTimeline::updateOrCreate(
                        ['employee_id' => $employeeId, 'date' => $date],
                        ['location' => $location]
                    );
                }
            }

            if ($previousStartDate && $previousEndDate) {
                ResourceTimeline::where('employee_id', $employeeId)
                    ->whereBetween('date', [$previousStartDate, $previousEndDate])
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->where('date', '<', $startDate)->orWhere('date', '>', $endDate);
                    })
                    ->delete();
            }
        });
    }

    public function deleteRange(int $employeeId, string $startDate, string $endDate): void
    {
        ResourceTimeline::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->delete();
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
