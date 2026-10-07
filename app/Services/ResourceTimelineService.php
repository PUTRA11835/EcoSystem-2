<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
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
    public const RESOURCE_POSITIONS = ['SAP CONSULTANT', 'SALESFORCE CONSULTANT', 'PROJECT MANAGEMENT OFFICER'];

    /**
     * Query dasar: seluruh employee aktif dengan position di RESOURCE_POSITIONS
     * (SAP Consultant, Salesforce Consultant & Project Management Officer) yang tidak di-block dan
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
     * Customer code aktif untuk dropdown Location (Create Timeline modal).
     * Return: ['ADHI', 'AIRNAV', ...] terurut A-Z.
     */
    public function customerCodeOptions(): array
    {
        return Customer::where('is_active', true)
            ->whereNotNull('customer_code')
            ->where('customer_code', '!=', '')
            ->distinct()
            ->orderBy('customer_code')
            ->pluck('customer_code')
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

        return [
            'days' => $this->dayColumns($monthStart, $monthEnd),
            'rows' => $this->gridRows($monthStart, $monthEnd, $homeBase),
        ];
    }

    /**
     * Grid tahunan: baris yang sama (urutan & grouping identik dengan bulanan),
     * tapi per bulan diringkas jadi
     * [['location' => 'IHC', 'segments' => [[1, 15], [20, 22]]], ...]
     * — segments = rentang hari berurutan (tgl awal, tgl akhir) per customer
     * code di bulan itu, supaya UI bisa menggambar fill sesuai posisi harinya.
     * Urut berdasarkan hari mulai paling awal.
     *
     * @return array{rows: array}
     */
    public function buildYearGrid(int $year, ?string $homeBase = null): array
    {
        $rows = $this->gridRows(
            Carbon::create($year, 1, 1)->startOfDay(),
            Carbon::create($year, 12, 31)->endOfDay(),
            $homeBase
        );

        $rows = array_map(function (array $row) {
            $months = [];
            foreach ($row['dates'] as $date => $locations) {
                $m = (int) substr($date, 5, 2);
                foreach ($locations as $location) {
                    $months[$m][$location][] = (int) substr($date, 8, 2);
                }
            }

            $row['months'] = [];
            foreach ($months as $m => $byLocation) {
                $items = [];
                foreach ($byLocation as $location => $days) {
                    sort($days);
                    $segments = [];
                    foreach ($days as $day) {
                        $last = count($segments) - 1;
                        if ($last >= 0 && $segments[$last][1] + 1 === $day) {
                            $segments[$last][1] = $day;
                        } else {
                            $segments[] = [$day, $day];
                        }
                    }
                    $items[] = ['location' => (string) $location, 'segments' => $segments];
                }
                usort($items, fn ($a, $b) => [$a['segments'][0][0], $a['location']] <=> [$b['segments'][0][0], $b['location']]);
                $row['months'][$m] = $items;
            }
            unset($row['dates']);

            return $row;
        }, $rows);

        return ['rows' => $rows];
    }

    /**
     * Baris per consultant (sudah terurut & ber-nomor) dengan map tanggal ->
     * lokasi untuk rentang $rangeStart..$rangeEnd.
     */
    private function gridRows(Carbon $rangeStart, Carbon $rangeEnd, ?string $homeBase): array
    {
        $monthStart = $rangeStart;
        $monthEnd   = $rangeEnd;

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

        return $sorted->values()->all();
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
     *
     * Audit Log: SATU baris per aksi (bukan per hari) — siapa, consultant mana,
     * customer apa, tanggal berapa, dan nilai lama -> baru.
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
        $isEdit   = $previousStartDate && $previousEndDate;

        $outcome = DB::transaction(function () use ($employeeId, $startDate, $endDate, $location, $previousStartDate, $previousEndDate, $previousLocation, $isEdit) {
            $removedPrev = $isEdit
                ? $this->removeRange($employeeId, $previousStartDate, $previousEndDate, $previousLocation)
                : null;

            if ($location === '') {
                return ['clear', $removedPrev, $this->removeRange($employeeId, $startDate, $endDate), 0];
            }

            $created = 0;
            foreach ($this->eachDate($startDate, $endDate) as $date) {
                $row = ResourceTimeline::firstOrCreate([
                    'employee_id' => $employeeId,
                    'date'        => $date,
                    'location'    => $location,
                ]);
                if ($row->wasRecentlyCreated) {
                    $created++;
                }
            }

            return ['save', $removedPrev, null, $created];
        });

        // Audited after commit; AuditLog::record() never throws.
        [$kind, $removedPrev, $cleared, $created] = $outcome;

        if ($kind === 'clear') {
            $removed = array_values(array_filter([$removedPrev, $cleared], fn ($r) => $r && $r['days'] > 0));
            if ($removed) {
                $text = implode('; ', array_map(fn ($r) => $this->describeRemoved($r), $removed));
                $this->audit($employeeId, 'deleted', "cleared timeline {$text}", ['removed' => $removed], null);
            }

            return;
        }

        $new = ['customer_code' => $location, 'start_date' => $startDate, 'end_date' => $endDate, 'days_added' => $created];

        if ($isEdit) {
            $unchanged = $previousLocation === $location && $previousStartDate === $startDate && $previousEndDate === $endDate;
            if (!$unchanged) {
                $old = ['customer_code' => $previousLocation, 'start_date' => $previousStartDate, 'end_date' => $previousEndDate,
                        'days_removed' => $removedPrev['days'] ?? 0];

                $this->audit(
                    $employeeId,
                    'updated',
                    sprintf('changed timeline %s %s → %s %s', $previousLocation ?: 'all customers',
                        $this->describeSpan($previousStartDate, $previousEndDate), $location, $this->describeSpan($startDate, $endDate)),
                    $old,
                    $new
                );
            }

            return;
        }

        if ($created > 0) {
            $this->audit(
                $employeeId,
                'created',
                sprintf('added timeline %s %s (%d day%s)', $location, $this->describeSpan($startDate, $endDate), $created, $created === 1 ? '' : 's'),
                null,
                $new
            );
        }
    }

    /**
     * Hapus range. Dengan $location hanya lokasi itu yang dihapus (tombol
     * delete di list entries); tanpa $location semua lokasi di range ikut.
     * Masuk Audit Log (satu baris) bila ada hari yang benar-benar terhapus.
     */
    public function deleteRange(int $employeeId, string $startDate, string $endDate, ?string $location = null): void
    {
        $removed = $this->removeRange($employeeId, $startDate, $endDate, $location);

        if ($removed['days'] > 0) {
            $this->audit($employeeId, 'deleted', 'deleted timeline ' . $this->describeRemoved($removed), ['removed' => [$removed]], null);
        }
    }

    /**
     * Hapus tanpa audit (upsertRange mengaudit sendiri).
     *
     * @return array{start:string,end:string,days:int,locations:array<string,int>}
     */
    private function removeRange(int $employeeId, string $startDate, string $endDate, ?string $location = null): array
    {
        $query = ResourceTimeline::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate]);

        if ($location !== null && $location !== '') {
            $query->where('location', $location);
        }

        $locations = (clone $query)
            ->selectRaw('location, COUNT(*) as days')
            ->groupBy('location')
            ->pluck('days', 'location')
            ->map(fn ($d) => (int) $d)
            ->all();

        $query->delete();

        return ['start' => $startDate, 'end' => $endDate, 'days' => array_sum($locations), 'locations' => $locations];
    }

    /** "IHC (12 days), PJT (3 days) 01 Oct 2026 – 15 Oct 2026" */
    private function describeRemoved(array $removed): string
    {
        $what = [];
        foreach ($removed['locations'] as $loc => $days) {
            $what[] = sprintf('%s (%d day%s)', $loc, $days, $days === 1 ? '' : 's');
        }

        return implode(', ', $what) . ' ' . $this->describeSpan($removed['start'], $removed['end']);
    }

    private function describeSpan(string $start, string $end): string
    {
        $s = Carbon::parse($start)->format('d M Y');
        $e = Carbon::parse($end)->format('d M Y');

        return $start === $end ? $s : "{$s} – {$e}";
    }

    /** One Audit Log row for a Resource Timeline action, attributed to the logged-in employee. */
    private function audit(int $employeeId, string $event, string $description, ?array $old, ?array $new): void
    {
        $emp   = Employee::with('basicData')->find($employeeId);
        $label = $emp ? $this->employeeName($emp) : "Employee #{$employeeId}";

        AuditLog::recordAction(
            module: 'Reporting',
            auditableType: 'ResourceTimeline',
            auditableId: $employeeId,
            event: $event,
            recordLabel: $label,
            description: "{$description} - {$label}",
            old: $old,
            new: $new
        );
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
