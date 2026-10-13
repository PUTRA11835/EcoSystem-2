<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ESS → Paystub terdaftar sebagai menu (Menu List / Menu Access): `general.my-paystub`, halaman tingkat atas seperti
 * My Reimbursement / My Letter Requests. Sebelumnya Paystub hanya dijaga kepemilikan datanya dan saklar ESS Settings,
 * sehingga tidak tampak di Menu List. Hibah awal: EC Administrator + role "User System Registered" (default semua karyawan),
 * jadi tidak ada karyawan yang kehilangan akses. Slip yang tampil tetap hanya milik sendiri dan sudah dipublikasikan.
 */
return new class extends Migration
{
    private const SLUG = 'general.my-paystub';

    public function up(): void
    {
        if (MenuRegistrar::register('general', [self::SLUG => 'Paystub'], 91, 'page')) {
            DB::table('menu')->where('slug', self::SLUG)->update(['parent_id' => null, 'order_seq' => 6, 'updated_at' => now()]);
            MenuRegistrar::grantToAdminAndRoles([self::SLUG], ['User System Registered']);
        }

        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
