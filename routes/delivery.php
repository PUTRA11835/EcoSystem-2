<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Delivery\DeliveryPhaseController;
use App\Http\Controllers\Delivery\DeliveryGroupController;
use App\Http\Controllers\Delivery\DeliveryStageController;
use App\Http\Controllers\Delivery\DeliveryActivityController;
use App\Http\Controllers\Delivery\DeliveryDataController;
use App\Http\Middleware\CheckAuthToken;

/**
 * ============================================================================
 * DELIVERY PLANNING ROUTES
 * ============================================================================
 *
 * Struktur hierarki yang didukung:
 * Phase → Group → Stage → Activity
 * Phase → Group → Activity (tanpa Stage)
 *
 * Simplified structure (tanpa junction table):
 * - Phase langsung per project
 * - Tidak ada template global
 *
 * Prefix: /delivery
 */

Route::prefix('delivery')->middleware(CheckAuthToken::class)->group(function () {

    // =========================================================================
    // PROJECT-SPECIFIC ROUTES
    // =========================================================================
    Route::prefix('projects/{project}')->group(function () {

        // =====================================================================
        // PHASES (langsung per project)
        // =====================================================================
        Route::prefix('phases')->group(function () {
            Route::get('/', [DeliveryPhaseController::class, 'index']);
            Route::post('/', [DeliveryPhaseController::class, 'store']);
            Route::get('/{phase}', [DeliveryPhaseController::class, 'show']);
            Route::put('/{phase}', [DeliveryPhaseController::class, 'update']);
            Route::delete('/{phase}', [DeliveryPhaseController::class, 'destroy']);
            Route::post('/reorder', [DeliveryPhaseController::class, 'reorder']);
            Route::post('/{phase}/recalculate', [DeliveryPhaseController::class, 'recalculateProgress']);
        });

        // =====================================================================
        // GROUPS
        // =====================================================================
        Route::prefix('groups')->group(function () {
            // List groups for a phase
            Route::get('/phase/{phase}', [DeliveryGroupController::class, 'index']);

            // CRUD
            Route::post('/', [DeliveryGroupController::class, 'store']);
            Route::get('/{group}', [DeliveryGroupController::class, 'show']);
            Route::put('/{group}', [DeliveryGroupController::class, 'update']);
            Route::delete('/{group}', [DeliveryGroupController::class, 'destroy']);

            // Operations
            Route::post('/reorder', [DeliveryGroupController::class, 'reorder']);
            Route::post('/{group}/move', [DeliveryGroupController::class, 'move']);
            Route::post('/{group}/recalculate', [DeliveryGroupController::class, 'recalculateProgress']);
        });

        // =====================================================================
        // STAGES
        // =====================================================================
        Route::prefix('stages')->group(function () {
            // List stages for a group
            Route::get('/group/{group}', [DeliveryStageController::class, 'index']);

            // CRUD
            Route::post('/group/{group}', [DeliveryStageController::class, 'store']);
            Route::get('/{stage}', [DeliveryStageController::class, 'show']);
            Route::put('/{stage}', [DeliveryStageController::class, 'update']);
            Route::delete('/{stage}', [DeliveryStageController::class, 'destroy']);

            // Operations
            Route::post('/group/{group}/reorder', [DeliveryStageController::class, 'reorder']);
            Route::post('/{stage}/move', [DeliveryStageController::class, 'move']);
            Route::post('/{stage}/recalculate', [DeliveryStageController::class, 'recalculateProgress']);
        });

        // =====================================================================
        // ACTIVITIES
        // =====================================================================
        Route::prefix('activities')->group(function () {
            // List activities
            Route::get('/stage/{stage}', [DeliveryActivityController::class, 'indexByStage']);
            Route::get('/group/{group}', [DeliveryActivityController::class, 'indexByGroup']);

            // CRUD
            Route::post('/', [DeliveryActivityController::class, 'store']);
            Route::get('/{activity}', [DeliveryActivityController::class, 'show']);
            Route::put('/{activity}', [DeliveryActivityController::class, 'update']);
            Route::delete('/{activity}', [DeliveryActivityController::class, 'destroy']);

            // Operations
            Route::post('/reorder', [DeliveryActivityController::class, 'reorder']);
            Route::post('/bulk-progress', [DeliveryActivityController::class, 'bulkUpdateProgress']);
            Route::post('/{activity}/move', [DeliveryActivityController::class, 'move']);

            // Employee Assignment
            Route::get('/{activity}/employees', [DeliveryActivityController::class, 'getAssignedEmployees']);
            Route::post('/{activity}/employees', [DeliveryActivityController::class, 'assignEmployee']);
            Route::put('/{activity}/employees/{employeeId}', [DeliveryActivityController::class, 'updateAssignment']);
            Route::delete('/{activity}/employees/{employeeId}', [DeliveryActivityController::class, 'unassignEmployee']);
        });

        // =====================================================================
        // DATA ENDPOINTS (Table, Gantt, S-Curve)
        // =====================================================================
        Route::prefix('data')->group(function () {
            Route::get('/table', [DeliveryDataController::class, 'getTableData']);
            Route::get('/gantt', [DeliveryDataController::class, 'getGanttData']);
            Route::get('/scurve', [DeliveryDataController::class, 'getSCurveData']);
            Route::get('/summary', [DeliveryDataController::class, 'getProjectSummary']);
        });
    });
});
