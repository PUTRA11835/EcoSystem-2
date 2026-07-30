<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Delivery\DeliverySupportController;
use App\Http\Controllers\Delivery\DeliverySupportPlanningController;
use App\Http\Controllers\Delivery\DeliverySupportPhaseController;
use App\Http\Controllers\Delivery\DeliverySupportActivityController;
use App\Http\Controllers\Delivery\DeliverySupportStageController;
use App\Http\Controllers\Delivery\DeliverySupportDataController;
use App\Http\Controllers\DeliverySupportPaymentTermController;
use App\Http\Controllers\DeliverySupportCostController;
use App\Http\Middleware\CheckAuthToken;

/**
 * ============================================================================
 * DELIVERY SUPPORT ROUTES
 * ============================================================================
 *
 * Routes for managing support deliveries, including:
 * - Support CRUD operations
 * - Phase management
 * - Activity management
 * - Stage management
 * - Planning/scheduling
 * - Data endpoints (table, gantt, s-curve)
 *
 * Prefix: /delivery/support
 * @date 2026-02-09
 */

Route::prefix('delivery/support')->middleware(CheckAuthToken::class)->name('delivery.support.')->group(function () {

    // =========================================================================
    // SUPPORT LIST & CRUD
    // =========================================================================

    // List all support items
    Route::get('/', [DeliverySupportController::class, 'index'])->name('index');

    // Planning list (all supports with planning progress)
    Route::get('/planning', [DeliverySupportController::class, 'planningList'])->name('planning-list');

    // Create new support
    Route::middleware('menu:delivery-support.add-new')->group(function () {
        Route::get('/create', [DeliverySupportController::class, 'create'])->name('create');
        Route::post('/', [DeliverySupportController::class, 'store'])->name('store');
    });

    // =========================================================================
    // SUPPORT-SPECIFIC ROUTES
    // =========================================================================
    Route::prefix('{support}')->group(function () {

        // ---------------------------------------------------------------------
        // Konvensi izin per section: <modul>.<section>.<aksi>
        //   .view   → endpoint baca section (GET)
        //   .edit   → mengubah record yang sudah ada (PATCH/PUT)
        //   .manage → menambah & menghapus record (POST/DELETE)
        // Mirror dari Delivery Project — lihat routes/web.php.
        // ---------------------------------------------------------------------

        // View support details
        Route::get('/', [DeliverySupportController::class, 'show'])->name('show');

        // Full edit page (mirror of create form, pre-filled) + update section General
        Route::middleware('menu:delivery-support.general.edit')->group(function () {
            Route::get('/edit', [DeliverySupportController::class, 'edit'])->name('edit');
            Route::put('/', [DeliverySupportController::class, 'update'])->name('update');
        });

        // Update support (section-based via AJAX). Satu endpoint melayani beberapa
        // section (support-info / approval-info / team-info), jadi izinnya TIDAK
        // bisa ditentukan di level route — dicek per `section` di updateField().
        Route::patch('/field', [DeliverySupportController::class, 'updateField'])->name('update-field');

        // Folder OneDrive & customer deliverable → section Documents
        Route::middleware('menu:delivery-support.documents.manage')->group(function () {
            Route::post('/generate-folder', [DeliverySupportController::class, 'generateFolder'])->name('generate-folder');
            Route::delete('/folder', [DeliverySupportController::class, 'deleteFolder'])->name('delete-folder');
            Route::post('/folder/delete', [DeliverySupportController::class, 'deleteFolder'])->name('delete-folder-post');

            Route::post('/generate-deliverable-folder', [DeliverySupportController::class, 'generateCustomerDeliverableFolder'])->name('generate-deliverable-folder');
            Route::delete('/deliverable-folder', [DeliverySupportController::class, 'deleteCustomerDeliverableFolder'])->name('delete-deliverable-folder');
            Route::post('/deliverable-folder/delete', [DeliverySupportController::class, 'deleteCustomerDeliverableFolder'])->name('delete-deliverable-folder-post');
            Route::post('/deliverable-share-link', [DeliverySupportController::class, 'getDeliverableShareLink'])->name('deliverable-share-link');
        });
        // List sub-folders inside the customer deliverable folder
        Route::get('/deliverable-subfolders', [DeliverySupportController::class, 'getDeliverableSubfolders'])->name('deliverable-subfolders')->middleware('menu:delivery-support.documents.view');

        // Delete support
        Route::middleware('menu:delivery-support.delete-support')->group(function () {
            Route::delete('/', [DeliverySupportController::class, 'destroy'])->name('destroy');
            Route::post('/delete', [DeliverySupportController::class, 'destroy'])->name('destroy-post');
        });

        // =====================================================================
        // TICKET ASSIGNMENT
        // =====================================================================
        // Assign ticket to support - creates activity automatically
        Route::post('/assign-ticket', [DeliverySupportController::class, 'assignTicket'])->name('assign-ticket');

        // =====================================================================
        // PLANNING
        // =====================================================================
        Route::prefix('planning')->name('planning.')->group(function () {
            Route::middleware('menu:delivery-support.activities.view')->group(function () {
                // Planning page view
                Route::get('/', [DeliverySupportPlanningController::class, 'index'])->name('index');
                // Planning data API
                Route::get('/data', [DeliverySupportPlanningController::class, 'getData'])->name('data');
            });

            Route::middleware('menu:delivery-support.activities.edit')->group(function () {
                Route::put('/{planning}', [DeliverySupportPlanningController::class, 'update'])->name('update');
                Route::post('/reorder', [DeliverySupportPlanningController::class, 'reorder'])->name('reorder');
            });

            Route::middleware('menu:delivery-support.activities.manage')->group(function () {
                Route::post('/', [DeliverySupportPlanningController::class, 'store'])->name('store');
                Route::delete('/{planning}', [DeliverySupportPlanningController::class, 'destroy'])->name('destroy');
                Route::post('/{planning}/delete', [DeliverySupportPlanningController::class, 'destroy'])->name('destroy-post');
            });
        });

        // =====================================================================
        // PHASES
        // =====================================================================
        Route::prefix('phases')->name('phases.')->group(function () {
            Route::middleware('menu:delivery-support.activities.view')->group(function () {
                Route::get('/', [DeliverySupportPhaseController::class, 'index'])->name('index');
                Route::get('/{phase}', [DeliverySupportPhaseController::class, 'show'])->name('show');
            });

            Route::middleware('menu:delivery-support.activities.edit')->group(function () {
                Route::post('/batch', [DeliverySupportPhaseController::class, 'batchUpdate'])->name('batch');
                Route::post('/reorder', [DeliverySupportPhaseController::class, 'reorder'])->name('reorder');
                Route::put('/{phase}', [DeliverySupportPhaseController::class, 'update'])->name('update');
                Route::post('/{phase}/toggle', [DeliverySupportPhaseController::class, 'toggleVisibility'])->name('toggle');
            });

            Route::middleware('menu:delivery-support.activities.manage')->group(function () {
                Route::post('/', [DeliverySupportPhaseController::class, 'store'])->name('store');
                Route::delete('/{phase}', [DeliverySupportPhaseController::class, 'destroy'])->name('destroy');
                Route::post('/{phase}/delete', [DeliverySupportPhaseController::class, 'destroy'])->name('destroy-post');
            });
        });

        // =====================================================================
        // ACTIVITIES
        // =====================================================================
        Route::prefix('activities')->name('activities.')->group(function () {
            Route::middleware('menu:delivery-support.activities.view')->group(function () {
                // List activities (optionally filtered by phase)
                Route::get('/', [DeliverySupportActivityController::class, 'index'])->name('index');
                Route::get('/phase/{phase}', [DeliverySupportActivityController::class, 'indexByPhase'])->name('by-phase');
                Route::get('/{activity}', [DeliverySupportActivityController::class, 'show'])->name('show');
                Route::get('/{activity}/employees', [DeliverySupportActivityController::class, 'getAssignedEmployees'])->name('employees.index');
            });

            Route::middleware('menu:delivery-support.activities.edit')->group(function () {
                Route::post('/reorder', [DeliverySupportActivityController::class, 'reorder'])->name('reorder');
                Route::post('/bulk-progress', [DeliverySupportActivityController::class, 'bulkUpdateProgress'])->name('bulk-progress');
                Route::put('/{activity}', [DeliverySupportActivityController::class, 'update'])->name('update');
                Route::patch('/{activity}/remove-ticket', [DeliverySupportActivityController::class, 'removeTicketLink'])->name('remove-ticket');
                Route::put('/{activity}/employees/{employeeId}', [DeliverySupportActivityController::class, 'updateAssignment'])->name('employees.update');
            });

            Route::middleware('menu:delivery-support.activities.manage')->group(function () {
                Route::post('/', [DeliverySupportActivityController::class, 'store'])->name('store');
                Route::delete('/{activity}', [DeliverySupportActivityController::class, 'destroy'])->name('destroy');
                Route::post('/{activity}/delete', [DeliverySupportActivityController::class, 'destroy'])->name('destroy-post');
                Route::post('/{activity}/employees', [DeliverySupportActivityController::class, 'assignEmployee'])->name('employees.store');
                Route::delete('/{activity}/employees/{employeeId}', [DeliverySupportActivityController::class, 'unassignEmployee'])->name('employees.destroy');
                Route::post('/{activity}/employees/{employeeId}/delete', [DeliverySupportActivityController::class, 'unassignEmployee'])->name('employees.destroy-post');
            });
        });

        // =====================================================================
        // STAGES
        // =====================================================================
        Route::prefix('stages')->name('stages.')->group(function () {
            Route::middleware('menu:delivery-support.activities.view')->group(function () {
                // Stages for an activity
                Route::get('/activity/{activity}', [DeliverySupportStageController::class, 'index'])->name('index');
                Route::get('/{stage}', [DeliverySupportStageController::class, 'show'])->name('show');
            });

            Route::middleware('menu:delivery-support.activities.edit')->group(function () {
                Route::post('/activity/{activity}/batch', [DeliverySupportStageController::class, 'batchUpdate'])->name('batch');
                Route::post('/activity/{activity}/reorder', [DeliverySupportStageController::class, 'reorder'])->name('reorder');
                Route::put('/{stage}', [DeliverySupportStageController::class, 'update'])->name('update');
            });

            Route::middleware('menu:delivery-support.activities.manage')->group(function () {
                Route::post('/activity/{activity}', [DeliverySupportStageController::class, 'store'])->name('store');
                Route::delete('/{stage}', [DeliverySupportStageController::class, 'destroy'])->name('destroy');
                Route::post('/{stage}/delete', [DeliverySupportStageController::class, 'destroy'])->name('destroy-post');
            });
        });

        // =====================================================================
        // DATA ENDPOINTS (Table, Gantt, S-Curve)
        // =====================================================================
        Route::prefix('data')->name('data.')->group(function () {
            Route::get('/table', [DeliverySupportDataController::class, 'getTableData'])->name('table');
            Route::get('/gantt', [DeliverySupportDataController::class, 'getGanttData'])->name('gantt');
            Route::get('/scurve', [DeliverySupportDataController::class, 'getSCurveData'])->name('scurve');
            Route::get('/summary', [DeliverySupportDataController::class, 'getSummary'])->name('summary');
        });

        // =====================================================================
        // DOCUMENTS
        // =====================================================================
        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/', [DeliverySupportController::class, 'getDocuments'])->name('index')->middleware('menu:delivery-support.documents.view');
            Route::put('/{document}', [DeliverySupportController::class, 'updateDocument'])->name('update')->middleware('menu:delivery-support.documents.edit');

            Route::middleware('menu:delivery-support.documents.manage')->group(function () {
                Route::post('/', [DeliverySupportController::class, 'storeDocument'])->name('store');
                Route::delete('/{document}', [DeliverySupportController::class, 'destroyDocument'])->name('destroy');
                Route::post('/{document}/delete', [DeliverySupportController::class, 'destroyDocument'])->name('destroy-post');
            });
        });

        // =====================================================================
        // UPDATES/NOTES — bagian dari section Activities
        // =====================================================================
        Route::prefix('updates')->name('updates.')->group(function () {
            Route::get('/', [DeliverySupportController::class, 'getUpdates'])->name('index')->middleware('menu:delivery-support.activities.view');
            Route::put('/{update}', [DeliverySupportController::class, 'updateUpdate'])->name('update')->middleware('menu:delivery-support.activities.edit');

            Route::middleware('menu:delivery-support.activities.manage')->group(function () {
                Route::post('/', [DeliverySupportController::class, 'storeUpdate'])->name('store');
                Route::delete('/{update}', [DeliverySupportController::class, 'destroyUpdate'])->name('destroy');
                Route::post('/{update}/delete', [DeliverySupportController::class, 'destroyUpdate'])->name('destroy-post');
            });
        });

        // =====================================================================
        // TEAM MEMBERS — hanya tambah/hapus, tidak ada aksi edit
        // =====================================================================
        Route::prefix('team')->name('team.')->group(function () {
            Route::get('/', [DeliverySupportController::class, 'getTeamMembers'])->name('index')->middleware('menu:delivery-support.team.view');

            Route::middleware('menu:delivery-support.team.manage')->group(function () {
                Route::post('/', [DeliverySupportController::class, 'addTeamMember'])->name('store');
                Route::delete('/{employee}', [DeliverySupportController::class, 'removeTeamMember'])->name('destroy');
                Route::post('/{employee}/delete', [DeliverySupportController::class, 'removeTeamMember'])->name('destroy-post');
            });
        });

        // =====================================================================
        // CUSTOMER PIC — sync menggantikan seluruh daftar sekaligus (= edit)
        // =====================================================================
        Route::middleware('menu:delivery-support.customer-pic.view')->group(function () {
            Route::get('/client-contacts', [DeliverySupportController::class, 'getClientContacts'])->name('client-contacts');
            Route::get('/customer-pics', [DeliverySupportController::class, 'getCustomerPics'])->name('customer-pics.index');
        });
        Route::post('/customer-pics', [DeliverySupportController::class, 'syncCustomerPics'])->name('customer-pics.sync')->middleware('menu:delivery-support.customer-pic.edit');

        // =====================================================================
        // FINANCIAL / SALES DATA (IO Number, Revenue, Plan Cost, Gross Profit)
        // =====================================================================
        Route::patch('/financial-info', [DeliverySupportController::class, 'updateFinancialInfo'])->name('financial-info.update')->middleware('menu:delivery-support.financial.edit');

        // =====================================================================
        // TERM OF PAYMENT (TOP) PLAN — tampil di dalam section Financial
        // =====================================================================
        Route::get('/payment-terms',        [DeliverySupportPaymentTermController::class, 'index'])->name('paymentTerms.index')->middleware('menu:delivery-support.financial.view');
        Route::put('/payment-terms/{term}', [DeliverySupportPaymentTermController::class, 'update'])->name('paymentTerms.update')->middleware('menu:delivery-support.financial.edit');

        Route::middleware('menu:delivery-support.financial.manage')->group(function () {
            Route::post('/payment-terms',               [DeliverySupportPaymentTermController::class, 'store'])->name('paymentTerms.store');
            Route::delete('/payment-terms/{term}',      [DeliverySupportPaymentTermController::class, 'destroy'])->name('paymentTerms.destroy');
            Route::post('/payment-terms/{term}/delete', [DeliverySupportPaymentTermController::class, 'destroy'])->name('paymentTerms.destroy-post');
        });

        // =====================================================================
        // PLAN COST
        // =====================================================================
        Route::middleware('menu:delivery-support.plan-cost.view')->group(function () {
            Route::get('/costs',                          [DeliverySupportCostController::class, 'index'])->name('costs.index');
            Route::get('/costs/{cost}/items',             [DeliverySupportCostController::class, 'indexItems'])->name('costs.items.index');
        });

        Route::middleware('menu:delivery-support.plan-cost.edit')->group(function () {
            Route::put('/costs/{cost}',                   [DeliverySupportCostController::class, 'update'])->name('costs.update');
            Route::put('/costs/{cost}/items/{item}',      [DeliverySupportCostController::class, 'updateItem'])->name('costs.items.update');
        });

        Route::middleware('menu:delivery-support.plan-cost.manage')->group(function () {
            Route::post('/costs',                             [DeliverySupportCostController::class, 'store'])->name('costs.store');
            Route::post('/costs/init',                        [DeliverySupportCostController::class, 'init'])->name('costs.init');
            Route::delete('/costs/{cost}',                    [DeliverySupportCostController::class, 'destroy'])->name('costs.destroy');
            Route::post('/costs/{cost}/delete',               [DeliverySupportCostController::class, 'destroy'])->name('costs.destroy-post');
            // Expense line-items
            Route::post('/costs/{cost}/items',                [DeliverySupportCostController::class, 'storeItem'])->name('costs.items.store');
            Route::delete('/costs/{cost}/items/{item}',       [DeliverySupportCostController::class, 'destroyItem'])->name('costs.items.destroy');
            Route::post('/costs/{cost}/items/{item}/delete',  [DeliverySupportCostController::class, 'destroyItem'])->name('costs.items.destroy-post');
        });
    });
});

/**
 * ============================================================================
 * API ROUTES FOR DELIVERY SUPPORT
 * ============================================================================
 */
Route::prefix('api/delivery/support')->middleware(CheckAuthToken::class)->name('api.delivery.support.')->group(function () {

    // Quick search/lookup
    Route::get('/search', [DeliverySupportController::class, 'search'])->name('search');

    // Get support by ticket
    Route::get('/ticket/{ticketId}', [DeliverySupportController::class, 'findByTicket'])->name('by-ticket');

    // Statistics
    Route::get('/statistics', [DeliverySupportController::class, 'getStatistics'])->name('statistics');
});
