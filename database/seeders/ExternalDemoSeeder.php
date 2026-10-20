<?php

namespace Database\Seeders;

use App\Models\EmployeeBasicData;
use App\Models\EmployeeContract;
use App\Models\Letters\Letter;
use App\Services\External\ExternalContractService;
use App\Services\External\ExternalDocumentService;
use App\Services\Letters\LetterService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data uji seksi External (HR & General → External): lima konsultan, kontrak dalam berbagai status, dan surat konsultan.
 *
 *   DEMO-EXT01 Eka  — (dari DemoComparisonAccountsSeeder) PKS lama dari seeder Contract + satu kontrak Draft baru
 *   DEMO-EXT02 Eko  — (idem) PKS Draft dari seeder Contract
 *   DEMO-EXT03 Arga — dibuat di sini · Mandays · kontrak Active (20 mandays, 8 jam) · surat tugas + surat keterangan
 *   DEMO-EXT04 Nara — dibuat di sini · Monthly · kontrak Active (prorata hari kerja) · surat referensi
 *   DEMO-EXT05 Raka — dibuat di sini · Fixed price · engagement MENDATANG · kontrak Draft
 *
 *   php artisan db:seed --class=ExternalDemoSeeder
 *   EXTERNAL_DEMO_CLEAN=1 php artisan db:seed --class=ExternalDemoSeeder   # hapus HANYA yang dibuat seeder ini
 *
 * Penanda: kontrak & surat memakai catatan "Seeder demo (External)"; baris engagement memakai vendor "Demo Vendor (External seeder)";
 * karyawan DEMO-EXT03..05 hanya dihapus bila ECI-nya persis itu. DEMO-EXT01/02 TIDAK dihapus (milik seeder lain).
 * DEMO-EXT03..05 juga dibuatkan akun login (username = ECI, kata sandi password123) dengan role External Consultant.
 * Aman diulang, tidak ada di DatabaseSeeder, menolak production. Pagar kesiapan data dimatikan HANYA selama seeder berjalan.
 * Nomor surat yang terpakai tidak dikembalikan (urutan surat keluar tidak pernah memakai ulang nomor).
 */
class ExternalDemoSeeder extends Seeder
{
    private const MARK = 'Seeder demo (External)';
    private const VENDOR = 'Demo Vendor (External seeder)';
    private const OWN = ['DEMO-EXT03', 'DEMO-EXT04', 'DEMO-EXT05'];
    private const OTHERS = ['DEMO-EXT01', 'DEMO-EXT02'];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->say('Dihentikan: data uji External tidak boleh dibuat di production.');

