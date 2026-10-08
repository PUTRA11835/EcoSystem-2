<?php

use App\Http\Controllers\Finance\BpjsLetterController;
use App\Http\Controllers\Finance\BpjsSettingsController;
use App\Http\Controllers\Finance\FinanceReportController;
use App\Http\Controllers\Finance\PayrollController;
use App\Http\Controllers\Finance\PayrollSettingsController;
use App\Http\Controllers\Finance\Pph21SettingsController;
use App\Http\Middleware\CheckAuthToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Commercial & Finance — Payroll, BPJS, PPh 21
|--------------------------------------------------------------------------
| Berkas baru (aditif; satu baris `require` di routes/web.php). Hanya GET & POST (verb DELETE diblokir edge
| produksi — lihat catatan di routes/hr-general.php). Izin: `menu:<slug>` untuk melihat, `menu.can:<slug>,<aksi>`
| untuk create/edit; slug didaftarkan lewat migrasi MenuRegistrar dan dicatat di config/menu_access.php.
*/
Route::prefix('finance')
    ->name('finance.')
    ->middleware(CheckAuthToken::class)
    ->group(function () {

        // ── Payroll ──────────────────────────────────────────────────────
        // Tab: Periods · Settings · Simulation (slug sendiri per tab). Aksi alur memakai slug fungsi terpisah.
        Route::prefix('payroll')->name('payroll.')->group(function () {
            $tabs = 'menu:finance.payroll.periods,finance.payroll.settings,finance.payroll.simulation';
            $can  = fn (string $action) => "menu.can:finance.payroll.periods,{$action}";

            Route::get('/', [PayrollController::class, 'home'])->name('index')->middleware($tabs);

            Route::prefix('periods')->middleware('menu:finance.payroll.periods')->group(function () use ($can) {
                Route::get('/', [PayrollController::class, 'index'])->name('periods');
                Route::post('/', [PayrollController::class, 'store'])->name('periods.store')->middleware($can('create'));

                Route::whereNumber(['period', 'slip', 'adjustment'])->group(function () use ($can) {
                    Route::get('/{period}', [PayrollController::class, 'show'])->name('periods.show');
                    Route::get('/{period}/export', [PayrollController::class, 'export'])->name('periods.export');
                    Route::get('/{period}/slips/{slip}', [PayrollController::class, 'slip'])->name('slips.show');
                    Route::get('/{period}/slips/{slip}/pdf', [PayrollController::class, 'slipPdf'])->name('slips.pdf');

                    Route::post('/{period}/calculate', [PayrollController::class, 'calculate'])->name('periods.calculate')->middleware($can('edit'));
                    Route::post('/{period}/adjustments', [PayrollController::class, 'addAdjustment'])->name('adjustments.store')->middleware($can('create'));
                    Route::post('/{period}/adjustments/{adjustment}/delete', [PayrollController::class, 'deleteAdjustment'])->name('adjustments.destroy')->middleware($can('edit'));
                    Route::post('/{period}/delete', [PayrollController::class, 'destroy'])->name('periods.destroy')->middleware($can('delete'));

                    Route::post('/{period}/approve', [PayrollController::class, 'approve'])->name('periods.approve')->middleware('menu:finance.payroll.approve');
                    Route::post('/{period}/reopen', [PayrollController::class, 'reopen'])->name('periods.reopen')->middleware('menu:finance.payroll.approve');
                    Route::post('/{period}/pay', [PayrollController::class, 'pay'])->name('periods.pay')->middleware('menu:finance.payroll.pay');
                    Route::post('/{period}/lock', [PayrollController::class, 'lock'])->name('periods.lock')->middleware('menu:finance.payroll.lock');
                });
            });

            Route::prefix('settings')->middleware('menu:finance.payroll.settings')->group(function () {
                Route::get('/', [PayrollSettingsController::class, 'settings'])->name('settings');
                Route::post('/', [PayrollSettingsController::class, 'saveSettings'])->name('settings.save')->middleware('menu.can:finance.payroll.settings,edit');
                // Menyalakan/mematikan modul: slug fungsi tersendiri (sensitif), terpisah dari hak mengubah kebijakan.
                Route::post('/activation', [PayrollSettingsController::class, 'activation'])->name('activation')->middleware('menu:finance.payroll.activate');
            });

            Route::get('/simulation', [PayrollSettingsController::class, 'simulation'])->name('simulation')->middleware('menu:finance.payroll.simulation');
            Route::get('/simulation/snapshot', [PayrollSettingsController::class, 'simulationSnapshot'])->name('simulation.snapshot')->middleware('menu:finance.payroll.simulation');
        });
        // ── BPJS ─────────────────────────────────────────────────────────
        // Satu hub: tab Settings (kini), Letters & Report menyusul (slug sendiri per tab).
        Route::prefix('bpjs')->name('bpjs.')->group(function () {
            Route::get('/', [BpjsSettingsController::class, 'home'])->name('index')->middleware('menu:finance.bpjs.settings,finance.bpjs.report,finance.bpjs.letters');

            Route::prefix('settings')->middleware('menu:finance.bpjs.settings')->group(function () {
                Route::get('/', [BpjsSettingsController::class, 'index'])->name('settings');
                Route::get('/simulate', [BpjsSettingsController::class, 'simulate'])->name('simulate');
                Route::post('/', [BpjsSettingsController::class, 'store'])->name('settings.store')->middleware('menu.can:finance.bpjs.settings,create');
            });

            Route::prefix('report')->middleware('menu:finance.bpjs.report')->group(function () {
                Route::get('/', [FinanceReportController::class, 'bpjs'])->name('report');
                Route::get('/export', [FinanceReportController::class, 'bpjsExport'])->name('report.export');
            });

            Route::prefix('letters')->middleware('menu:finance.bpjs.letters')->group(function () {
                Route::get('/', [BpjsLetterController::class, 'index'])->name('letters');
                Route::post('/', [BpjsLetterController::class, 'store'])->name('letters.store')->middleware('menu.can:finance.bpjs.letters,create');
                Route::whereNumber('letter')->group(function () {
                    Route::get('/{letter}/pdf', [BpjsLetterController::class, 'pdf'])->name('letters.pdf');
                    Route::post('/{letter}/void', [BpjsLetterController::class, 'void'])->name('letters.void')->middleware('menu.can:finance.bpjs.letters,delete');
                });
            });
        });
        // ── PPh 21 ───────────────────────────────────────────────────────
        Route::prefix('pph21')->name('pph21.')->group(function () {
            $tabs = 'menu:finance.pph21.settings,finance.pph21.report';
            $can  = fn (string $action) => "menu.can:finance.pph21.settings,{$action}";

            Route::get('/', [Pph21SettingsController::class, 'home'])->name('index')->middleware($tabs);

            Route::prefix('report')->middleware('menu:finance.pph21.report')->group(function () {
                Route::get('/', [FinanceReportController::class, 'pph21'])->name('report');
                Route::get('/export', [FinanceReportController::class, 'pph21Export'])->name('report.export');
            });

            Route::prefix('settings')->middleware('menu:finance.pph21.settings')->group(function () use ($can) {
                Route::get('/', [Pph21SettingsController::class, 'index'])->name('settings');
                Route::post('/years', [Pph21SettingsController::class, 'createYear'])->name('years.store')->middleware($can('create'));

                Route::whereNumber('year')->group(function () use ($can) {
                    Route::post('/{year}/ptkp', [Pph21SettingsController::class, 'savePtkp'])->name('ptkp.save')->middleware($can('edit'));
                    Route::post('/{year}/brackets', [Pph21SettingsController::class, 'saveBrackets'])->name('brackets.save')->middleware($can('edit'));
                    Route::post('/{year}/ter/{category}', [Pph21SettingsController::class, 'saveTer'])->whereIn('category', ['A', 'B', 'C'])->name('ter.save')->middleware($can('edit'));
                    Route::post('/{year}/settings', [Pph21SettingsController::class, 'saveSettings'])->name('settings.save')->middleware($can('edit'));
                });
            });
        });
    });
