<?php

use App\Http\Controllers\HR_General\ExternalConsultantController;
use App\Http\Controllers\HR_General\ExternalContractController;
use App\Http\Controllers\HR_General\ExternalDocumentController;
use App\Http\Controllers\HR_General\ExternalTimesheetController;
use App\Http\Middleware\CheckAuthToken;
use App\Support\External\ExternalRules;
use Illuminate\Support\Facades\Route;

/**
 * ============================================================================
 * EXTERNAL — konsultan eksternal (HR & General → External)
 * ============================================================================
 *
 * Berkas rute BARU (dimuat web.php); modul aditif, tidak menyentuh rute lama.
 * Prefix: /general/external · nama: general.external.*
 *
 *   Consultants             V daftar + profil (hanya baca; data diubah di Master Employee)
 *   Timesheet & Payment     placeholder "Coming soon"
 *   External Contract       V daftar/halaman kelola/dokumen · C buat · E ubah / ganti status · D hapus Draft
 *   Assignment Letter       V arsip + PDF · C buat surat
 *   Employment Certificate  V arsip + PDF · C buat surat
 *   Reference Letter        V arsip + PDF · C buat surat
 *
 * Seperti modul HR lain: HANYA GET dan POST (aksi: /update, /delete). Rute statis ditaruh SEBELUM rute berparameter.
 */
Route::prefix('general/external')
    ->name('general.external.')
    ->middleware(CheckAuthToken::class)
    ->group(function () {

        // ── Consultants ──────────────────────────────────────────────────
        Route::prefix('consultants')->name('consultants.')->middleware('menu:general.external.consultants')->group(function () {
            Route::get('/', [ExternalConsultantController::class, 'index'])->name('index');
            Route::get('/{employeeId}', [ExternalConsultantController::class, 'show'])->whereNumber('employeeId')->name('show');
        });

        // ── Timesheet & Payment (placeholder) ────────────────────────────
        Route::get('timesheet', [ExternalTimesheetController::class, 'index'])
            ->middleware('menu:general.external.timesheet')->name('timesheet.index');

        // ── External Contract ────────────────────────────────────────────
        Route::prefix('contracts')->name('contracts.')->middleware('menu:general.external.contracts')->group(function () {
            $can = fn (string $action) => "menu.can:general.external.contracts,{$action}";

            Route::get('/', [ExternalContractController::class, 'index'])->name('index');

            Route::whereNumber('employeeId')->group(function () use ($can) {
                Route::get('/consultant/{employeeId}', [ExternalContractController::class, 'manage'])->name('manage');
                Route::post('/consultant/{employeeId}/store', [ExternalContractController::class, 'store'])->name('store')->middleware($can('create'));
            });

            Route::whereNumber('contractId')->group(function () use ($can) {
                Route::get('/{contractId}/document', [ExternalContractController::class, 'document'])->name('document');
                Route::get('/{contractId}/pdf', [ExternalContractController::class, 'pdf'])->name('pdf');
                Route::post('/{contractId}/update', [ExternalContractController::class, 'update'])->name('update')->middleware($can('edit'));
                Route::post('/{contractId}/delete', [ExternalContractController::class, 'destroy'])->name('destroy')->middleware($can('delete'));
            });
        });

        // ── Tiga surat konsultan: satu controller, jenis dari rute ───────
        foreach (ExternalRules::DOCUMENTS as $type => $definition) {
            ['path' => $path, 'slug' => $slug] = $definition;

            Route::prefix($path)->name("{$path}.")->middleware("menu:{$slug}")->group(function () use ($type, $slug) {
                Route::get('/', [ExternalDocumentController::class, 'index'])->defaults('type', $type)->name('index');
                Route::post('/', [ExternalDocumentController::class, 'store'])->defaults('type', $type)->name('store')->middleware("menu.can:{$slug},create");
            });
        }

        // PDF surat: gerbang sebenarnya di controller (menurut jenis surat); rute hanya mensyaratkan salah satu dari tiga menu.
        Route::get('documents/{letter}/pdf', [ExternalDocumentController::class, 'pdf'])
            ->whereNumber('letter')
            ->middleware('menu:' . implode(',', array_column(ExternalRules::DOCUMENTS, 'slug')))
            ->name('documents.pdf');
    });
