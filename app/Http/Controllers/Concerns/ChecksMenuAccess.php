<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Employee;
use App\Support\SessionUser;

/**
 * Izin sebuah halaman atau fungsi diputuskan SATU tempat: Control Center > Menu Access (`role_menu`).
 *
 * Pakai trait ini di controller alih-alih membandingkan RoleId. Daftar role yang ditulis di kode
 * tidak terlihat oleh administrator di Menu Access, sehingga sebuah menu bisa tampil di sidebar
 * tetapi halamannya tetap menolak (403). `Employee::canAccessMenu()` memeriksa SEMUA role yang
 * dipegang orang itu, bukan hanya role utamanya.
 */
trait ChecksMenuAccess
{
    protected function menuAllowed(string $slug): bool
    {
        $sessionUser = SessionUser::fromSession(session('user'));
        if (!$sessionUser) {
            return false;
        }

        $employee = Employee::find($sessionUser->id);

        return $employee !== null && $employee->canAccessMenu($slug);
    }
}
