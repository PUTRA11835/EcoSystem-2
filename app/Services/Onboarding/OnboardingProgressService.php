<?php

namespace App\Services\Onboarding;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Progres kelengkapan data master employee (menu Onboarding).
 *
 * Tidak ada tabel baru: seluruh angka dihitung dari data master yang sudah ada
 * (HC-D14). Logika penilaian ada di OnboardingRules (murni); kelas ini hanya
 * memuat data dari database.
 *
 * SET-BASED: satu kueri per tabel untuk SEMUA karyawan yang diminta, lalu
 * dikelompokkan di memori — bukan satu kueri per karyawan. Untuk ± 200 karyawan
 * itu 5 kueri, bukan ± 1.000.
 *
 * "Aktif" di sini = employee.is_active = 1 DAN employee_basic_data.deletion_flag = 0.
 * Definisi "aktif" di aplikasi belum seragam (sinkronisasi/06 §4, pertanyaan Q32);
 * ambang ini sengaja konservatif: karyawan yang sudah ditandai untuk dihapus
 * tidak perlu dikejar kelengkapan datanya.
 */
class OnboardingProgressService
{
    /**
     * Progres seluruh karyawan aktif (atau hanya id tertentu).
     *
     * @param  int[]|null  $onlyIds
     * @return array{employees: array<int,array>, summary: array<string,int>}
     */
    public function all(?array $onlyIds = null): array
    {
        $items  = config('hc_onboarding.items', []);
        $groups = config('hc_onboarding.groups', []);

        // H3.9: status kepegawaian dari profil HR. Dijaga bila tabelnya belum ada (kode dirilis sebelum
        // migrasi) agar halaman Onboarding tidak ikut rusak — kolom Status saja yang kosong.
        $hasProfile = Schema::hasTable('employee_hr_profile');

        $base = DB::table('employee as e')
            ->join('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->when($hasProfile, fn ($q) => $q->leftJoin('employee_hr_profile as hp', 'hp.employee_id', '=', 'e.employee_id'))
            ->where('e.is_active', 1)
            ->where(function ($q) {
                $q->where('b.deletion_flag', 0)->orWhereNull('b.deletion_flag');
            })
            ->when($onlyIds !== null, fn ($q) => $q->whereIn('e.employee_id', $onlyIds ?: [0]))
            ->select([
                'e.employee_id', 'e.eci',
                'b.first_name', 'b.last_name', 'b.nick_name',
                'b.position', 'b.department', 'b.employee_type',
                'b.gender', 'b.religion', 'b.marital_status',
                'b.birth_date', 'b.birth_place', 'b.since_date',
                $hasProfile ? 'hp.employment_status' : DB::raw('NULL as employment_status'),
                $hasProfile ? 'hp.locked_at' : DB::raw('NULL as locked_at'),
            ])
            ->orderBy('b.first_name')
            ->get();

        $ids = $base->pluck('employee_id')->all();

        $address = $this->groupBy('employee_address', $ids,
            ['employee_id', 'street', 'cell_phone', 'email_work']);
        $identification = $this->groupBy('employee_identification', $ids,
            ['employee_id', 'identification_type', 'identification_number']);
        $bank = $this->groupBy('employee_bank', $ids,
            ['employee_id', 'bank_name', 'account_number', 'account_holder']);
        $contract = $this->groupBy('employee_contract', $ids,
            ['employee_id', 'is_active', 'start_date']);

        $employees = [];
        $summary = [
            'employees' => 0, 'in_progress' => 0, 'complete' => 0,
            'items_total' => 0, 'items_done' => 0, 'items_remaining' => 0, 'percent' => 0,
        ];

        foreach ($base as $row) {
            $id = (int) $row->employee_id;

            $facts = [
                'employee_type'  => $row->employee_type,
                'basic'          => (array) $row,
                'address'        => $address[$id] ?? [],
                'identification' => $identification[$id] ?? [],
                'bank'           => $bank[$id] ?? [],
                'contract'       => $contract[$id] ?? [],
            ];

            $result = OnboardingRules::evaluate($items, $groups, $facts);

            $fullName = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            $employees[$id] = $result + [
                'employee_id' => $id,
                'eci'         => $row->eci,
                'name'        => $fullName !== '' ? $fullName : ($row->nick_name ?: $row->eci),
                'position'    => OnboardingRules::filled($row->position) ? $row->position : null,
                'department'  => OnboardingRules::filled($row->department) ? $row->department : null,
                'type'        => OnboardingRules::filled($row->employee_type) ? $row->employee_type : OnboardingRules::DEFAULT_TYPE,
                'join_date'   => $row->since_date,
                'employment_status' => $row->employment_status ?? null,
                'locked_at'   => $row->locked_at ?? null,
            ];

            $summary['employees']++;
            $summary[$result['status']]++;
            $summary['items_total'] += $result['total'];
            $summary['items_done']  += $result['done'];
        }

        $summary['items_remaining'] = $summary['items_total'] - $summary['items_done'];
        $summary['percent'] = $summary['items_total'] > 0
            ? (int) round(100 * $summary['items_done'] / $summary['items_total'])
            : 100;

        return ['employees' => $employees, 'summary' => $summary];
    }

    /** Progres satu karyawan, atau null bila tidak aktif / tidak ditemukan. */
    public function forEmployee(int $employeeId): ?array
    {
        return $this->all([$employeeId])['employees'][$employeeId] ?? null;
    }

    /**
     * Ambil baris tabel anak untuk sekumpulan karyawan, dikelompokkan per employee_id.
     *
     * @param  int[]  $ids
     * @param  string[]  $columns
     * @return array<int,array<int,array>>
     */
    private function groupBy(string $table, array $ids, array $columns): array
    {
        if (!$ids) {
            return [];
        }

        $grouped = [];
        // whereIn dipotong per 500 id agar aman terhadap batas placeholder.
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (DB::table($table)->whereIn('employee_id', $chunk)->get($columns) as $row) {
                $grouped[(int) $row->employee_id][] = (array) $row;
            }
        }

        return $grouped;
    }
}
