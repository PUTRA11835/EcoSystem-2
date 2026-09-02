<?php

use App\Http\Controllers\HR_General\AttendanceCorrectionController;
use App\Http\Controllers\HR_General\AttendanceRecapController;
use App\Http\Controllers\HR_General\AttendanceSettingController;
use App\Http\Controllers\HR_General\AttendanceSourceController;
use App\Http\Controllers\HR_General\BranchController;
use App\Http\Controllers\HR_General\DashboardAttendanceController;
use App\Http\Controllers\HR_General\GeoLookupController;
use App\Http\Controllers\HR_General\MyAttendanceController;
use App\Http\Controllers\HR_General\MyOvertimeController;
use App\Http\Controllers\HR_General\MyPurchaseRequestController;
use App\Http\Controllers\HR_General\MyReimbursementController;
use App\Http\Controllers\HR_General\OvertimeReviewController;
use App\Http\Controllers\HR_General\OvertimeSettingController;
use App\Http\Controllers\HR_General\PurchaseRequestController;
use App\Http\Controllers\HR_General\PurchaseRequestSettingController;
use App\Http\Controllers\HR_General\ReimbursementController;
use App\Http\Controllers\HR_General\ReimbursementImportController;
use App\Http\Controllers\HR_General\ReimbursementSettingController;
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
 *   Settings -> Overtime   aturan lembur + alur persetujuan
 *   Settings -> Reimburse. aturan reimbursement + alur persetujuan
 *   Attendance             rekap presensi (harian & bulanan) + koreksi
 *   My Attendance          presensi mandiri, untuk SELURUH karyawan
 *   Overtime               peninjauan & persetujuan lembur
 *   My Overtime            pengajuan lembur mandiri, untuk SELURUH karyawan
 *
 * Otorisasi memakai middleware `menu:` yang sudah ada. Setiap rute dilindungi
 * slug-nya sendiri, dan setiap tombol di Blade dibungkus @if($can(...)) dengan
 * slug yang sama — supaya pengguna tidak pernah menemui 403 dari tombol yang
 * terlihat.
 *
 * =============================================================================
 * 🔴 HANYA GET DAN POST — TIDAK ADA PUT / PATCH / DELETE
 * =============================================================================
 * Seluruh rute pengubah data di berkas ini memakai POST dengan akhiran aksi
 * yang eksplisit (`/update`, `/delete`, `/cancel`, `/release`).
 *
 * ALASANNYA:
 * Sebagian penyedia hosting, proxy, dan WAF menolak metode PUT/PATCH/DELETE,
 * dan sebagian lainnya menyaring field `_method` yang dipakai Laravel untuk
 * memalsukan metode. Selama seluruh perubahan data lewat POST biasa, modul ini
 * tidak bergantung pada satu pun dari keduanya.
 *
 * CATATAN JUJUR: form Blade dengan @method('DELETE') sebenarnya SUDAH mengirim
 * POST di kabel — Laravel yang menerjemahkannya lewat `_method`. Jadi perubahan
 * ini bukan memperbaiki yang rusak, melainkan MENGHAPUS KETERGANTUNGAN pada
 * mekanisme itu, dan memastikan kode JavaScript yang ditambahkan kelak tidak
 * diam-diam mengirim DELETE sungguhan lewat fetch().
 *
 * BERLAKU HANYA UNTUK BERKAS INI. Modul lain (Ticket, Delivery, Timesheet,
 * Master Data) masih memakai PUT/DELETE dan SENGAJA TIDAK DISENTUH — mengubah
 * rute yang sedang dipakai produksi berisiko tanpa manfaat yang diminta.
 *
 * Nama rute TIDAK berubah saat verbanya diubah, sehingga seluruh `route()` di
 * Blade tetap bekerja tanpa disunting.
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
                Route::post('/correction/{correction}/cancel', [AttendanceCorrectionController::class, 'cancel'])->name('correction.cancel');
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
                Route::post('/{branch}/update', [BranchController::class, 'update'])->name('update');
                Route::post('/{branch}/delete', [BranchController::class, 'destroy'])->name('destroy');
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
                Route::post('/{shift}/update', [ShiftController::class, 'update'])->name('update');
                Route::post('/{shift}/delete', [ShiftController::class, 'destroy'])->name('destroy');

                // Penugasan karyawan ke shift
                Route::get('/{shift}/assign', [ShiftController::class, 'assign'])->name('assign');
                Route::post('/{shift}/assign', [ShiftController::class, 'storeAssignment'])->name('assign.store');
                Route::post('/{shift}/assign/{assignment}/release', [ShiftController::class, 'releaseAssignment'])->name('assign.release');
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
                Route::post('/update', [AttendanceSettingController::class, 'update'])->name('update');
            });

        // Master sumber presensi. Dipisah dari pengaturan karena berupa daftar
        // yang dapat bertambah, bukan satu baris nilai.
        Route::prefix('settings/attendance-sources')
            ->name('settings.sources.')
            ->middleware('menu:general.settings.attendance')
            ->group(function () {
                Route::post('/', [AttendanceSourceController::class, 'store'])->name('store');
                Route::post('/{source}/update', [AttendanceSourceController::class, 'update'])->name('update');
                Route::post('/{source}/delete', [AttendanceSourceController::class, 'destroy'])->name('destroy');
            });

        // =====================================================================
        // MY OVERTIME — pengajuan lembur mandiri, untuk SELURUH karyawan
        // =====================================================================
        // Seperti My Attendance, seluruh rute di bawah memakai satu slug yang
        // sama: siapa pun yang boleh membuka halamannya boleh mengajukan lembur
        // untuk DIRINYA SENDIRI. Identitas diambil dari sesi, bukan dari
        // request, sehingga tidak ada jalan mengajukan atas nama orang lain.
        Route::prefix('my-overtime')
            ->name('my-overtime.')
            ->middleware('menu:general.my-overtime')
            ->group(function () {
                Route::get('/', [MyOvertimeController::class, 'index'])->name('index');
                Route::get('/create', [MyOvertimeController::class, 'create'])->name('create');
                Route::post('/', [MyOvertimeController::class, 'store'])->name('store');

                // Pembanding presensi saat tanggal diganti di form.
                Route::get('/attendance-hint', [MyOvertimeController::class, 'attendanceHint'])->name('attendance-hint');

                // Kepemilikan diperiksa dari sesi di dalam service, bukan dari
                // parameter rute.
                Route::post('/{overtimeRequest}/cancel', [MyOvertimeController::class, 'cancel'])->name('cancel');
            });

        // =====================================================================
        // OVERTIME — PENINJAUAN & PERSETUJUAN (sisi HR / penyetuju)
        // =====================================================================
        Route::prefix('overtime')->name('overtime.')->group(function () {

            Route::get('/', [OvertimeReviewController::class, 'index'])
                ->name('index')
                ->middleware('menu:general.overtime');

            // Didaftarkan SEBELUM rute berparameter agar tidak tertimpa.
            Route::get('/export', [OvertimeReviewController::class, 'export'])
                ->name('export')
                ->middleware('menu:general.overtime.export');

            // Menyetujui/menolak dilindungi slug TERPISAH dari sekadar melihat
            // daftar. Slug ini hanya gerbang HALAMAN; siapa yang berwenang pada
            // sebuah pengajuan tetap ditentukan langkah persetujuannya.
            Route::middleware('menu:general.overtime.approve')->group(function () {
                Route::post('/{overtimeRequest}/approve', [OvertimeReviewController::class, 'approve'])->name('approve');
                Route::post('/{overtimeRequest}/reject', [OvertimeReviewController::class, 'reject'])->name('reject');
            });
        });

        // =====================================================================
        // SETTINGS — OVERTIME RULES & APPROVAL WORKFLOW
        // =====================================================================
        // Katup pengaman modul: setiap kebijakan yang dapat MENOLAK pengajuan
        // harus dapat dilonggarkan dari sini tanpa perubahan kode.
        Route::prefix('settings/overtime')
            ->name('settings.overtime.')
            ->middleware('menu:general.settings.overtime')
            ->group(function () {
                Route::get('/', [OvertimeSettingController::class, 'edit'])->name('edit');
                Route::post('/update', [OvertimeSettingController::class, 'update'])->name('update');

                // Alur persetujuan. Perubahan di sini berlaku pada pengajuan
                // BARU; yang sedang berjalan memakai salinan langkah miliknya
                // sendiri.
                Route::post('/steps', [OvertimeSettingController::class, 'storeStep'])->name('steps.store');
                Route::post('/steps/{step}/update', [OvertimeSettingController::class, 'updateStep'])->name('steps.update');
                Route::post('/steps/{step}/delete', [OvertimeSettingController::class, 'destroyStep'])->name('steps.destroy');
                Route::post('/steps/{step}/move', [OvertimeSettingController::class, 'moveStep'])->name('steps.move');
            });

        // =====================================================================
        // MY REIMBURSEMENT — pengajuan mandiri, untuk SELURUH karyawan
        // =====================================================================
        // Seperti My Attendance dan My Overtime, seluruh rute di bawah memakai
        // satu slug yang sama. Identitas diambil dari sesi, bukan dari request.
        //
        // BEDANYA dari dua sub-modul itu: `show` dan `print` menerima parameter
        // dokumen, sehingga slug saja TIDAK cukup — slug hanya menjawab "boleh
        // membuka halaman ini?", bukan "dokumen siapa ini?". Kepemilikan
        // diperiksa di controller lewat abort_if(), kalau tidak siapa pun yang
        // boleh mengajukan dapat membaca dokumen keuangan rekannya dengan
        // menebak id-nya.
        //
        // TIDAK ADA rute pembatalan: karyawan tidak dapat membatalkan
        // dokumennya sendiri (Keputusan D111, mengikuti aplikasi acuan).
        Route::prefix('my-reimbursement')
            ->name('my-reimbursement.')
            ->middleware('menu:general.my-reimbursement')
            ->group(function () {
                Route::get('/', [MyReimbursementController::class, 'index'])->name('index');
                Route::get('/create', [MyReimbursementController::class, 'create'])->name('create');
                Route::post('/', [MyReimbursementController::class, 'store'])->name('store');

                // Didaftarkan SESUDAH /create agar tidak menangkapnya sebagai id.
                Route::get('/{reimbursementRequest}', [MyReimbursementController::class, 'show'])->name('show');
                Route::get('/{reimbursementRequest}/print', [MyReimbursementController::class, 'print'])->name('print');
            });

        // =====================================================================
        // MY PURCHASE REQUEST — pengajuan mandiri, untuk SELURUH karyawan
        // =====================================================================
        // Slug `general.my-purchase-request` menjaga pintunya, tetapi TIDAK
        // menjawab "dokumen siapa ini?". Kepemilikan diperiksa di controller
        // lewat abort_if() pada show, print, dan cancel — kalau tidak, siapa pun
        // yang boleh mengajukan dapat membaca atau MEMBATALKAN dokumen rekannya
        // hanya dengan menebak id-nya.
        //
        // 🔴 ADA rute pembatalan di sini, berbeda dari My Reimbursement
        // (Keputusan D131): purchase request belum menimbulkan komitmen uang,
        // jadi alasan D111 tidak berlaku. Syaratnya — status masih `submitted`
        // DAN sakelar `allow_requester_cancel` menyala — ditegakkan di service,
        // bukan hanya oleh tombol yang disembunyikan.
        Route::prefix('my-purchase-request')
            ->name('my-purchase-request.')
            ->middleware('menu:general.my-purchase-request')
            ->group(function () {
                Route::get('/', [MyPurchaseRequestController::class, 'index'])->name('index');
                Route::get('/create', [MyPurchaseRequestController::class, 'create'])->name('create');
                Route::post('/', [MyPurchaseRequestController::class, 'store'])->name('store');

                // Didaftarkan SESUDAH /create agar tidak menangkapnya sebagai id.
                Route::get('/{purchaseRequest}', [MyPurchaseRequestController::class, 'show'])->name('show');
                Route::get('/{purchaseRequest}/print', [MyPurchaseRequestController::class, 'print'])->name('print');
                Route::post('/{purchaseRequest}/cancel', [MyPurchaseRequestController::class, 'cancel'])->name('cancel');
            });

        // =====================================================================
        // REIMBURSEMENT — PENGELOLAAN (sisi HR / GA / penyetuju)
        // =====================================================================
        // 🔴 URUTAN PENDAFTARAN PENTING: /create, /export, dan /import harus
        // berada SEBELUM /{reimbursementRequest}, kalau tidak ketiganya
        // tertangkap sebagai id dokumen. Jebakan yang sama sudah pernah ditemui
        // pada rekap Attendance dan Overtime.
        Route::prefix('reimbursement')->name('reimbursement.')->group(function () {

            Route::get('/', [ReimbursementController::class, 'index'])
                ->name('index')
                ->middleware('menu:general.reimbursement');

            // Membuat atas nama karyawan. Slug terpisah dari `.manage`: yang
            // boleh membuatkan belum tentu boleh menghapus.
            Route::middleware('menu:general.reimbursement.create')->group(function () {
                Route::get('/create', [ReimbursementController::class, 'create'])->name('create');
                Route::post('/', [ReimbursementController::class, 'store'])->name('store');
            });

            Route::get('/export', [ReimbursementController::class, 'export'])
                ->name('export')
                ->middleware('menu:general.reimbursement.export');

            Route::middleware('menu:general.reimbursement.import')->group(function () {
                Route::get('/import', [ReimbursementImportController::class, 'form'])->name('import.form');
                Route::get('/import/template', [ReimbursementImportController::class, 'template'])->name('import.template');
                Route::post('/import', [ReimbursementImportController::class, 'store'])->name('import.store');
            });

            // ── Rute berparameter, didaftarkan TERAKHIR ──────────────────────
            //
            // ->withTrashed() disengaja pada ketiga rute baca: dokumen yang
            // dihapus tetap harus dapat dibuka, dicetak, dan diekspor — itulah
            // gunanya soft delete (Keputusan D109). Tanpa ini, filter
            // "Status -> Deleted" akan menampilkan baris yang tidak bisa
            // diklik sama sekali.
            Route::get('/{reimbursementRequest}', [ReimbursementController::class, 'show'])
                ->name('show')
                ->middleware('menu:general.reimbursement')
                ->withTrashed();

            Route::get('/{reimbursementRequest}/print', [ReimbursementController::class, 'print'])
                ->name('print')
                ->middleware('menu:general.reimbursement')
                ->withTrashed();

            Route::get('/{reimbursementRequest}/export', [ReimbursementController::class, 'exportSingle'])
                ->name('export.single')
                ->middleware('menu:general.reimbursement.export')
                ->withTrashed();

            // Menyetujui/menolak dilindungi slug TERPISAH dari sekadar melihat
            // daftar. Slug ini hanya gerbang HALAMAN; siapa yang berwenang pada
            // sebuah dokumen tetap ditentukan langkah persetujuannya.
            Route::middleware('menu:general.reimbursement.approve')->group(function () {
                Route::post('/{reimbursementRequest}/approve', [ReimbursementController::class, 'approve'])->name('approve');
                Route::post('/{reimbursementRequest}/reject', [ReimbursementController::class, 'reject'])->name('reject');
            });

            // Mengubah dokumen: gerbang rute hanya setingkat HALAMAN, karena ada
            // DUA jalan sah menuju ke sini —
            //   1. pemegang `general.reimbursement.manage`  (kapan pun, selama terbuka)
            //   2. penyetuju yang sedang mendapat giliran, BILA pengaturan
            //      `allow_approver_adjust_amount` dinyalakan
            // Keduanya diputuskan ReimbursementController::canEditDocument(),
            // yang memeriksa keadaan DOKUMEN — sesuatu yang tidak dapat dijawab
            // slug. Pola yang sama dengan approve/reject.
            Route::middleware('menu:general.reimbursement')->group(function () {
                Route::get('/{reimbursementRequest}/edit', [ReimbursementController::class, 'edit'])->name('edit');
                Route::post('/{reimbursementRequest}/update', [ReimbursementController::class, 'update'])->name('update');
            });

            // Menghapus tetap MUTLAK milik pemegang `.manage`. Tidak ada jalan
            // kedua: dokumen keuangan yang berujung ke pembayaran tidak boleh
            // dapat dihapus oleh orang yang sekadar kebagian giliran menyetujui.
            Route::post('/{reimbursementRequest}/delete', [ReimbursementController::class, 'destroy'])
                ->name('destroy')
                ->middleware('menu:general.reimbursement.manage');
        });

        // =====================================================================
        // PURCHASE REQUEST MANAGEMENT (sisi HR / GA / penyetuju)
        // =====================================================================
        // Rute BERPARAMETER didaftarkan TERAKHIR di dalam grup ini; rute statis
        // seperti /create dan /export (menyusul di P6) harus mendahuluinya, kalau
        // tidak keduanya tertangkap sebagai id dokumen.
        Route::prefix('purchase-request')->name('purchase-request.')->group(function () {

            Route::get('/', [PurchaseRequestController::class, 'index'])
                ->name('index')
                ->middleware('menu:general.purchase-request');

            // ── Rute STATIS, WAJIB mendahului rute berparameter ──────────────
            //
            // 🔴 `/create` dan `/export` harus berada DI ATAS `/{purchaseRequest}`,
            // kalau tidak keduanya tertangkap sebagai id dokumen dan halamannya
            // tidak pernah terbuka.
            Route::middleware('menu:general.purchase-request.create')->group(function () {
                Route::get('/create', [PurchaseRequestController::class, 'create'])->name('create');
                Route::post('/', [PurchaseRequestController::class, 'store'])->name('store');
            });

            Route::get('/export', [PurchaseRequestController::class, 'export'])
                ->name('export')
                ->middleware('menu:general.purchase-request.export');

            // ->withTrashed() disengaja pada kedua rute baca: dokumen yang
            // dihapus tetap harus dapat dibuka dan dicetak — itulah gunanya soft
            // delete (Keputusan D109). Tanpa ini, filter "Status -> Deleted"
            // menampilkan baris yang tidak bisa diklik sama sekali.
            Route::get('/{purchaseRequest}', [PurchaseRequestController::class, 'show'])
                ->name('show')
                ->middleware('menu:general.purchase-request')
                ->withTrashed();

            Route::get('/{purchaseRequest}/print', [PurchaseRequestController::class, 'print'])
                ->name('print')
                ->middleware('menu:general.purchase-request')
                ->withTrashed();

            // Menyetujui/menolak dilindungi slug TERPISAH dari sekadar melihat
            // daftar. Slug ini hanya gerbang HALAMAN; siapa yang berwenang pada
            // sebuah dokumen tetap ditentukan langkah persetujuannya, lewat
            // PurchaseRequestService::canAct().
            Route::get('/{purchaseRequest}/export', [PurchaseRequestController::class, 'exportSingle'])
                ->name('export.single')
                ->middleware('menu:general.purchase-request.export')
                ->withTrashed();

            // Menyetujui/menolak dilindungi slug TERPISAH dari sekadar melihat
            // daftar. Slug ini hanya gerbang HALAMAN; siapa yang berwenang pada
            // sebuah dokumen tetap ditentukan langkah persetujuannya, lewat
            // PurchaseRequestService::canAct().
            Route::middleware('menu:general.purchase-request.approve')->group(function () {
                Route::post('/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->name('approve');
                Route::post('/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->name('reject');
            });

            // Mengubah dokumen: gerbang rute hanya setingkat HALAMAN, karena ada
            // DUA jalan sah menuju ke sini —
            //   1. pemegang `general.purchase-request.manage` (kapan pun, selama terbuka)
            //   2. penyetuju yang sedang mendapat giliran, BILA pengaturan
            //      `allow_approver_adjust_items` dinyalakan
            // Keduanya diputuskan PurchaseRequestController::canEditDocument(),
            // yang memeriksa keadaan DOKUMEN — sesuatu yang tidak dapat dijawab
            // slug. Pola yang sama dengan approve/reject.
            Route::middleware('menu:general.purchase-request')->group(function () {
                Route::get('/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])->name('edit');
                Route::post('/{purchaseRequest}/update', [PurchaseRequestController::class, 'update'])->name('update');
            });

            // Menghapus TETAP menuntut `.manage` — memberi hak meninjau tidak
            // boleh otomatis memberi hak menghapus dokumen pengadaan.
            Route::post('/{purchaseRequest}/delete', [PurchaseRequestController::class, 'destroy'])
                ->name('destroy')
                ->middleware('menu:general.purchase-request.manage');
        });

        // =====================================================================
        // SETTINGS — REIMBURSEMENT RULES & APPROVAL WORKFLOW
        // =====================================================================
        // Katup pengaman sub-modul Reimbursement, dengan alasan yang sama
        // seperti Overtime: setiap kebijakan yang dapat MENOLAK pengajuan —
        // batas mundur, batas jumlah item, batas nominal bermode `block`,
        // penguncian periode — harus dapat dilonggarkan dari sini tanpa
        // perubahan kode.
        //
        // Didaftarkan lebih dulu daripada halaman operasionalnya (My
        // Reimbursement dan Reimbursement Management, langkah R4-R5) karena
        // tanpa satu langkah persetujuan aktif, dokumen tidak dapat diajukan
        // sama sekali.
        Route::prefix('settings/reimbursement')
            ->name('settings.reimbursement.')
            ->middleware('menu:general.settings.reimbursement')
            ->group(function () {
                Route::get('/', [ReimbursementSettingController::class, 'edit'])->name('edit');
                Route::post('/update', [ReimbursementSettingController::class, 'update'])->name('update');

                // Alur persetujuan. Perubahan di sini berlaku pada dokumen BARU;
                // yang sedang berjalan memakai salinan langkah miliknya sendiri.
                Route::post('/steps', [ReimbursementSettingController::class, 'storeStep'])->name('steps.store');
                Route::post('/steps/{step}/update', [ReimbursementSettingController::class, 'updateStep'])->name('steps.update');
                Route::post('/steps/{step}/delete', [ReimbursementSettingController::class, 'destroyStep'])->name('steps.destroy');
                Route::post('/steps/{step}/move', [ReimbursementSettingController::class, 'moveStep'])->name('steps.move');
            });

        // =====================================================================
        // SETTINGS -> PURCHASE REQUEST  (aturan dokumen + alur persetujuan)
        // =====================================================================
        // Didaftarkan lebih dulu daripada halaman operasionalnya (My Purchase
        // Request dan Purchase Request Management, langkah P4-P5) karena tanpa
        // satu langkah persetujuan aktif, dokumen tidak dapat diajukan sama
        // sekali — persis alasan yang sama dengan blok Reimbursement di atas.
        Route::prefix('settings/purchase-request')
            ->name('settings.purchase-request.')
            ->middleware('menu:general.settings.purchase-request')
            ->group(function () {
                Route::get('/', [PurchaseRequestSettingController::class, 'edit'])->name('edit');
                Route::post('/update', [PurchaseRequestSettingController::class, 'update'])->name('update');

                // Alur persetujuan. Perubahan di sini berlaku pada dokumen BARU;
                // yang sedang berjalan memakai salinan langkah miliknya sendiri.
                Route::post('/steps', [PurchaseRequestSettingController::class, 'storeStep'])->name('steps.store');
                Route::post('/steps/{step}/update', [PurchaseRequestSettingController::class, 'updateStep'])->name('steps.update');
                Route::post('/steps/{step}/delete', [PurchaseRequestSettingController::class, 'destroyStep'])->name('steps.destroy');
                Route::post('/steps/{step}/move', [PurchaseRequestSettingController::class, 'moveStep'])->name('steps.move');
            });

        // =====================================================================
        // BLOK DASHBOARD — pemasok data untuk kartu Attendance di halaman utama
        // =====================================================================
        // SENGAJA TANPA middleware `menu:`, dan itu bukan kelalaian.
        //
        // Endpoint ini melayani DUA izin sekaligus (`general.my-attendance` untuk
        // bagian pribadi, `general.attendance` untuk ringkasan HR); satu slug di
        // middleware pasti salah untuk salah satu dari keduanya. Penjagaannya
        // dipindah ke dalam controller, PER BAGIAN: yang tidak memegang izinnya
        // menerima `null`, bukan data orang lain. Bagian pribadi pun hanya
        // pernah membaca sesi pemanggil, sehingga tidak ada data yang dapat
        // bocor lewat parameter.
        //
        // CheckAuthToken pada grup induk tetap berlaku — tamu tidak sampai sini.
        Route::get('/dashboard/attendance', [DashboardAttendanceController::class, 'widget'])
            ->name('dashboard.attendance');

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

        // =====================================================================
        // ESS — MY KPI (presensi mandiri KPI, untuk SELURUH karyawan)
        // =====================================================================
        // Setiap karyawan melihat KPI milik DIRINYA SENDIRI. Identitas karyawan
        // selalu diambil dari sesi, tidak pernah dari parameter rute.
        // Self-assessment bersifat wajib tetapi order-independent dengan
        // penilaian supervisor. HR hanya bisa menyetujui setelah KEDUANYA selesai.
        Route::prefix('my-kpi')
            ->name('my-kpi.')
            ->middleware('menu:general.my-kpi')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\HR_General\MyKpiController::class, 'index'])->name('index');
                Route::get('/{id}/self-assessment', [\App\Http\Controllers\HR_General\MyKpiController::class, 'selfAssessmentForm'])
                    ->name('self-assessment');
                Route::post('/{id}/self-assessment', [\App\Http\Controllers\HR_General\MyKpiController::class, 'submitSelfAssessment'])
                    ->name('self-assessment.submit');
            });

        // =====================================================================
        // HR — KPI EVALUATION (dashboard, daftar, review, persetujuan)
        // =====================================================================
        Route::prefix('kpi-evaluation')
            ->name('kpi-evaluation.')
            ->middleware('menu:general.kpi-evaluation')
            ->group(function () {
                // Dashboard
                Route::get('/', [\App\Http\Controllers\HR\KpiController::class, 'dashboard'])->name('index');

                // Evaluation list (detailed, paginated)
                Route::get('/list', [\App\Http\Controllers\HR\KpiController::class, 'evaluationList'])->name('list');

                // Create evaluation (HR creates record, assigns employee + supervisor + template)
                Route::post('/store', [\App\Http\Controllers\HR\KpiController::class, 'storeEvaluation'])
                    ->name('store')
                    ->middleware('menu:general.kpi-evaluation.create');

                // Review / supervisor scoring
                Route::get('/{id}/review', [\App\Http\Controllers\HR\KpiController::class, 'reviewEvaluation'])
                    ->name('review');
                Route::post('/{id}/review', [\App\Http\Controllers\HR\KpiController::class, 'submitReview'])
                    ->name('review.submit')
                    ->middleware('menu:general.kpi-evaluation.review');

                // Approve / Reject (HR decision — makes result visible to employee)
                Route::post('/{id}/approve', [\App\Http\Controllers\HR\KpiController::class, 'approveEvaluation'])
                    ->name('approve')
                    ->middleware('menu:general.kpi-evaluation.approve');
                Route::post('/{id}/reject', [\App\Http\Controllers\HR\KpiController::class, 'rejectEvaluation'])
                    ->name('reject')
                    ->middleware('menu:general.kpi-evaluation.approve');

                // Delete (draft or rejected only)
                Route::post('/{id}/delete', [\App\Http\Controllers\HR\KpiController::class, 'deleteEvaluation'])
                    ->name('delete')
                    ->middleware('menu:general.kpi-evaluation.create');

                // Update deadline (HR extends missed self/supervisor deadline)
                Route::post('/{id}/update-deadline', [\App\Http\Controllers\HR\KpiController::class, 'updateDeadline'])
                    ->name('update-deadline')
                    ->middleware('menu:general.kpi-evaluation.create');

                // Update template (HR updates assigned template for an evaluation)
                Route::post('/{id}/update-template', [\App\Http\Controllers\HR\KpiController::class, 'updateTemplate'])
                    ->name('update-template')
                    ->middleware('menu:general.kpi-evaluation.create');

                // Export CSV
                Route::get('/export', [\App\Http\Controllers\HR\KpiController::class, 'exportEvaluations'])
                    ->name('export')
                    ->middleware('menu:general.kpi-evaluation.export');

                // AJAX dashboard data
                Route::get('/dashboard-data', [\App\Http\Controllers\HR\KpiController::class, 'getDashboardData'])
                    ->name('dashboard-data');
            });

        // =====================================================================
        // KPI SETTINGS — Template Management
        // =====================================================================
        Route::prefix('settings/kpi')
            ->name('settings.kpi.')
            ->middleware('menu:general.settings.kpi')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\HR\KpiTemplateController::class, 'index'])->name('index');
                Route::post('/store', [\App\Http\Controllers\HR\KpiTemplateController::class, 'store'])
                    ->name('store')
                    ->middleware('menu:general.settings.kpi.manage');
                Route::post('/{id}/update', [\App\Http\Controllers\HR\KpiTemplateController::class, 'update'])
                    ->name('update')
                    ->middleware('menu:general.settings.kpi.manage');
                Route::post('/{id}/toggle', [\App\Http\Controllers\HR\KpiTemplateController::class, 'toggleActive'])
                    ->name('toggle')
                    ->middleware('menu:general.settings.kpi.manage');
                Route::post('/{id}/delete', [\App\Http\Controllers\HR\KpiTemplateController::class, 'delete'])
                    ->name('delete')
                    ->middleware('menu:general.settings.kpi.manage');
                // AJAX: get indicators for a template (for form auto-population)
                Route::get('/{id}/indicators', [\App\Http\Controllers\HR\KpiTemplateController::class, 'getIndicators'])
                    ->name('indicators');
            });
    });
