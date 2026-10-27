<?php

use App\Http\Controllers\HR_General\ExternalConsultantController;
use App\Http\Controllers\HR_General\ContractSettingController;
use App\Http\Controllers\HR_General\ExternalContractController;
use App\Http\Controllers\HR_General\ExternalDocumentController;
use App\Http\Controllers\HR_General\ExternalMyTimesheetController;
use App\Http\Controllers\HR_General\ExternalTimesheetController;
use App\Http\Controllers\HR_General\ExternalWorkflowSettingsController;
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
            Route::post('/', [ExternalConsultantController::class, 'store'])->name('store')->middleware('menu.can:general.external.consultants,create');
            // Urutan ECI + cek ketersediaan untuk formulir Add consultant (baca-saja; didaftarkan sebelum rute berparameter).
            // Token CSRF segar untuk formulir Add consultant (dipanggil sekali bila token lama ditolak, 419).
            Route::get('/csrf', [ExternalConsultantController::class, 'csrf'])->name('csrf')->middleware('menu.can:general.external.consultants,create');
            Route::get('/eci-sequence', [ExternalConsultantController::class, 'eciSequence'])->name('eci-sequence')->middleware('menu.can:general.external.consultants,create');
            Route::get('/{employeeId}', [ExternalConsultantController::class, 'show'])->whereNumber('employeeId')->name('show');
            // Edit konsultan di sini (bukan di Master Employee): izin per tab = external.section.*, diatur Admin terpisah dari HR.
            Route::get('/{employeeId}/edit', [\App\Http\Controllers\EmployeeController::class, 'showExternal'])->whereNumber('employeeId')->name('edit');
        });

        // ── Timesheet & Payment ──────────────────────────────────────────
        // Tiga tab, tiga slug: Timesheets (`…timesheet`, C/E/D) · Invoices & Payments (`…timesheet.invoices`) · Settings (`…timesheet.settings`, C/E/D).
        // Menyetujui / mengembalikan / memverifikasi mengikuti PENYETUJU di Settings (dicek di controller), bukan kotak C/E/D.
        // Rute statis (settings, invoices) ditaruh SEBELUM rute berparameter.
        Route::prefix('timesheet')->name('timesheet.')->group(function () {
            $sheet = 'general.external.timesheet';
            $inv = 'general.external.timesheet.invoices';
            $set = 'general.external.timesheet.settings';
            $can = fn (string $slug, string $action) => "menu.can:{$slug},{$action}";

            Route::prefix('settings')->name('settings.')->middleware("menu:{$set}")->group(function () use ($set, $can) {
                Route::get('/', [ExternalWorkflowSettingsController::class, 'index'])->name('index');
                Route::post('/steps', [ExternalWorkflowSettingsController::class, 'storeStep'])->name('steps.store')->middleware($can($set, 'create'));
                Route::post('/overrides', [ExternalWorkflowSettingsController::class, 'storeOverride'])->name('overrides.store')->middleware($can($set, 'create'));
                Route::post('/options', [ExternalWorkflowSettingsController::class, 'saveOptions'])->name('options')->middleware($can($set, 'edit'));
                Route::whereNumber('id')->group(function () use ($set, $can) {
                    Route::post('/steps/{id}/update', [ExternalWorkflowSettingsController::class, 'updateStep'])->name('steps.update')->middleware($can($set, 'edit'));
                    Route::post('/steps/{id}/delete', [ExternalWorkflowSettingsController::class, 'destroyStep'])->name('steps.destroy')->middleware($can($set, 'delete'));
                    Route::post('/overrides/{id}/delete', [ExternalWorkflowSettingsController::class, 'destroyOverride'])->name('overrides.destroy')->middleware($can($set, 'delete'));
                });
            });

            Route::get('invoices', [ExternalTimesheetController::class, 'invoicesIndex'])->middleware("menu:{$inv}")->name('invoices');

            Route::get('/', [ExternalTimesheetController::class, 'index'])->middleware("menu:{$sheet}")->name('index');
            Route::post('/', [ExternalTimesheetController::class, 'open'])->middleware("menu:{$sheet}")->middleware($can($sheet, 'create'))->name('open');

            // Melihat lembar, cetak, dan berkas invoice: pemegang tab Timesheets ATAU Invoices.
            Route::whereNumber('id')->middleware("menu:{$sheet},{$inv}")->group(function () {
                Route::get('/{id}', [ExternalTimesheetController::class, 'show'])->name('show');
                Route::get('/{id}/print', [ExternalTimesheetController::class, 'print'])->name('print');
                Route::get('/{id}/invoice', [ExternalTimesheetController::class, 'invoiceFile'])->name('invoice.file');
                Route::post('/{id}/approve', [ExternalTimesheetController::class, 'approve'])->name('approve');
                Route::post('/{id}/return', [ExternalTimesheetController::class, 'returnSheet'])->name('return');
                Route::post('/{id}/invoice/verify', [ExternalTimesheetController::class, 'verifyInvoice'])->name('invoice.verify');
                Route::post('/{id}/invoice/reject', [ExternalTimesheetController::class, 'rejectInvoice'])->name('invoice.reject');
                Route::post('/{id}/invoice/paid', [ExternalTimesheetController::class, 'payInvoice'])->name('invoice.paid');
            });

            // Mengisi / mengubah atas nama konsultan: kotak Edit pada Timesheets.
            Route::whereNumber('id')->middleware("menu:{$sheet}")->middleware($can($sheet, 'edit'))->group(function () {
                Route::post('/{id}/save', [ExternalTimesheetController::class, 'save'])->name('save');
                Route::post('/{id}/submit', [ExternalTimesheetController::class, 'submit'])->name('submit');
                Route::post('/{id}/delete', [ExternalTimesheetController::class, 'destroy'])->name('destroy');
                Route::post('/{id}/invoice', [ExternalTimesheetController::class, 'uploadInvoice'])->name('invoice.upload');
            });
        });

        // ── My Timesheet & Invoice (konsultan sendiri) ───────────────────
        Route::prefix('my-timesheet')->name('my-timesheet.')->middleware('menu:general.external.my-timesheet')->group(function () {
            Route::get('/', [ExternalMyTimesheetController::class, 'index'])->name('index');
            Route::post('/', [ExternalMyTimesheetController::class, 'open'])->name('open');
            Route::whereNumber('id')->group(function () {
                Route::post('/{id}/save', [ExternalMyTimesheetController::class, 'save'])->name('save');
                Route::post('/{id}/submit', [ExternalMyTimesheetController::class, 'submit'])->name('submit');
                Route::post('/{id}/invoice', [ExternalMyTimesheetController::class, 'uploadInvoice'])->name('invoice.upload');
                Route::get('/{id}/invoice', [ExternalMyTimesheetController::class, 'invoiceFile'])->name('invoice.file');
                Route::get('/{id}/print', [ExternalMyTimesheetController::class, 'print'])->name('print');
            });
        });

        // ── External Contract ────────────────────────────────────────────
        // Settings tab (before the parameterised routes): contract types and position types of the EXTERNAL contract forms.
        Route::prefix('contracts/settings')->name('contracts.settings.')->middleware('menu:general.external.contracts.settings')->group(function () {
            $can = fn (string $action) => "menu.can:general.external.contracts.settings,{$action}";

            Route::get('/', [ContractSettingController::class, 'index'])->name('index')->defaults('scope', 'external');
            Route::post('/options', [ContractSettingController::class, 'store'])->name('store')->middleware($can('create'))->defaults('scope', 'external');
            Route::whereNumber('optionId')->group(function () use ($can) {
                Route::post('/options/{optionId}/update', [ContractSettingController::class, 'update'])->name('update')->middleware($can('edit'))->defaults('scope', 'external');
                Route::post('/options/{optionId}/move', [ContractSettingController::class, 'move'])->name('move')->middleware($can('edit'))->defaults('scope', 'external');
                Route::post('/options/{optionId}/delete', [ContractSettingController::class, 'destroy'])->name('destroy')->middleware($can('delete'))->defaults('scope', 'external');
            });
        });

        Route::prefix('contracts')->name('contracts.')->middleware('menu:general.external.contracts')->group(function () {
            $can = fn (string $action) => "menu.can:general.external.contracts,{$action}";

            Route::get('/', [ExternalContractController::class, 'index'])->name('index');

            Route::whereNumber('employeeId')->group(function () use ($can) {
                Route::get('/consultant/{employeeId}', [ExternalContractController::class, 'manage'])->name('manage');
                Route::post('/consultant/{employeeId}/store', [ExternalContractController::class, 'store'])->name('store')->middleware($can('create'));
                // Generates the text from the form for the editor under it; saves nothing (the controller checks Create / Edit).
                Route::post('/consultant/{employeeId}/preview', [ExternalContractController::class, 'preview'])->name('preview')->middleware('throttle:30,1');
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
