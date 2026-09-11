<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RoleSeeder extends Seeder
{
    /**
     * Daftar master SELURUH role — diurutkan alfabet sesuai nama agar mudah
     * dicocokkan dengan daftar resmi. Tidak dipisah "lama vs baru".
     *
     * Catatan: 7 role inti memakai 'id' TETAP karena direferensikan langsung
     * sebagai FK (employee.role_id, employee_role_assignment) dan banyak
     * pengecekan role-id hardcoded di aplikasi (mis. admin = 1, helpdesk = 6/7).
     * ID ditaruh inline pada entri masing-masing, jadi daftarnya tetap satu.
     */
    private array $roles = [
        ['name' => 'Customer Access Administrator'],
        ['name' => 'Customer Access User'],
        ['name' => 'Delivery Operation Administrator'],
        ['name' => 'Delivery Operation Director'],
        ['name' => 'Delivery Operation Head'],
        ['name' => 'Delivery Operation User'],
        ['name' => 'Delivery Project Administrator'],
        ['name' => 'Delivery Project Head', 'id' => 4, 'description' => 'Kepala proyek dengan wewenang untuk approval timesheet consultant'],
        ['name' => 'Delivery Project Manager'],
        ['name' => 'Delivery Project PIC'],
        ['name' => 'Delivery Project User'],
        ['name' => 'Delivery RPMO Administrator'],
        ['name' => 'Delivery RPMO Head', 'id' => 7, 'description' => 'Resource & Project Management Office yang bertanggung jawab membuat delivery'],
        ['name' => 'Delivery RPMO Manager'],
        ['name' => 'Delivery RPMO User'],
        ['name' => 'Delivery Support Administrator'],
        ['name' => 'Delivery Support Head', 'id' => 5, 'description' => 'Kepala support dengan wewenang untuk approval timesheet consultant'],
        ['name' => 'Delivery Support Manager'],
        ['name' => 'Delivery Support PIC'],
        ['name' => 'Delivery Support Service Helpdesk', 'id' => 6, 'description' => 'Staff helpdesk yang menangani dan mengassign ticket ke delivery support'],
        ['name' => 'Delivery Support User', 'id' => 2, 'description' => 'Karyawan dengan akses untuk mengedit data pribadi mereka sendiri'],
        ['name' => 'EC Administrator', 'id' => 1, 'description' => 'Administrator dengan akses penuh untuk mengelola semua data master'],
        ['name' => 'EC Director'],
        ['name' => 'EC Head'],
        ['name' => 'EC System Administrator'],
        ['name' => 'EC User', 'id' => 3, 'description' => 'Karyawan magang dengan akses terbatas sesuai kebutuhan internship'],
        ['name' => 'HO Accounting Administrator'],
        ['name' => 'HO Accounting Head'],
        ['name' => 'HO Accounting User'],
        ['name' => 'HO Administrator'],
        ['name' => 'HO Control Administrator'],
        ['name' => 'HO Control Head'],
        ['name' => 'HO Control User'],
        ['name' => 'HO Director'],
        ['name' => 'HO Finance Administrator'],
        ['name' => 'HO Finance Head'],
        ['name' => 'HO Finance User'],
        ['name' => 'HO GA Administrator'],
        ['name' => 'HO GA Head'],
        ['name' => 'HO GA User'],
        ['name' => 'HO HR Administrator'],
        ['name' => 'HO HR Head'],
        ['name' => 'HO HR User'],
        ['name' => 'HO Legal Administrator'],
        ['name' => 'HO Legal Head'],
        ['name' => 'HO Legal User'],
        ['name' => 'HO User'],
        ['name' => 'Sales Engagement Administrator'],
        ['name' => 'Sales Engagement Head'],
        ['name' => 'Sales Engagement User'],
        ['name' => 'Sales Marketing Administrator'],
        ['name' => 'Sales Marketing Head'],
        ['name' => 'Sales Marketing Manager'],
        ['name' => 'Sales Marketing User'],
        ['name' => 'Sales Operation Account Executive'],
        ['name' => 'Sales Operation Administrator'],
        ['name' => 'Sales Operation Director'],
        ['name' => 'Sales Operation Head'],
        ['name' => 'Sales Operation User'],
        ['name' => 'Sales Presales Administrator'],
        ['name' => 'Sales Presales Head'],
        ['name' => 'Sales Presales User'],
        ['name' => 'User System Registered'],
    ];

    public function run(): void
    {
        $now = Carbon::now();

        // ── Pass 1: pakukan role inti ber-ID tetap (stabilitas FK) ────────────
        // Dijalankan lebih dulu agar id 1-7 terpesan sebelum insert auto-increment.
        foreach ($this->roles as $r) {
            if (isset($r['id'])) {
                DB::table('employee_role')->updateOrInsert(
                    ['id' => $r['id']],
                    [
                        'name'        => $r['name'],
                        'description' => $r['description'] ?? null,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]
                );
            }
        }

        // ── Pass 2: pastikan SEMUA role ada (cek by-name, anti-duplikat) ──────
        // Kolom `name` tidak unique, jadi cek manual mencegah baris ganda.
        $existing = array_flip(
            DB::table('employee_role')
                ->pluck('name')
                ->map(fn ($n) => mb_strtolower(trim($n)))
                ->all()
        );

        $toInsert = [];
        foreach ($this->roles as $r) {
            $key = mb_strtolower(trim($r['name']));
            if (!isset($existing[$key])) {
                $toInsert[] = [
                    'name'        => $r['name'],
                    'description' => $r['description'] ?? null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
                $existing[$key] = true;
            }
        }

        if (!empty($toInsert)) {
            DB::table('employee_role')->insert($toInsert);
        }

        $total = DB::table('employee_role')->count();
        $this->command->info("RoleSeeder selesai. Role baru ditambahkan: " . count($toInsert) . ". Total role: {$total}.");
    }
}
