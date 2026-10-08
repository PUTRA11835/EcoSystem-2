<?php

namespace Database\Seeders;

use App\Models\EmployeeContract;
use App\Services\Contracts\ContractService;
use App\Services\Letters\LetterService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data uji menu Contract (HR & General → Contract): kontrak PKWT, PKWTT, dan External dalam berbagai status untuk
 * karyawan demo DEMO-INT01/02 dan DEMO-EXT01/02 (dibuat DemoComparisonAccountsSeeder; dibuat otomatis bila belum ada),
 * lengkap dengan data induk (KTP, NPWP, alamat, rekening) supaya dokumennya terisi seperti kontrak sungguhan.
 *
 *   DEMO-INT01  Intan  — PKWT lama (Expired, tahun lalu) + PKWT berjalan (Active; gaji pokok + 2 tunjangan)
 *   DEMO-INT02  Indra  — PKWT lama (Terminated) + PKWTT berjalan (Active; tanpa tunjangan)
 *   DEMO-EXT01  Eka    — PKS Konsultan berjalan (Active; skema Mandays, 20,00 mandays, tarif per manday)
 *   DEMO-EXT02  Eko    — PKS Konsultan Draft (belum aktif)
 *
 *   php artisan db:seed --class=ContractDemoSeeder
 *   CONTRACT_DEMO_CLEAN=1 php artisan db:seed --class=ContractDemoSeeder   # hapus HANYA kontrak/engagement/data induk buatan seeder ini
 *
 * Penanda: kolom `notes` kontrak diawali "Seeder demo (Contract)"; baris engagement memakai vendor "Demo Vendor (Contract seeder)";
 * data induk demo hanya ditambah bila karyawan itu belum punya (dan hanya milik DEMO-*). Aman diulang (kontrak lama penanda
 * dibersihkan dulu), tidak ada di DatabaseSeeder, menolak production. Pagar kesiapan data dimatikan HANYA selama seeder berjalan.
 */
class ContractDemoSeeder extends Seeder
{
    private const MARK = 'Seeder demo (Contract)';
    private const VENDOR = 'Demo Vendor (Contract seeder)';
    private const ECIS = ['DEMO-INT01', 'DEMO-INT02', 'DEMO-EXT01', 'DEMO-EXT02'];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->say('Dihentikan: data uji kontrak tidak boleh dibuat di production.');

