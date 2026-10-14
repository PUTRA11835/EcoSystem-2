<?php

namespace Database\Seeders;

use App\Models\EmployeeBasicData;
use App\Models\EmployeeContract;
use App\Services\Contracts\ContractService;
use App\Services\Letters\LetterService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dua karyawan uji untuk menguji alur Offering/Contract → Salary → Payroll (Commercial & Finance):
 *
 *   XX UJI PAYROLL (XXUJI03)  — SUDAH berkontrak: data induk lengkap, PKWT berstatus Active (gaji pokok + 2 tunjangan), gaji
 *                               ikut tersalin ke Salary Components lewat sinkron kontrak, PTKP K/1, BPJS aktif, ikut payroll.
 *   WW UJI PAYROLL (WWUJI04)  — SIAP dikontrak: data induk lengkap (lolos Contract Readiness 100%), TANPA kontrak, TANPA gaji,
 *                               belum ikut payroll. Buat kontraknya sendiri di HR & General → Contract, lalu lihat gajinya
 *                               muncul di Master Employee → Contract → Salary Components.
 *
 *   php artisan db:seed --class=PayrollUjiSeeder
 *   PAYROLL_UJI_CLEAN=1 php artisan db:seed --class=PayrollUjiSeeder   # hapus HANYA data kedua karyawan ini
 *
 * Aman diulang (kontrak bertanda dibuat ulang; data induk memakai updateOrInsert), menolak production, tidak ada di
 * DatabaseSeeder. Pagar kesiapan data kontrak dimatikan HANYA selama seeder berjalan. Berkas KTP/KK hanya penanda (tanpa file).
 */
class PayrollUjiSeeder extends Seeder
{
    private const MARK = 'Seeder uji (Payroll)';
    private const PEOPLE = [
        'XXUJI03' => ['first' => 'XX Uji', 'last' => 'Payroll', 'nick' => 'XX Uji', 'gender' => 'Male', 'ptkp' => 'K/1', 'deps' => 1, 'bank' => '7001203', 'join' => '2026-01-01'],
        'WWUJI04' => ['first' => 'WW Uji', 'last' => 'Payroll', 'nick' => 'WW Uji', 'gender' => 'Female', 'ptkp' => 'TK/0', 'deps' => 0, 'bank' => '7001204', 'join' => '2026-10-01'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->say('Dihentikan: data uji payroll tidak boleh dibuat di production.');

            return;
        }

        if (env('PAYROLL_UJI_CLEAN')) {
            $this->clean();

            return;
        }

        $ids = [];
        foreach (self::PEOPLE as $eci => $p) {
            $ids[$eci] = $this->employee($eci, $p);
            $this->masterData($ids[$eci], $eci, $p);
        }

        $this->clean(true);   // kontrak bertanda lama + baris gaji turunannya, supaya aman diulang
        config(['hc_contract.readiness_gate' => 'off']);

        $svc = app(ContractService::class);
        $signerId = LetterService::signatoryOptions()->first()['id'] ?? '';
        $svc->create($ids['XXUJI03'], [
            'contract_type' => 'PKWT', 'status' => 'active', 'position' => 'SUPPORT CONSULTANT',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'signed_date' => '2025-12-29', 'work_location' => 'Yogyakarta',
            'salary' => '7.500.000,00', 'components' => [['name' => 'Tunjangan Transportasi', 'amount' => '500.000,00'], ['name' => 'Uang Makan', 'amount' => '750.000,00']],
            'signatory_employee_id' => $signerId, 'notes' => self::MARK . ' — jangan dipakai sebagai kontrak sungguhan.',
        ], (int) ($signerId ?: 1), true);

        $this->say('Selesai.');
        $this->say('  XX Uji Payroll (XXUJI03): kontrak PKWT Active + gaji di Salary Components + ikut payroll → langsung bisa di-Calculate di Payroll.');
        $this->say('  WW Uji Payroll (WWUJI04): data lengkap, tanpa kontrak & gaji → buat kontraknya sendiri di HR & General → Contract.');
        $this->say('Bersihkan: PAYROLL_UJI_CLEAN=1 php artisan db:seed --class=PayrollUjiSeeder');
    }

