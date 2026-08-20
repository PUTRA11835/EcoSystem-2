<?php

use App\Http\Controllers\HR_General\AttendanceCorrectionController;
use App\Http\Controllers\HR_General\AttendanceRecapController;
use App\Http\Controllers\HR_General\AttendanceSettingController;
use App\Http\Controllers\HR_General\AttendanceSourceController;
use App\Http\Controllers\HR_General\BranchController;
use App\Http\Controllers\HR_General\GeoLookupController;
use App\Http\Controllers\HR_General\MyAttendanceController;
use App\Http\Controllers\HR_General\ShiftController;
use App\Http\Middleware\CheckAuthToken;
use Illuminate\Support\Facades\Route;

/**
 * ============================================================================
 * HR & GENERAL — ROUTES
 * ============================================================================
 *
 * Prefix: /general
 * Menu induk: `general` (menu id 20)
 *
 * Sub-modul:
 *   Settings -> Branches   master cabang + titik geofence
 *   Settings -> Shifts     pola jam kerja
 *   Attendance             rekap presensi (harian & bulanan) + koreksi
 *   My Attendance          presensi mandiri, untuk SELURUH karyawan
 *
 * Otorisasi memakai middleware `menu:` yang sudah ada. Setiap rute dilindungi
 * slug-nya sendiri, dan setiap tombol di Blade dibungkus @if($can(...)) dengan
 * slug yang sama — supaya pengguna tidak pernah menemui 403 dari tombol yang
 * terlihat.
 */