            return;
        }

        $ids = DB::table('employee')->whereIn('eci', self::ECIS)->pluck('employee_id', 'eci')->all();

        if (env('CONTRACT_DEMO_CLEAN')) {
            $this->clean($ids);

            return;
        }

        if (count($ids) < count(self::ECIS)) {
            $this->say('Akun DEMO-* belum lengkap — menjalankan DemoComparisonAccountsSeeder dulu.');
            $this->call(DemoComparisonAccountsSeeder::class);
            $ids = DB::table('employee')->whereIn('eci', self::ECIS)->pluck('employee_id', 'eci')->all();
        }
        if (count($ids) < count(self::ECIS)) {
            $this->say('Akun DEMO-* tetap tidak lengkap — dihentikan.');

            return;
        }

        config(['hc_contract.readiness_gate' => 'off']);
        $this->clean($ids, true);

        foreach ($ids as $eci => $employeeId) {
            $this->masterData((int) $employeeId, $eci);
        }

        $svc = app(ContractService::class);
        $signer = LetterService::signatoryOptions()->first();
        $signerId = $signer['id'] ?? '';
        $today = now();
        $mk = fn (int $employeeId, array $d) => $svc->create($employeeId, $d + [
            'signatory_employee_id' => $signerId, 'notes' => self::MARK . ' — jangan dipakai sebagai kontrak sungguhan.',
        ], (int) ($signerId ?: 1), true);
        $fmt = fn ($c) => $c->format('Y-m-d');

        // DEMO-INT01: PKWT tahun lalu (Expired) lalu PKWT berjalan.
        $int1 = (int) $ids['DEMO-INT01'];
        $mk($int1, ['contract_type' => 'PKWT', 'status' => 'active', 'position' => 'Support Consultant', 'start_date' => $fmt($today->copy()->subMonths(18)),
            'end_date' => $fmt($today->copy()->subMonths(6)->subDay()), 'signed_date' => $fmt($today->copy()->subMonths(18)->subDays(3)),
            'salary' => '4.000.000', 'components' => [['name' => 'Tunjangan Transportasi', 'amount' => '500.000']], 'work_location' => 'Yogyakarta']);
        $mk($int1, ['contract_type' => 'PKWT', 'status' => 'active', 'position' => 'Support Consultant', 'start_date' => $fmt($today->copy()->startOfMonth()),
            'end_date' => $fmt($today->copy()->startOfMonth()->addMonths(12)->subDay()), 'signed_date' => $fmt($today->copy()->startOfMonth()->subDays(3)),
            'salary' => '4.500.000', 'components' => [['name' => 'Tunjangan Transportasi', 'amount' => '750.000'], ['name' => 'Uang Makan', 'amount' => '500.000']],
            'work_location' => 'Yogyakarta']);

        // DEMO-INT02: PKWT lama dihentikan, lalu PKWTT berjalan.
        $int2 = (int) $ids['DEMO-INT02'];
        $old = $mk($int2, ['contract_type' => 'PKWT', 'status' => 'active', 'position' => 'Project Consultant', 'start_date' => $fmt($today->copy()->subMonths(14)),
            'end_date' => $fmt($today->copy()->subMonths(2)), 'salary' => '6.000.000', 'components' => [], 'work_location' => 'Yogyakarta']);
        $svc->update($old, ['notes' => $old->notes, 'contract_type' => 'PKWT', 'status' => 'terminated', 'start_date' => $fmt($today->copy()->subMonths(14)),
            'end_date' => $fmt($today->copy()->subMonths(2))], true);
        $mk($int2, ['contract_type' => 'PKWTT', 'status' => 'active', 'position' => 'Project Consultant', 'start_date' => $fmt($today->copy()->subMonths(1)),
            'end_date' => '', 'signed_date' => $fmt($today->copy()->subMonths(1)->subDays(2)), 'salary' => '10.000.000', 'components' => [], 'work_location' => 'Yogyakarta']);

        // DEMO-EXT01: PKS berjalan, 20 mandays. DEMO-EXT02: PKS Draft.
        $ext1 = (int) $ids['DEMO-EXT01'];
        $ext2 = (int) $ids['DEMO-EXT02'];
        foreach ([$ext1 => ['Mandays', 'SAP Consultant — Finance', 1500000], $ext2 => ['Monthly', 'SAP Consultant — Basis', 18000000]] as $employeeId => [$scheme, $role, $rate]) {
            DB::table('employee_engagement')->updateOrInsert(['employee_id' => $employeeId], [
                'engagement_scheme' => $scheme, 'vendor_partner' => self::VENDOR, 'client_company' => 'PT Klien Contoh Indonesia',
                'assignment_role' => $role, 'managed_by' => 'Delivery Support', 'start_date' => $fmt($today), 'end_date' => $fmt($today->copy()->addMonths(3)),
                'rate' => $rate, 'currency' => 'IDR', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $mk($ext1, ['contract_type' => 'EXTERNAL', 'status' => 'active', 'position' => 'SAP Consultant — Finance', 'start_date' => $fmt($today),
            'end_date' => $fmt($today->copy()->addMonths(3)), 'signed_date' => $fmt($today->copy()->subDays(2)), 'work_location' => 'ONSITE',
            'work_volume' => '20,00 mandays', 'salary' => '', 'components' => []]);
        $mk($ext2, ['contract_type' => 'EXTERNAL', 'status' => 'draft', 'position' => 'SAP Consultant — Basis', 'start_date' => $fmt($today->copy()->addWeek()),
            'end_date' => $fmt($today->copy()->addMonths(4)), 'work_location' => 'REMOTE', 'work_volume' => '3,00 bulan', 'salary' => '', 'components' => []]);

        $this->say('Selesai. Buka HR & General → Contract: cari "Demo" — 4 karyawan, 6 kontrak (Expired, Terminated, Active ×3, Draft).');
        $this->say('Dokumen: klik View pada tiap kontrak. Bersihkan: CONTRACT_DEMO_CLEAN=1 php artisan db:seed --class=ContractDemoSeeder');
    }

    /** Data induk yang dibaca dokumen kontrak — hanya ditambah bila karyawan DEMO itu belum punya. */
    private function masterData(int $employeeId, string $eci): void
    {
        $n = (int) preg_replace('/\D/', '', $eci) ?: 1;
        $isExt = str_contains($eci, 'EXT');
        $seq = str_pad((string) (array_search($eci, self::ECIS, true) + 1), 2, '0', STR_PAD_LEFT);
        $now = now();

        if (!DB::table('employee_identification')->where('employee_id', $employeeId)->where('identification_type', 'KTP')->exists()) {
            DB::table('employee_identification')->insert(['employee_id' => $employeeId, 'identification_type' => 'KTP', 'identification_number' => '34710' . $seq . '9604120001', 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!DB::table('employee_identification')->where('employee_id', $employeeId)->where('identification_type', 'NPWP')->exists()) {
            DB::table('employee_identification')->insert(['employee_id' => $employeeId, 'identification_type' => 'NPWP', 'identification_number' => '90.123.45' . $seq . '.1-541.000', 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!DB::table('employee_address')->where('employee_id', $employeeId)->where('address_type', 'Home')->exists()) {
            DB::table('employee_address')->insert([
                'employee_id' => $employeeId, 'address_type' => 'Home', 'country' => 'ID', 'region' => 'DI Yogyakarta', 'city' => 'Kota Yogyakarta',
                'street' => 'Jl. Contoh Demo No. ' . (10 + $n), 'postal_code' => '55281', 'cell_phone' => '08123456' . $seq . $seq,
                'email_personal' => strtolower($eci) . '@example.com', 'is_primary' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (!DB::table('employee_bank')->where('employee_id', $employeeId)->exists()) {
            $name = trim((string) DB::table('employee_basic_data')->where('employee_id', $employeeId)->selectRaw("CONCAT_WS(' ', first_name, last_name) n")->value('n'));
            DB::table('employee_bank')->insert(['employee_id' => $employeeId, 'bank_name' => 'Bank Contoh', 'account_number' => '70012' . $seq . '3456', 'account_holder' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /** Hapus HANYA yang bertanda seeder ini. @param array<string,int|string> $ids */
    private function clean(array $ids, bool $quiet = false): void
    {
        $employeeIds = array_map('intval', array_values($ids));
        if (!$employeeIds) {
            return;
        }
        $contracts = EmployeeContract::whereIn('employee_id', $employeeIds)->where('notes', 'like', self::MARK . '%')->delete();
        $engagement = DB::table('employee_engagement')->whereIn('employee_id', $employeeIds)->where('vendor_partner', self::VENDOR)->delete();
        if (!$quiet) {
            $this->say("Dibersihkan: {$contracts} kontrak, {$engagement} baris engagement (data induk demo ikut terhapus bila akun DEMO-* dibersihkan).");
        }
    }

    private function say(string $m): void
    {
        $this->command ? $this->command->info($m) : print($m . "\n");
    }
}