    private function employee(string $eci, array $p): int
    {
        $now = now();
        $id = DB::table('employee')->where('eci', $eci)->value('employee_id')
            ?: DB::table('employee')->insertGetId(['eci' => $eci, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('employee')->where('employee_id', $id)->update(['is_active' => true, 'updated_at' => $now]);

        DB::table('employee_basic_data')->updateOrInsert(['employee_id' => $id], [
            'title' => $p['gender'] === 'Female' ? 'Ms.' : 'Mr.', 'first_name' => $p['first'], 'last_name' => $p['last'], 'nick_name' => $p['nick'],
            'search_term_1' => mb_strtoupper($p['first']), 'search_term_2' => mb_strtoupper($p['last']),
            'gender' => $p['gender'], 'religion' => 'Islam', 'marital_status' => $p['deps'] ? 'Married' : 'Single', 'birth_date' => '1995-05-20', 'birth_place' => 'Yogyakarta',
            'since_date' => $p['join'], 'position' => 'SUPPORT CONSULTANT', 'department' => 'RESOURCE, PROJECT MANAGEMENT & OPERATIONS', 'division' => 'OPERATIONS',
            'employee_group' => 'INTERNAL', 'personnel_area' => 'OPERATIONS', 'home_base' => 'Yogyakarta',
            'employee_type' => EmployeeBasicData::deriveEmployeeType('Yogyakarta'),
            'created_by' => 'PayrollUjiSeeder', 'created_on' => $now, 'block' => false, 'deletion_flag' => false,
        ]);

        return (int) $id;
    }

    /** Semua isian Contract Readiness + data yang dibaca Payroll (PTKP, BPJS, NPWP, rekening). */
    private function masterData(int $id, string $eci, array $p): void
    {
        $now = now();
        $once = fn (string $table, array $where, array $data) => DB::table($table)->where($where)->exists() ?: DB::table($table)->insert($where + $data + ['created_at' => $now, 'updated_at' => $now]);
        $name = $p['first'] . ' ' . $p['last'];

        $once('employee_identification', ['employee_id' => $id, 'identification_type' => 'KTP'], ['identification_number' => '3471' . substr($p['bank'], -2) . '9505200001']);
        $once('employee_identification', ['employee_id' => $id, 'identification_type' => 'NPWP'], ['identification_number' => '90.123.4' . substr($p['bank'], -2) . '.1-541.000']);
        $once('employee_identification', ['employee_id' => $id, 'identification_type' => 'BPJS_KESEHATAN'], ['identification_number' => '000' . $p['bank'] . '0001']);
        $once('employee_address', ['employee_id' => $id, 'address_type' => 'Home'], [
            'country' => 'ID', 'region' => 'DI Yogyakarta', 'city' => 'Kota Yogyakarta', 'street' => 'Jl. Uji Payroll No. ' . substr($p['bank'], -2), 'postal_code' => '55281',
            'cell_phone' => '0812345600' . substr($p['bank'], -2), 'email_personal' => strtolower($eci) . '@example.com', 'is_primary' => 0,
        ]);
        $once('employee_bank', ['employee_id' => $id, 'bank_name' => 'Bank Contoh'], ['account_number' => $p['bank'] . '56789', 'account_holder' => $name]);
        foreach (['KTP' => 'KTP (data uji)', 'KK' => 'KK (data uji)'] as $type => $title) {
            $once('employee_attachment', ['employee_id' => $id, 'document_type' => $type], ['document_title' => $title, 'file_name' => strtolower($type) . '-uji.pdf', 'file_path' => 'seeder/placeholder-' . strtolower($type), 'file_size' => 0, 'mime_type' => 'application/pdf', 'uploaded_by' => 'PayrollUjiSeeder']);
        }

        $payroll = $eci === 'XXUJI03';
        DB::table('employee_hr_profile')->updateOrInsert(['employee_id' => $id], [
            'emergency_contact_name' => 'Kontak Darurat Uji', 'emergency_contact_relation' => 'Spouse', 'emergency_contact_phone' => '081200000000',
            'ptkp_code' => $p['ptkp'], 'dependents_count' => $p['deps'], 'bpjs_health_active' => 1, 'bpjs_employment_active' => 1,
            'bpjs_dependents_count' => $p['deps'], 'payroll_activated' => $payroll ? 1 : 0, 'updated_at' => $now, 'created_at' => $now,
        ]);
    }

    /** Hapus HANYA kontrak bertanda seeder ini beserta baris Salary Components turunannya; mode CLEAN juga menghapus kedua karyawan. */
    private function clean(bool $quiet = false): void
    {
        $ids = DB::table('employee')->whereIn('eci', array_keys(self::PEOPLE))->pluck('employee_id')->map(fn ($v) => (int) $v)->all();
        if (!$ids) {
            return;
        }
        $contractIds = EmployeeContract::whereIn('employee_id', $ids)->where('notes', 'like', self::MARK . '%')->pluck('contract_id')->all();
        $comps = $contractIds ? DB::table('employee_salary_components')->whereIn('contract_id', $contractIds)->delete() : 0;
        $contracts = EmployeeContract::whereIn('contract_id', $contractIds)->delete();
        if ($quiet) {
            return;
        }

        DB::transaction(function () use ($ids) {
            DB::table('employee_salary_components')->whereIn('employee_id', $ids)->delete();
            foreach (['employee_identification', 'employee_address', 'employee_bank', 'employee_attachment', 'employee_hr_profile', 'employee_contract', 'employee_basic_data'] as $t) {
                DB::table($t)->whereIn('employee_id', $ids)->delete();
            }
            DB::table('employee')->whereIn('employee_id', $ids)->delete();
        });
        $this->say("Dibersihkan: {$contracts} kontrak, {$comps} baris gaji turunan, dan 2 karyawan uji (XXUJI03, WWUJI04).");
    }

    private function say(string $m): void
    {
        $this->command ? $this->command->info($m) : print($m . "\n");
    }
}
