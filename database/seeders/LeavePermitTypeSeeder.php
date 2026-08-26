<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeavePermitTypeSeeder extends Seeder
{
    /**
     * Run the database seeds for Leave & Permit Master Types.
     */
    public function run(): void
    {
        $types = [
            [
                'code'                 => 'CAS',
                'name'                 => 'Cuti Anggota Serumah Meninggal',
                'category'             => 'permit',
                'default_quota'        => 1.0,
                'min_service_period'   => null,
                'is_paid'              => true,
                'gender_target'        => 'all',
                'requires_attachment'  => false,
                'description'          => 'Izin berbayar 1 hari bila anggota keluarga dalam satu rumah meninggal.',
                'is_active'            => true,
            ],
            [
                'code'                 => 'CHD',
                'name'                 => 'Cuti Haid',
                'category'             => 'leave',
                'default_quota'        => 1.0,
                'min_service_period'   => null,
                'is_paid'              => true,
                'gender_target'        => 'P',
                'requires_attachment'  => false,
                'description'          => 'Izin/cuti berbayar 1 hari per bulan untuk pekerja perempuan saat haid.',
                'is_active'            => true,
            ],
            [
                'code'                 => 'CIM',
                'name'                 => 'Cuti Istri Melahirkan/Keguguran',
                'category'             => 'leave',
                'default_quota'        => 2.0,
                'min_service_period'   => null,
                'is_paid'              => true,
                'gender_target'        => 'L',
                'requires_attachment'  => false,
                'description'          => 'Izin berbayar 2 hari untuk suami saat istri melahirkan atau keguguran.',
                'is_active'            => true,
            ],
            [
                'code'                 => 'CGK',
                'name'                 => 'Cuti Keguguran',
                'category'             => 'leave',
                'default_quota'        => 0.0,
                'min_service_period'   => null,
                'is_paid'              => true,
                'gender_target'        => 'P',
                'requires_attachment'  => true,
                'description'          => 'Berbasis kejadian. Istirahat 1,5 bulan atau sesuai surat dokter/bidan setelah keguguran.',
                'is_active'            => true,
            ],
            [
                'code'                 => 'CKM',
                'name'                 => 'Cuti Keluarga Inti Meninggal',
                'category'             => 'permit',
                'default_quota'        => 2.0,
                'min_service_period'   => null,
                'is_paid'              => true,
                'gender_target'        => 'all',
                'requires_attachment'  => false,
                'description'          => 'Izin berbayar 2 hari bila suami/istri, orang tua/mertua, atau anak/menantu meninggal.',
                'is_active'            => true,
            ],
            [
                'code'                 => 'CKA',
                'name'                 => 'Cuti Khitan/Baptis Anak',
                'category'             => 'permit',
                'default_quota'        => 2.0,
                'min_service_period'   => null,
                'is_paid'              => true,
                'gender_target'        => 'all',
                'requires_attachment'  => false,
                'description'          => 'Izin berbayar 2 hari saat anak dikhitan atau dibaptis.',
                'is_active'            => true,
            ],
            [
                'code'                 => 'CML',
                'name'                 => 'Cuti Melahirkan',
                'category'             => 'leave',
                'default_quota'        => 0.0,
                'min_service_period'   => null,
                'is_paid'              => true,
                'gender_target'        => 'P',
                'requires_attachment'  => true,
                'description'          => 'Berbasis kejadian. Istirahat 1,5 bulan sebelum dan 1,5 bulan sesudah melahirkan menurut perhitungan dokter/bidan.',
                'is_active'            => true,
            ],
            [
                'code'                 => 'CMN',
                'name'                 => 'Cuti Menikah',
                'category'             => 'leave',
                'default_quota'        => 3.0,
                'min_service_period'   => null,
                'is_paid'              => true,
                'gender_target'        => 'all',
                'requires_attachment'  => false,
                'description'          => 'Izin berbayar 3 hari untuk pekerja yang menikah.',
                'is_active'            => true,
            ],
            [
                'code'                 => 'CMA',
                'name'                 => 'Cuti Menikahkan Anak',
                'category'             => 'permit',
                'default_quota'        => 2.0,
                'min_service_period'   => null,
                'is_paid'              => true,
                'gender_target'        => 'all',
                'requires_attachment'  => false,
                'description'          => 'Izin berbayar 2 hari saat pekerja menikahkan anak.',
                'is_active'            => true,
            ],
            [
                'code'                 => 'CSK',
                'name'                 => 'Cuti Sakit',
                'category'             => 'leave',
                'default_quota'        => 0.0,
                'min_service_period'   => null,
                'is_paid'              => true,
                'gender_target'        => 'all',
                'requires_attachment'  => true,
                'description'          => 'Berbasis kejadian. Jenis cuti custom perusahaan.',
                'is_active'            => true,
            ],
            [
                'code'                 => 'CTH',
                'name'                 => 'Cuti Tahunan',
                'category'             => 'leave',
                'default_quota'        => 12.0,
                'min_service_period'   => '12 bln',
                'is_paid'              => true,
                'gender_target'        => 'all',
                'requires_attachment'  => false,
                'description'          => 'Minimal 12 hari kerja setelah 12 bulan bekerja terus-menerus.',
                'is_active'            => true,
            ],
            [
                'code'                 => 'CTU',
                'name'                 => 'Cuti Unpaid',
                'category'             => 'leave',
                'default_quota'        => 0.0,
                'min_service_period'   => null,
                'is_paid'              => false, // Potong gaji / unpaid
                'gender_target'        => 'all',
                'requires_attachment'  => false,
                'description'          => 'Berbasis kejadian. Cuti di luar hak berbayar. Dipakai jika perusahaan mengizinkan unpaid leave.',
                'is_active'            => true,
            ],
        ];

        foreach ($types as $type) {
            DB::table('leave_permit_types')->updateOrInsert(
                ['code' => $type['code']],
                array_merge($type, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }
}
