<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Delivery\DeliverySupportController;
use App\Http\Controllers\Delivery\DeliverySupportPlanningController;
use App\Http\Controllers\Delivery\DeliverySupportPhaseController;
use App\Http\Controllers\Delivery\DeliverySupportActivityController;
use App\Http\Controllers\Delivery\DeliverySupportStageController;
use App\Http\Controllers\Delivery\DeliverySupportDataController;
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
    Route::get('/create', [DeliverySupportController::class, 'create'])->name('create');
    Route::post('/', [DeliverySupportController::class, 'store'])->name('store');

    // =========================================================================
    // SUPPORT-SPECIFIC ROUTES
    // =========================================================================
    Route::prefix('{support}')->group(function () {

        // View support details
        Route::get('/', [DeliverySupportController::class, 'show'])->name('show');

        // Update support (section-based via AJAX)
        Route::patch('/field', [DeliverySupportController::class, 'updateField'])->name('update-field');

        // Generate OneDrive folder
        Route::post('/generate-folder', [DeliverySupportController::class, 'generateFolder'])->name('generate-folder');
        // Delete OneDrive folder
        Route::delete('/folder', [DeliverySupportController::class, 'deleteFolder'])->name('delete-folder');

        // Delete support
        Route::delete('/', [DeliverySupportController::class, 'destroy'])->name('destroy');

        // =====================================================================
        // TICKET ASSIGNMENT
        // =====================================================================
        // Assign ticket to support - creates activity automatically
        Route::post('/assign-ticket', [DeliverySupportController::class, 'assignTicket'])->name('assign-ticket');

        // =====================================================================
        // PLANNING
        // =====================================================================
        Route::prefix('planning')->name('planning.')->group(function () {
            // Planning page view
            Route::get('/', [DeliverySupportPlanningController::class, 'index'])->name('index');

            // Planning data API
            Route::get('/data', [DeliverySupportPlanningController::class, 'getData'])->name('data');

            // Planning CRUD
            Route::post('/', [DeliverySupportPlanningController::class, 'store'])->name('store');
            Route::put('/{planning}', [DeliverySupportPlanningController::class, 'update'])->name('update');
            Route::delete('/{planning}', [DeliverySupportPlanningController::class, 'destroy'])->name('destroy');

            // Reorder planning items
            Route::post('/reorder', [DeliverySupportPlanningController::class, 'reorder'])->name('reorder');
        });

        // =====================================================================
        // PHASES
        // =====================================================================
        Route::prefix('phases')->name('phases.')->group(function () {
            // List phases
            Route::get('/', [DeliverySupportPhaseController::class, 'index'])->name('index');

            // Create phase
            Route::post('/', [DeliverySupportPhaseController::class, 'store'])->name('store');

            // Batch update phases
            Route::post('/batch', [DeliverySupportPhaseController::class, 'batchUpdate'])->name('batch');

            // Reorder phases
            Route::post('/reorder', [DeliverySupportPhaseController::class, 'reorder'])->name('reorder');

            // Single phase operations
            Route::get('/{phase}', [DeliverySupportPhaseController::class, 'show'])->name('show');
            Route::put('/{phase}', [DeliverySupportPhaseController::class, 'update'])->name('update');
            Route::delete('/{phase}', [DeliverySupportPhaseController::class, 'destroy'])->name('destroy');
            Route::post('/{phase}/toggle', [DeliverySupportPhaseController::class, 'toggleVisibility'])->name('toggle');
        });

        // =====================================================================
        // ACTIVITIES
        // =====================================================================
        Route::prefix('activities')->name('activities.')->group(function () {
            // List activities (optionally filtered by phase)
            Route::get('/', [DeliverySupportActivityController::class, 'index'])->name('index');
            Route::get('/phase/{phase}', [DeliverySupportActivityController::class, 'indexByPhase'])->name('by-phase');

            // Create activity
            Route::post('/', [DeliverySupportActivityController::class, 'store'])->name('store');

            // Bulk operations
            Route::post('/reorder', [DeliverySupportActivityController::class, 'reorder'])->name('reorder');
            Route::post('/bulk-progress', [DeliverySupportActivityController::class, 'bulkUpdateProgress'])->name('bulk-progress');

            // Single activity operations
            Route::get('/{activity}', [DeliverySupportActivityController::class, 'show'])->name('show');
            Route::put('/{activity}', [DeliverySupportActivityController::class, 'update'])->name('update');
            Route::delete('/{activity}', [DeliverySupportActivityController::class, 'destroy'])->name('destroy');

            // Employee assignment
            Route::get('/{activity}/employees', [DeliverySupportActivityController::class, 'getAssignedEmployees'])->name('employees.index');
            Route::post('/{activity}/employees', [DeliverySupportActivityController::class, 'assignEmployee'])->name('employees.store');
            Route::put('/{activity}/employees/{employeeId}', [DeliverySupportActivityController::class, 'updateAssignment'])->name('employees.update');
            Route::delete('/{activity}/employees/{employeeId}', [DeliverySupportActivityController::class, 'unassignEmployee'])->name('employees.destroy');
        });

        // =====================================================================
        // STAGES
        // =====================================================================
        Route::prefix('stages')->name('stages.')->group(function () {
            // Stages for an activity
            Route::get('/activity/{activity}', [DeliverySupportStageController::class, 'index'])->name('index');

            // Create stage
            Route::post('/activity/{activity}', [DeliverySupportStageController::class, 'store'])->name('store');

            // Batch update stages
            Route::post('/activity/{activity}/batch', [DeliverySupportStageController::class, 'batchUpdate'])->name('batch');

            // Reorder stages
            Route::post('/activity/{activity}/reorder', [DeliverySupportStageController::class, 'reorder'])->name('reorder');

            // Single stage operations
            Route::get('/{stage}', [DeliverySupportStageController::class, 'show'])->name('show');
            Route::put('/{stage}', [DeliverySupportStageController::class, 'update'])->name('update');
            Route::delete('/{stage}', [DeliverySupportStageController::class, 'destroy'])->name('destroy');
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
            Route::get('/', [DeliverySupportController::class, 'getDocuments'])->name('index');
            Route::post('/', [DeliverySupportController::class, 'storeDocument'])->name('store');
            Route::put('/{document}', [DeliverySupportController::class, 'updateDocument'])->name('update');
            Route::delete('/{document}', [DeliverySupportController::class, 'destroyDocument'])->name('destroy');
        });

        // =====================================================================
        // UPDATES/NOTES
        // =====================================================================
        Route::prefix('updates')->name('updates.')->group(function () {
            Route::get('/', [DeliverySupportController::class, 'getUpdates'])->name('index');
            Route::post('/', [DeliverySupportController::class, 'storeUpdate'])->name('store');
            Route::put('/{update}', [DeliverySupportController::class, 'updateUpdate'])->name('update');
            Route::delete('/{update}', [DeliverySupportController::class, 'destroyUpdate'])->name('destroy');
        });

        // =====================================================================
        // TEAM MEMBERS
        // =====================================================================
        Route::prefix('team')->name('team.')->group(function () {
            Route::get('/', [DeliverySupportController::class, 'getTeamMembers'])->name('index');
            Route::post('/', [DeliverySupportController::class, 'addTeamMember'])->name('store');
            Route::delete('/{employee}', [DeliverySupportController::class, 'removeTeamMember'])->name('destroy');
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
