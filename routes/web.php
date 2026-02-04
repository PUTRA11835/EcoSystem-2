<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectUpdateController;
use App\Http\Controllers\ProjectPlanController;
use App\Http\Controllers\ProjectPlanningController;
use App\Http\Controllers\ProjectGanttController;
use App\Http\Controllers\DynamicPhaseController;
use App\Http\Controllers\ActivityStageController;
use App\Http\Controllers\ActivityManagementController;
use App\Http\Controllers\ProjectDataController;
use App\Http\Controllers\StageManagementController;
use App\Http\Controllers\ProjectPlanningExportController;
use App\Http\Middleware\CheckAuthToken;

// ==================== PUBLIC ROUTES ====================

// ✅ CSRF Cookie Route - Required untuk AJAX login
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});

// Login page
Route::get('/auth/login', [AuthController::class, 'showLogin'])->name('login');

// Login API
Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

// Logout routes
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/logout', function () {
    return redirect()->route('login')->with('info', 'Please use logout button');
})->name('logout.redirect');

// ==================== PROTECTED ROUTES ====================

Route::middleware(CheckAuthToken::class)->group(function () {
    
    // Auth verification
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

    // ==================== DASHBOARD ROUTES ====================
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/employee', [DashboardController::class, 'index'])->name('dashboardEmployee');
    Route::get('/dashboard/customer', [DashboardController::class, 'index'])->name('dashboardCustomer');

    // ==================== CALENDAR ====================
    Route::prefix('calendar')->name('calendar.')->group(function () {
        Route::get('/', [CalendarController::class, 'index'])->name('index');
        Route::get('/events', [CalendarController::class, 'events'])->name('events');
        Route::get('/timesheets', [CalendarController::class, 'timesheets'])->name('timesheets');
    });

    // ==================== REPORTING ====================
    Route::get('/reporting', function () {
        return view('reporting.reporting', ['user' => session('user')]);
    })->name('reporting');

    // ==================== MASTER ====================
    Route::prefix('master')->name('master.')->group(function () {
        // Employee routes
        Route::prefix('employee')->name('employee.')->group(function () {
            Route::get('/', [EmployeeController::class, 'index'])->name('index');
            Route::get('/{id}', [EmployeeController::class, 'show'])->name('detail');
        });
        
        // Customer routes
        Route::prefix('customer')->name('customer.')->group(function () {
            Route::get('/', [CustomerController::class, 'index'])->name('index');
            Route::get('/{id}', [CustomerController::class, 'show'])->name('detail');
        });
    });

    // ==================== FINANCIAL ====================
    Route::get('/financial', function () {
        return view('financial.financial', ['user' => session('user')]);
    })->name('financial');

    // ==================== HR & GENERAL ====================
    Route::get('/general', function () {
        return view('general.general', ['user' => session('user')]);
    })->name('general');

    // ==================== BUSINESS ====================
    Route::get('/business', function () {
        return view('business.business', ['user' => session('user')]);
    })->name('business');

    // ==================== DELIVERY SUPPORT ====================
    Route::prefix('delivery')->name('delivery.')->group(function () {
        // Support/Ticket Management
        Route::get('/support', function () {
            $user = session('user');
            return view('delivery.support.index', ['user' => $user]);
        })->name('support.index');
    });

    // ==================== RPMO ====================
    Route::get('/rpmo', function () {
        return view('rpmo.rpmo', ['user' => session('user')]);
    })->name('rpmo');

    // ==================== LEGAL ====================
    Route::get('/legal', function () {
        return view('legal.legal', ['user' => session('user')]);
    })->name('legal');

    // ==================== SETTINGS ====================
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/preferences', [SettingsController::class, 'updatePreferences'])->name('preferences');
        Route::post('/reset', [SettingsController::class, 'resetPreferences'])->name('reset');
    });

    // ==================== DASHBOARD API ====================
    Route::get('/dashboard/monthly-trends', [DashboardController::class, 'getMonthlyTrends'])->name('dashboard.trends');
    Route::get('/dashboard/project-progress', [DashboardController::class, 'getProjectProgress'])->name('dashboard.progress');
    Route::post('/dashboard/clear-cache', [DashboardController::class, 'clearCache'])->name('dashboard.clear-cache');
    Route::get('/dashboard/export', [DashboardController::class, 'export'])->name('dashboard.export');

    // ==================== PROJECT DELIVERY ROUTES ====================

    // Project routes (CRUD)
    Route::resource('projects', ProjectController::class)->except(['edit', 'update']);
    Route::patch('/projects/{project}/update-field', [ProjectController::class, 'updateField'])->name('projects.updateField');
    Route::patch('/projects/{project}/delivery-info', [ProjectController::class, 'updateDeliveryInfo'])->name('projects.updateDeliveryInfo');
    Route::patch('/projects/{project}/location-info', [ProjectController::class, 'updateLocationInfo'])->name('projects.updateLocationInfo');

    // Document management routes
    Route::post('/projects/{project}/documents', [ProjectController::class, 'storeDocument'])->name('project.documents.store');
    Route::patch('/project/documents/{document}', [ProjectController::class, 'updateDocument'])->name('project.documents.update');
    Route::delete('/project/documents/{document}', [ProjectController::class, 'destroyDocument'])->name('project.documents.destroy');

    // Team member management routes
    Route::get('/projects/{project}/team-members', [ProjectController::class, 'getTeamMembers'])->name('projects.team.index');
    Route::post('/projects/{project}/team-members', [ProjectController::class, 'storeTeamMember'])->name('projects.team.store');
    Route::put('/projects/{project}/team-members/{employee}', [ProjectController::class, 'updateTeamMember'])->name('projects.team.update');
    Route::delete('/projects/{project}/team-members/{employee}', [ProjectController::class, 'destroyTeamMember'])->name('projects.team.destroy');

    // Project updates/issues routes
    Route::post('/projects/{project}/updates', [ProjectUpdateController::class, 'store'])->name('project.updates.store');
    Route::patch('/project-updates/{project_update}', [ProjectUpdateController::class, 'update'])->name('project.updates.update');
    Route::delete('/project-updates/{project_update}', [ProjectUpdateController::class, 'destroy'])->name('project.updates.destroy');
    Route::get('/project-updates/{project_update}/edit', [ProjectUpdateController::class, 'edit'])->name('project.updates.edit');

    // API routes for regions/cities
    Route::get('/api/regions', [ProjectController::class, 'getRegions'])->name('api.regions');
    Route::get('/api/cities', [ProjectController::class, 'getCities'])->name('api.cities');

    // Issues routes
    Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
    Route::get('/issues/{project}', [IssueController::class, 'show'])->name('issues.show');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ==================== PROJECT PLANNING ROUTES ====================

    // Planning index - list all projects
    Route::get('/planning', [ProjectPlanningController::class, 'index'])->name('planning.index');

    // Backward compatibility redirect
    Route::redirect('/projects-planning', '/planning')->name('projects-planning.index');

    // Project-specific planning routes
    Route::prefix('planning/{project}')->name('planning.')->group(function () {

        // Main planning page
        Route::get('/', [ProjectPlanningController::class, 'show'])->name('show');
        Route::get('/gantt', [ProjectPlanningController::class, 'gantt'])->name('gantt');
        Route::get('/scurve', [ProjectPlanningController::class, 'scurve'])->name('scurve');
        Route::get('/phases-list', [ProjectPlanningController::class, 'getPhases'])->name('phases-list');

        // Phase Management
        Route::prefix('phases')->name('phases.')->group(function () {
            Route::get('/', [DynamicPhaseController::class, 'index'])->name('index');
            Route::post('/create-custom', [DynamicPhaseController::class, 'createCustomPhase'])->name('create');
            Route::post('/add', [DynamicPhaseController::class, 'addPhase'])->name('add');
            Route::put('/{phase}', [DynamicPhaseController::class, 'updatePhase'])->name('update');
            Route::delete('/{phase}', [DynamicPhaseController::class, 'removePhase'])->name('remove');
            Route::post('/reorder', [DynamicPhaseController::class, 'reorderPhases'])->name('reorder');
            Route::post('/{phase}/toggle', [DynamicPhaseController::class, 'togglePhaseVisibility'])->name('toggle');
        });

        // View configuration
        Route::post('/view-config', [DynamicPhaseController::class, 'updateViewConfig'])->name('view-config');

        // Activity Management
        Route::prefix('activities')->name('activities.')->group(function () {
            Route::post('/', [ActivityManagementController::class, 'store'])->name('store');
            Route::get('/{activity}', [ActivityManagementController::class, 'show'])->name('show');
            Route::put('/{activity}', [ActivityManagementController::class, 'update'])->name('update');
            Route::delete('/{activity}', [ActivityManagementController::class, 'destroy'])->name('destroy');

            // Activity Member Assignment
            Route::get('/{activity}/members', [ActivityManagementController::class, 'getAssignedMembers'])->name('members.index');
            Route::post('/{activity}/members', [ActivityManagementController::class, 'assignMember'])->name('members.store');
            Route::put('/{activity}/members/{employee}', [ActivityManagementController::class, 'updateAssignedMember'])->name('members.update');
            Route::delete('/{activity}/members/{employee}', [ActivityManagementController::class, 'unassignMember'])->name('members.destroy');
        });

        // Stage Management
        Route::prefix('stages')->name('stages.')->group(function () {
            Route::post('/', [StageManagementController::class, 'store'])->name('store');
            Route::get('/{stage}', [StageManagementController::class, 'show'])->name('show');
            Route::put('/{stage}', [StageManagementController::class, 'update'])->name('update');
            Route::delete('/{stage}', [StageManagementController::class, 'destroy'])->name('destroy');
            Route::post('/{stage}/reorder', [StageManagementController::class, 'reorder'])->name('reorder');
        });

        // Data endpoints
        Route::prefix('data')->name('data.')->group(function () {
            Route::get('/table', [ProjectDataController::class, 'getTableData'])->name('table');
            Route::get('/gantt', [ProjectDataController::class, 'getGanttData'])->name('gantt');
            Route::get('/scurve', [ProjectDataController::class, 'getSCurveData'])->name('scurve');
        });

        // Export routes
        Route::prefix('export')->name('export.')->group(function () {
            Route::get('/planning-pdf', [ProjectPlanningExportController::class, 'exportPlanningPDF'])->name('planning-pdf');
            Route::get('/table-pdf', [ProjectPlanningExportController::class, 'exportPlanningPDF'])->name('table-pdf');
            Route::get('/gantt-pdf', [ProjectPlanningExportController::class, 'exportGanttPDF'])->name('gantt-pdf');
            Route::get('/scurve-pdf', [ProjectPlanningExportController::class, 'exportSCurvePDF'])->name('scurve-pdf');

            // Excel export routes
            Route::get('/table-excel', [ProjectPlanningExportController::class, 'exportTableExcel'])->name('table-excel');
            Route::get('/gantt-excel', [ProjectPlanningExportController::class, 'exportGanttExcel'])->name('gantt-excel');
            Route::get('/scurve-excel', [ProjectPlanningExportController::class, 'exportSCurveExcel'])->name('scurve-excel');
        });
    });

    // BACKWARD COMPATIBILITY
    Route::redirect('/projects/{project}/planning', '/planning/{project}')->name('projects-planning.show');
});

// ==================== ROOT REDIRECT ====================

Route::get('/', function () {
    if (session()->has('auth_token')) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// ==================== DELIVERY PLANNING ROUTES ====================
// Include delivery planning module routes
require __DIR__ . '/delivery.php';