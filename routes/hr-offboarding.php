<?php

use App\Http\Controllers\Finance\LegalBasisController;
use App\Http\Controllers\HR_General\OffboardingController;
use App\Http\Controllers\HR_General\OffboardingSettingsController;
use App\Http\Middleware\CheckAuthToken;
use Illuminate\Support\Facades\Route;

/**
 * ============================================================================
 * OFFBOARDING (HR & General) dan REGISTER DASAR HUKUM (Finance) — berkas rute BARU, aditif.
 * ============================================================================
 *   /general/offboarding            Offboardings (V) · mulai (C) · checklist / hitung / nonaktifkan akses / selesaikan (E) · batalkan (D)
 *   /general/offboarding/settings   Settings (V) · C tambah tugas · E saklar & opsi · D hapus tugas
 *   /legal/basis                    Legal Basis (menu Business & Legal) (V) · C tambah/versi baru · E ubah & tandai ulasan · D tandai superseded
 * Seperti modul HR lain: HANYA GET dan POST. Rute statis (settings) ditaruh SEBELUM rute berparameter.
 */
Route::middleware(CheckAuthToken::class)->group(function () {

    Route::prefix('general/offboarding')->name('general.offboarding.')->group(function () {
        $list = 'general.offboarding.list';
        $set = 'general.offboarding.settings';
        $can = fn (string $slug, string $action) => "menu.can:{$slug},{$action}";

        Route::prefix('settings')->name('settings.')->middleware("menu:{$set}")->group(function () use ($set, $can) {
            Route::get('/', [OffboardingSettingsController::class, 'index'])->name('index');
            Route::post('/', [OffboardingSettingsController::class, 'save'])->name('save')->middleware($can($set, 'edit'));
            Route::post('/tasks', [OffboardingSettingsController::class, 'storeTemplate'])->name('tasks.store')->middleware($can($set, 'create'));
            Route::whereNumber('templateId')->group(function () use ($set, $can) {
                Route::post('/tasks/{templateId}/update', [OffboardingSettingsController::class, 'updateTemplate'])->name('tasks.update')->middleware($can($set, 'edit'));
                Route::post('/tasks/{templateId}/delete', [OffboardingSettingsController::class, 'destroyTemplate'])->name('tasks.destroy')->middleware($can($set, 'delete'));
            });
        });

        Route::middleware("menu:{$list}")->group(function () use ($list, $can) {
            Route::get('/', [OffboardingController::class, 'index'])->name('index');
            Route::post('/', [OffboardingController::class, 'store'])->name('store')->middleware($can($list, 'create'));
            Route::whereNumber('id')->group(function () use ($list, $can) {
                Route::get('/{id}', [OffboardingController::class, 'show'])->name('show');
                Route::post('/{id}/tasks', [OffboardingController::class, 'saveTasks'])->name('tasks')->middleware($can($list, 'edit'));
                Route::post('/{id}/calculate', [OffboardingController::class, 'calculate'])->name('calculate')->middleware($can($list, 'edit'));
                Route::post('/{id}/deactivate', [OffboardingController::class, 'deactivate'])->name('deactivate')->middleware($can($list, 'edit'));
                Route::post('/{id}/complete', [OffboardingController::class, 'complete'])->name('complete')->middleware($can($list, 'edit'));
                Route::post('/{id}/cancel', [OffboardingController::class, 'cancel'])->name('cancel')->middleware($can($list, 'delete'));
            });
        });
    });

    Route::prefix('legal/basis')->name('finance.legal-basis.')->middleware('menu:finance.legal-basis')->group(function () {
        $slug = 'finance.legal-basis';
        $can = fn (string $action) => "menu.can:{$slug},{$action}";

        Route::get('/', [LegalBasisController::class, 'index'])->name('index');
        Route::post('/', [LegalBasisController::class, 'store'])->name('store')->middleware($can('create'));
        Route::post('/switches', [LegalBasisController::class, 'saveSwitches'])->name('switches')->middleware($can('edit'));
        Route::whereNumber('id')->group(function () use ($can) {
            Route::post('/{id}/update', [LegalBasisController::class, 'update'])->name('update')->middleware($can('edit'));
            Route::post('/{id}/review', [LegalBasisController::class, 'review'])->name('review')->middleware($can('edit'));
            Route::post('/{id}/needs-review', [LegalBasisController::class, 'needsReview'])->name('needs-review')->middleware($can('edit'));
            Route::post('/{id}/supersede', [LegalBasisController::class, 'supersede'])->name('supersede')->middleware($can('delete'));
        });
    });
});
