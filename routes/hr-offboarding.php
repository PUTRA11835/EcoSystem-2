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

    // Legal Basis = hub dua tab: Register (finance.legal-basis) dan Settings (finance.legal-basis.settings) — izin terpisah, diatur Admin.
    Route::prefix('legal/basis')->name('finance.legal-basis.')->group(function () {
        $reg = 'finance.legal-basis';
        $set = 'finance.legal-basis.settings';
        $gate = fn (string $action) => ["menu:{$reg}", "menu.can:{$reg},{$action}"];

        Route::get('/', [LegalBasisController::class, 'index'])->name('index')->middleware("menu:{$reg}");
        Route::post('/', [LegalBasisController::class, 'store'])->name('store')->middleware($gate('create'));
        Route::get('/settings', [LegalBasisController::class, 'settings'])->name('settings')->middleware("menu:{$set}");
        Route::post('/switches', [LegalBasisController::class, 'saveSwitches'])->name('switches')->middleware(["menu:{$set}", "menu.can:{$set},edit"]);
        Route::post('/module/{module}/review', [LegalBasisController::class, 'reviewModule'])->name('review-module')->whereIn('module', ['pph21', 'bpjs', 'overtime', 'contract', 'offboarding'])->middleware($gate('edit'));
        Route::whereNumber('id')->group(function () use ($gate) {
            Route::post('/{id}/update', [LegalBasisController::class, 'update'])->name('update')->middleware($gate('edit'));
            Route::post('/{id}/review', [LegalBasisController::class, 'review'])->name('review')->middleware($gate('edit'));
            Route::post('/{id}/needs-review', [LegalBasisController::class, 'needsReview'])->name('needs-review')->middleware($gate('edit'));
            Route::post('/{id}/supersede', [LegalBasisController::class, 'supersede'])->name('supersede')->middleware($gate('delete'));
            Route::post('/{id}/restore', [LegalBasisController::class, 'restore'])->name('restore')->middleware($gate('edit'));
        });
    });
});