            return;
        }

        if (env('EXTERNAL_DEMO_CLEAN')) {
            $this->clean();

            return;
        }

        if (DB::table('employee')->whereIn('eci', self::OTHERS)->count() < count(self::OTHERS)) {
            $this->say('Akun DEMO-EXT01/02 belum ada — menjalankan DemoComparisonAccountsSeeder dulu.');
            $this->call(DemoComparisonAccountsSeeder::class);
        }

        config(['hc_contract.readiness_gate' => 'off']);
        $this->clean(true);

        $ids = [];
        foreach (self::OTHERS as $eci) {
            $ids[$eci] = (int) DB::table('employee')->where('eci', $eci)->value('employee_id');
        }
        $today = now();
        $fmt = fn ($d) => $d->format('Y-m-d');

        $people = [
            'DEMO-EXT03' => ['Arga', 'Pradana Demo Mandays', 'Arga Demo', 'Male', 'SAP CONSULTANT'],
            'DEMO-EXT04' => ['Nara', 'Pradana Demo Monthly', 'Nara Demo', 'Female', 'SAP CONSULTANT'],
            'DEMO-EXT05' => ['Raka', 'Pradana Demo Fixed', 'Raka Demo', 'Male', 'PROJECT MANAGEMENT OFFICER'],
        ];
        foreach ($people as $eci => [$first, $last, $nick, $gender, $position]) {
            $ids[$eci] = $this->consultant($eci, $first, $last, $nick, $gender, $position);
        }

        // Engagement: skema, vendor/klien, peran, periode, tarif.
        $engagements = [
            'DEMO-EXT03' => ['mandays', 'Konsultan Sales & Distribution', 'PT Klien Contoh Indonesia', 'Head of RPMO', $today->copy()->subMonths(2), $today->copy()->addMonths(4), 2400000],
            'DEMO-EXT04' => ['monthly', 'Konsultan Keuangan', 'PT Mitra Sejahtera Contoh', 'Head of RPMO', $today->copy()->subMonth(), $today->copy()->addMonths(5), 27000000],
            'DEMO-EXT05' => ['fixed_price', 'Konsultan Materials Management', 'PT Klien Contoh Indonesia', 'Delivery Support', $today->copy()->addWeek(), $today->copy()->addMonths(5), 50000000],
        ];
        foreach ($engagements as $eci => [$scheme, $role, $client, $managedBy, $start, $end, $rate]) {
            DB::table('employee_engagement')->updateOrInsert(['employee_id' => $ids[$eci]], [
                'engagement_scheme' => $scheme, 'vendor_partner' => self::VENDOR, 'client_company' => $client, 'assignment_role' => $role,
                'managed_by' => $managedBy, 'start_date' => $fmt($start), 'end_date' => $fmt($end), 'rate' => $rate, 'currency' => 'IDR',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $signer = LetterService::signatoryOptions()->first();
        $signerId = $signer['id'] ?? '';
        $actor = (int) ($signerId ?: 1);
        $contracts = app(ExternalContractService::class);
        $base = ['signatory_employee_id' => $signerId, 'notes' => self::MARK . ' — jangan dipakai sebagai kontrak sungguhan.', 'signed_date' => $fmt($today->copy()->subDays(3))];

        // Kontrak: Active (mandays), Active (monthly), Draft (fixed price), dan satu Draft untuk Eka.
        $contracts->create($ids['DEMO-EXT03'], $base + [
            'engagement_scheme' => 'mandays', 'status' => 'active', 'start_date' => $fmt($today->copy()->subMonths(2)), 'end_date' => $fmt($today->copy()->addMonths(4)),
            'rate' => '2.400.000', 'currency' => 'IDR', 'billing_unit' => 'mandays', 'work_volume' => '20,00 mandays', 'hours_per_manday' => 8,
            'payment_term_days' => 14, 'work_location' => 'ONSITE',
        ], $actor, true);
        $contracts->create($ids['DEMO-EXT04'], $base + [
            'engagement_scheme' => 'monthly', 'status' => 'active', 'start_date' => $fmt($today->copy()->subMonth()), 'end_date' => $fmt($today->copy()->addMonths(5)),
            'rate' => '27.000.000', 'currency' => 'IDR', 'billing_unit' => 'months', 'work_volume' => '6,00 bulan', 'proration' => 'working_days',
            'payment_term_days' => 30, 'work_location' => 'REMOTE',
        ], $actor, true);
        $contracts->create($ids['DEMO-EXT05'], $base + [
            'engagement_scheme' => 'fixed_price', 'status' => 'draft', 'start_date' => $fmt($today->copy()->addWeek()), 'end_date' => $fmt($today->copy()->addMonths(5)),
            'rate' => '50.000.000', 'currency' => 'IDR', 'billing_unit' => 'package', 'work_volume' => '1 paket', 'payment_term_days' => 21, 'work_location' => 'HYBRID',
        ], $actor, true);
        $contracts->create($ids['DEMO-EXT01'], $base + [
            'engagement_scheme' => 'mandays', 'status' => 'draft', 'start_date' => $fmt($today->copy()->addMonths(3)->addDay()), 'end_date' => $fmt($today->copy()->addMonths(6)),
            'rate' => '1.650.000', 'currency' => 'IDR', 'billing_unit' => 'mandays', 'work_volume' => '15,00 mandays', 'hours_per_manday' => 8,
            'payment_term_days' => 14, 'work_location' => 'ONSITE',
        ], $actor, true);

        // Surat konsultan: dibuat lewat layanan yang sama dengan formulir (nomor dari urutan surat keluar).
        $letters = 0;
        if ($signer) {
            $docs = app(ExternalDocumentService::class);
            $running = fn (string $eci) => DB::table('employee_contract')->where('employee_id', $ids[$eci])->where('notes', 'like', self::MARK . '%')
                ->whereIn(DB::raw('UPPER(contract_type)'), ['EXTERNAL'])->orderBy('contract_id')->value('contract_number');
            $common = ['language' => 'id', 'signatory_employee_id' => $signer['id'], 'signatory_title' => $signer['position'] ?? 'Head of RPMO', 'notes' => self::MARK, 'sign_now' => true];

            $docs->create('assignment', $common + [
                'employee_id' => $ids['DEMO-EXT03'], 'letter_date' => $fmt($today->copy()->subDays(5)), 'addressed_to' => 'PT Klien Contoh Indonesia',
                'purpose' => 'Pendampingan implementasi modul Sales & Distribution.', 'contract_no' => $running('DEMO-EXT03'),
                'location' => 'PT Klien Contoh Indonesia, Jakarta', 'period_from' => $fmt($today->copy()->subMonths(2)), 'period_to' => $fmt($today->copy()->addMonths(4)),
            ], $actor);
            $docs->create('certificate', $common + [
                'employee_id' => $ids['DEMO-EXT03'], 'letter_date' => $fmt($today->copy()->subDays(2)), 'addressed_to' => 'Bagian Administrasi Klien',
                'purpose' => 'Kebutuhan administrasi project client.', 'contract_no' => $running('DEMO-EXT03'),
                'location' => 'PT Klien Contoh Indonesia, Jakarta', 'period_from' => $fmt($today->copy()->subMonths(2)), 'period_to' => $fmt($today->copy()->addMonths(4)),
            ], $actor);
            $docs->create('reference', ['language' => 'en'] + $common + [
                'employee_id' => $ids['DEMO-EXT04'], 'letter_date' => $fmt($today->copy()->subDays(1)), 'addressed_to' => 'Hiring Manager',
                'purpose' => 'Nara delivered the finance workstream on schedule and worked well with the client team.', 'contract_no' => $running('DEMO-EXT04'),
                'location' => 'PT Mitra Sejahtera Contoh', 'period_from' => $fmt($today->copy()->subMonth()), 'period_to' => $fmt($today->copy()->addMonths(5)),
            ], $actor);
            $letters = 3;
        } else {
            $this->say('Belum ada penanda tangan (Letter Templates → Settings → Signers) — surat demo dilewati.');
        }

        $timesheets = $this->timesheets($ids, $actor);

        $this->say('Selesai. Buka HR & General → External: 5 konsultan (Eka, Eko, Arga, Nara, Raka), kontrak Active ×2 + Draft ×2 dari seeder ini, ' . $letters . ' surat, ' . $timesheets . ' lembar timesheet (Paid, menunggu verifikasi invoice, Submitted, Returned, Draft).');
        $this->say('Bersihkan: EXTERNAL_DEMO_CLEAN=1 php artisan db:seed --class=ExternalDemoSeeder');
    }

    /**
     * Timesheet demo lewat layanan yang sama dengan layar (aturan tanggal, tarif beku, penyetuju, nomor):
     *   Arga (Mandays)  2 bulan lalu → Approved + invoice Paid · bulan lalu → Approved + invoice menunggu verifikasi · bulan ini → Submitted
     *   Nara (Monthly)  bulan lalu → Returned (dengan alasan) · bulan ini → Draft   (bulan relatif terhadap hari ini)
     * Pemberitahuan yang tercipta selama seeder dibuang lagi supaya kotak notifikasi pengguna tidak ramai.
     */
    private function timesheets(array $ids, int $actor): int
    {
        $svc = app(\App\Services\External\ExternalTimesheetService::class);
        $inv = app(\App\Services\External\ExternalInvoiceService::class);
        $started = now();
        $made = 0;

        $contractOf = fn (int $employeeId) => (int) DB::table('employee_contract')->where('employee_id', $employeeId)->where('notes', 'like', self::MARK . '%')
            ->whereIn(DB::raw('UPPER(contract_type)'), ['EXTERNAL'])->where('lifecycle_status', 'active')->orderBy('contract_id')->value('contract_id');
        // n hari kerja berurutan mulai $from (Sen–Jum), tidak lewat hari ini.
        $days = function (string $from, int $n): array {
            $out = [];
            for ($d = new \DateTimeImmutable($from); count($out) < $n && $d->format('Y-m-d') <= now()->toDateString(); $d = $d->modify('+1 day')) {
                if ((int) $d->format('N') <= 5) {
                    $out[] = $d->format('Y-m-d');
                }
            }

            return $out;
        };
        $rows = fn (array $dates, string $project, string $activity, string $loc = 'ONSITE') => array_map(fn ($date) => [
            'work_date' => $date, 'start_time' => '08:00', 'end_time' => '17:00', 'project_label' => $project, 'location' => $loc, 'activity' => $activity,
        ], $dates);
        $pdf = fn (string $no) => new \Illuminate\Http\UploadedFile($this->pdf($no), $no . '.pdf', 'application/pdf', null, true);
        $today = now();
        // Bulan relatif terhadap hari ini (batas mundur bawaan 60 hari): tiga bulan terakhir = m0 (2 bulan lalu), m1 (bulan lalu), m2 (bulan ini).
        $m0 = now()->startOfMonth()->subMonths(2);
        $m1 = now()->startOfMonth()->subMonth();
        $m2 = now()->startOfMonth();
        $inWindow = now()->subDays(55)->toDateString();
        // Hari kerja TERAKHIR dalam sebuah bulan (maks. $n), tidak lewat hari ini dan tidak lebih tua dari batas mundur.
        $recent = function (\Illuminate\Support\Carbon $month, int $n) use ($inWindow): array {
            $out = [];
            for ($d = $month->copy()->endOfMonth(); count($out) < $n && $d >= $month; $d = $d->copy()->subDay()) {
                if ($d->isWeekday() && $d->toDateString() <= now()->toDateString() && $d->toDateString() >= $inWindow) {
                    $out[] = $d->toDateString();
                }
            }

            return array_reverse($out);
        };

        // ── Arga ──
        $arga = (int) $ids['DEMO-EXT03'];
        $contract = $contractOf($arga);
        if ($contract) {
            $augDates = $recent($m0, 5);   // kosong bila bulan itu sudah di luar batas mundur — skenario "Paid" dilewati
            if ($augDates) {
                $aug = $svc->open($arga, $contract, $m0->year, $m0->month, $actor);
                $svc->save($aug, $rows($augDates, 'Implementation S/D', 'Fit-gap workshop and configuration'), $actor);
                $svc->submit($aug, $arga);
                $svc->approve($aug, $actor);
                $gross = (float) DB::table('external_timesheets')->where('id', $aug)->value('gross_amount');
                $inv->upload($aug, ['invoice_no' => 'INV/ARGA/' . $m0->format('Y/m'), 'invoice_date' => $m1->toDateString(), 'amount' => (string) $gross], $pdf('INV-ARGA-' . $m0->format('m')), $arga);
                $inv->verify($aug, $actor);
                $inv->markPaid($aug, $actor, $m1->copy()->day(14)->toDateString(), 'TRF-' . $m1->format('Y-m') . '-0142');
            }

            $sep = $svc->open($arga, $contract, $m1->year, $m1->month, $actor);
            $svc->save($sep, $rows($recent($m1, 10), 'Implementation S/D', 'Sales order scenarios and UAT support', 'REMOTE'), $actor);
            $svc->submit($sep, $arga);
            $svc->approve($sep, $actor);
            $gross = (float) DB::table('external_timesheets')->where('id', $sep)->value('gross_amount');
            $inv->upload($sep, ['invoice_no' => 'INV/ARGA/' . $m1->format('Y/m'), 'invoice_date' => $m2->copy()->addDay()->min(now())->toDateString(), 'amount' => (string) $gross], $pdf('INV-ARGA-' . $m1->format('m')), $arga);

            $oct = $svc->open($arga, $contract, $m2->year, $m2->month, $actor);
            $svc->save($oct, $rows($recent($m2, 4), 'Implementation S/D', 'Data migration rehearsal'), $actor);
            $svc->submit($oct, $arga);
            $made += $augDates ? 3 : 2;
        }

        // ── Nara ──
        $nara = (int) $ids['DEMO-EXT04'];
        $contract = $contractOf($nara);
        if ($contract) {
            $sep = $svc->open($nara, $contract, $m1->year, $m1->month, $actor);
            $svc->save($sep, $rows($recent($m1, 6), 'Finance rollout', 'Month-end closing support', 'REMOTE'), $actor);
            $svc->submit($sep, $nara);
            $svc->returnSheet($sep, $actor, 'Please add the project name for the last days of the month and submit again.');

            $oct = $svc->open($nara, $contract, $m2->year, $m2->month, $actor);
            $svc->save($oct, $rows($recent($m2, 3), 'Finance rollout', 'Cash management configuration', 'REMOTE'), $actor);
            $made += 2;
        }

        DB::table('notifications')->where('type', 'like', 'external\_%')->where('created_at', '>=', $started)->delete();

        return $made;
    }

    /** PDF kecil sungguhan untuk invoice demo. */
    private function pdf(string $no): string
    {
        $path = tempnam(sys_get_temp_dir(), 'inv');
        file_put_contents($path, \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<h2>Invoice ' . e($no) . '</h2><p>Demo invoice (External seeder).</p>')->output());

        return $path;
    }

    /** Konsultan demo baru (tanpa akun login) lengkap dengan data induk yang dibaca profil & dokumen. */
    private function consultant(string $eci, string $first, string $last, string $nick, string $gender, string $position): int
    {
        $now = now();
        $employeeId = (int) (DB::table('employee')->where('eci', $eci)->value('employee_id')
            ?: DB::table('employee')->insertGetId(['eci' => $eci, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]));

        DB::table('employee_basic_data')->updateOrInsert(['employee_id' => $employeeId], [
            'title' => $gender === 'Female' ? 'Ms.' : 'Mr.', 'first_name' => $first, 'last_name' => $last, 'nick_name' => $nick,
            'search_term_1' => mb_strtoupper($first), 'search_term_2' => mb_strtoupper($last),
            'gender' => $gender, 'marital_status' => 'Single', 'birth_date' => '1993-08-17', 'birth_place' => 'Yogyakarta',
            'position' => $position, 'department' => 'RESOURCE, PROJECT MANAGEMENT & OPERATIONS', 'division' => 'OPERATIONS',
            'employee_group' => 'EXTERNAL', 'personnel_area' => 'OPERATIONS', 'home_base' => 'Others',
            'employee_type' => EmployeeBasicData::deriveEmployeeType('Others'),
            'created_by' => 'DemoSeeder', 'created_on' => $now, 'block' => false, 'deletion_flag' => false,
        ]);

        $n = str_pad((string) ((int) preg_replace('/\D/', '', $eci)), 2, '0', STR_PAD_LEFT);
        DB::table('employee_identification')->insert([
            ['employee_id' => $employeeId, 'identification_type' => 'KTP', 'identification_number' => '34710' . $n . '9308170001', 'created_at' => $now, 'updated_at' => $now],
            ['employee_id' => $employeeId, 'identification_type' => 'NPWP', 'identification_number' => '91.234.56' . $n . '.1-541.000', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('employee_address')->insert([
            'employee_id' => $employeeId, 'address_type' => 'Home', 'country' => 'ID', 'region' => 'DI Yogyakarta', 'city' => 'Kota Yogyakarta',
            'street' => 'Jl. Simulasi No. ' . (20 + (int) $n), 'postal_code' => '55281', 'cell_phone' => '08129900' . $n . $n,
            'email_work' => strtolower(str_replace('-', '.', $eci)) . '@example.invalid', 'email_personal' => strtolower(str_replace('-', '', $eci)) . '@example.com',
            'is_primary' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('employee_bank')->insert([
            'employee_id' => $employeeId, 'bank_name' => 'Bank Contoh', 'account_number' => '80012' . $n . '3456', 'account_holder' => trim("$first $last"),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('employee_attachment')->insert([
            ['employee_id' => $employeeId, 'document_type' => 'KTP', 'document_title' => 'KTP', 'file_name' => 'ktp-demo.pdf', 'file_size' => 1024, 'mime_type' => 'application/pdf', 'uploaded_by' => 'DemoSeeder', 'created_at' => $now, 'updated_at' => $now],
            ['employee_id' => $employeeId, 'document_type' => 'NPWP', 'document_title' => 'NPWP', 'file_name' => 'npwp-demo.pdf', 'file_size' => 1024, 'mime_type' => 'application/pdf', 'uploaded_by' => 'DemoSeeder', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Akun login demo (username = ECI, kata sandi password123) supaya halaman konsultan dapat dicoba langsung.
        $roleIds = array_filter([
            DB::table('employee_role')->where('name', 'User System Registered')->value('id'),
            DB::table('employee_role')->where('name', 'EC User')->value('id'),
            DB::table('employee_role')->where('name', 'External Consultant')->value('id'),
        ]);
        foreach ($roleIds as $roleId) {
            DB::table('employee_role_assignment')->insertOrIgnore(['employee_id' => $employeeId, 'role_id' => $roleId, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('auth_users')->updateOrInsert(['employee_id' => $employeeId], [
            'customer_id' => null, 'username' => $eci, 'email' => strtolower($eci) . '@ecosystem.local',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'), 'is_active' => true, 'is_already_cp' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return $employeeId;
    }

    /** Hapus HANYA yang bertanda seeder ini (lihat docblok). */
    private function clean(bool $quiet = false): void
    {
        // Berkas invoice demo (lembar & invoice terhapus bersama kontrak lewat cascade, berkasnya tidak).
        $sheetIds = DB::table('external_timesheets as t')->join('employee_contract as c', 'c.contract_id', '=', 't.contract_id')
            ->where('c.notes', 'like', self::MARK . '%')->pluck('t.id');
        foreach ($sheetIds as $sheetId) {
            \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("external-invoices/{$sheetId}");
        }
        $letters = Letter::withTrashed()->where('notes', 'like', self::MARK . '%')->whereIn('template_key', \App\Support\External\ExternalRules::templateKeys())->forceDelete();
        // Ketentuan kontrak ikut terhapus bersama kontrak (cascade).
        $contracts = EmployeeContract::where('notes', 'like', self::MARK . '%')->delete();
        $engagement = DB::table('employee_engagement')->where('vendor_partner', self::VENDOR)->delete();

        $ids = DB::table('employee')->whereIn('eci', self::OWN)->pluck('employee_id')->all();
        if ($ids) {
            DB::transaction(function () use ($ids) {
                foreach (['auth_users', 'employee_role_assignment', 'employee_attachment', 'employee_bank', 'employee_address', 'employee_identification', 'employee_contract', 'employee_engagement', 'employee_basic_data'] as $table) {
                    DB::table($table)->whereIn('employee_id', $ids)->delete();
                }
                DB::table('employee')->whereIn('employee_id', $ids)->whereIn('eci', self::OWN)->delete();
            });
        }

        if (!$quiet) {
            $this->say("Dibersihkan: {$letters} surat, {$sheetIds->count()} timesheet, {$contracts} kontrak, {$engagement} baris engagement, " . count($ids) . ' konsultan demo (DEMO-EXT03..05).');
        }
    }

    private function say(string $m): void
    {
        $this->command ? $this->command->info($m) : print($m . "\n");
    }
}