Route::prefix('general')
    ->name('general.')
    ->middleware(CheckAuthToken::class)
    ->group(function () {

        // =====================================================================
        // MY ATTENDANCE — presensi mandiri, untuk SELURUH karyawan
        // =====================================================================
        // Seluruh rute di bawah memakai satu slug yang sama: siapa pun yang
        // boleh membuka halamannya boleh melakukan presensi untuk DIRINYA
        // SENDIRI. Identitas karyawan diambil dari sesi, bukan dari request,
        // sehingga tidak ada jalan mencatat presensi atas nama orang lain.
        Route::prefix('my-attendance')
            ->name('my-attendance.')
            ->middleware('menu:general.my-attendance')
            ->group(function () {
                Route::get('/', [MyAttendanceController::class, 'index'])->name('index');
                Route::get('/today', [MyAttendanceController::class, 'today'])->name('today');
                Route::post('/check-in', [MyAttendanceController::class, 'checkIn'])->name('check-in');
                Route::post('/check-out', [MyAttendanceController::class, 'checkOut'])->name('check-out');

                // Pengajuan koreksi milik sendiri. Kepemilikan diperiksa dari
                // sesi di dalam controller, bukan dari parameter rute.
                Route::post('/correction', [AttendanceCorrectionController::class, 'store'])->name('correction.store');
                Route::delete('/correction/{correction}', [AttendanceCorrectionController::class, 'cancel'])->name('correction.cancel');
            });

        // =====================================================================
        // ATTENDANCE — REKAP (sisi HR)
        // =====================================================================
        // Rute ekspor didaftarkan SEBELUM rute rekap bulanan agar tidak
        // tertimpa, dan dilindungi slug ekspor tersendiri.
        Route::prefix('attendance')->name('attendance.')->group(function () {

            Route::get('/', [AttendanceRecapController::class, 'daily'])
                ->name('daily')
                ->middleware('menu:general.attendance');

            Route::get('/export', [AttendanceRecapController::class, 'exportDaily'])
                ->name('export')
                ->middleware('menu:general.attendance.export');

            Route::get('/monthly', [AttendanceRecapController::class, 'monthly'])
                ->name('monthly')
                ->middleware('menu:general.attendance.monthly');

            Route::get('/monthly/export', [AttendanceRecapController::class, 'exportMonthly'])
                ->name('monthly.export')
                ->middleware('menu:general.attendance.export');
        });

        // =====================================================================
        // ATTENDANCE — PENINJAUAN KOREKSI (sisi HR)
        // =====================================================================
        Route::prefix('attendance/corrections')->name('attendance.corrections.')->group(function () {

            Route::get('/', [AttendanceCorrectionController::class, 'index'])
                ->name('index')
                ->middleware('menu:general.attendance.correction');

            // Menyetujui/menolak dilindungi slug TERPISAH dari sekadar melihat
            // daftar: sebagian pemegang akses HR boleh memantau tanpa berwenang
            // mengubah jam presensi orang lain.
            Route::middleware('menu:general.attendance.correction.approve')->group(function () {
                Route::post('/{correction}/approve', [AttendanceCorrectionController::class, 'approve'])->name('approve');
                Route::post('/{correction}/reject', [AttendanceCorrectionController::class, 'reject'])->name('reject');
            });
        });

        // =====================================================================
        // SETTINGS — BRANCHES
        // =====================================================================
        Route::prefix('settings/branches')->name('settings.branches.')->group(function () {

            Route::get('/', [BranchController::class, 'index'])
                ->name('index')
                ->middleware('menu:general.settings.branches');

            // Membuat / mengubah / menghapus dilindungi slug terpisah dari
            // sekadar melihat daftar.
            Route::middleware('menu:general.settings.branches.manage')->group(function () {
                Route::get('/create', [BranchController::class, 'create'])->name('create');
                Route::post('/', [BranchController::class, 'store'])->name('store');
                Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('edit');
                Route::put('/{branch}', [BranchController::class, 'update'])->name('update');
                Route::delete('/{branch}', [BranchController::class, 'destroy'])->name('destroy');
            });
        });

        // =====================================================================
        // SETTINGS — SHIFTS
        // =====================================================================
        Route::prefix('settings/shifts')->name('settings.shifts.')->group(function () {

            Route::get('/', [ShiftController::class, 'index'])
                ->name('index')
                ->middleware('menu:general.settings.shifts');

            Route::middleware('menu:general.settings.shifts.manage')->group(function () {
                Route::get('/create', [ShiftController::class, 'create'])->name('create');
                Route::post('/', [ShiftController::class, 'store'])->name('store');
                Route::get('/{shift}/edit', [ShiftController::class, 'edit'])->name('edit');
                Route::put('/{shift}', [ShiftController::class, 'update'])->name('update');
                Route::delete('/{shift}', [ShiftController::class, 'destroy'])->name('destroy');

                // Penugasan karyawan ke shift
                Route::get('/{shift}/assign', [ShiftController::class, 'assign'])->name('assign');
                Route::post('/{shift}/assign', [ShiftController::class, 'storeAssignment'])->name('assign.store');
                Route::delete('/{shift}/assign/{assignment}', [ShiftController::class, 'releaseAssignment'])->name('assign.release');
            });
        });

        // =====================================================================
        // SETTINGS — ATTENDANCE RULES
        // =====================================================================
        // Katup pengaman modul: bila kebijakan yang dipilih ternyata memblokir
        // presensi, pemilik sistem dapat melonggarkannya sendiri dari sini.
        Route::prefix('settings/attendance')
            ->name('settings.attendance.')
            ->middleware('menu:general.settings.attendance')
            ->group(function () {
                Route::get('/', [AttendanceSettingController::class, 'edit'])->name('edit');
                Route::put('/', [AttendanceSettingController::class, 'update'])->name('update');
            });

        // Master sumber presensi. Dipisah dari pengaturan karena berupa daftar
        // yang dapat bertambah, bukan satu baris nilai.
        Route::prefix('settings/attendance-sources')
            ->name('settings.sources.')
            ->middleware('menu:general.settings.attendance')
            ->group(function () {
                Route::post('/', [AttendanceSourceController::class, 'store'])->name('store');
                Route::put('/{source}', [AttendanceSourceController::class, 'update'])->name('update');
                Route::delete('/{source}', [AttendanceSourceController::class, 'destroy'])->name('destroy');
            });

        // =====================================================================
        // PENCARIAN LOKASI (proxy Nominatim / OpenStreetMap)
        // =====================================================================
        // Dipakai HANYA oleh form cabang. Dilindungi izin pengelolaan cabang
        // supaya tidak menjadi endpoint terbuka yang memakai kuota Nominatim
        // atas nama alamat IP kantor.
        Route::prefix('geo')
            ->name('geo.')
            ->middleware('menu:general.settings.branches.manage')
            ->group(function () {
                Route::get('/search', [GeoLookupController::class, 'search'])->name('search');
                Route::get('/reverse', [GeoLookupController::class, 'reverse'])->name('reverse');
            });
    });
