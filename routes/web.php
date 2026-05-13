<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\DeliveryProjectIssueController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StagingTicketController;
use App\Http\Controllers\DeliveryProjectController;
use App\Http\Controllers\DeliveryProjectUpdateController;
use App\Http\Controllers\DeliveryProjectPlanController;
use App\Http\Controllers\DeliveryProjectPlanningController;
use App\Http\Controllers\DeliveryProjectGanttController;
use App\Http\Controllers\DeliveryDynamicPhaseController;
use App\Http\Controllers\ActivityStageController;
use App\Http\Controllers\ActivityManagementController;
use App\Http\Controllers\DeliveryProjectDataController;
use App\Http\Controllers\DeliveryProjectStageManagementController;
use App\Http\Controllers\DeliveryProjectPlanningExportController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TicketViewController;
use App\Http\Controllers\ConsultantWorkloadController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\PasswordSetupController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminSessionController;
use App\Http\Controllers\AdminJobController;
use App\Http\Controllers\AdminBackupController;
use App\Http\Middleware\CheckAuthToken;

// ==================== PUBLIC ROUTES ====================

Route::prefix('api/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

// Login page
Route::get('/auth/login', [AuthController::class, 'showLogin'])->name('login');

// ==================== PASSWORD SETUP & RESET (public — tidak perlu auth) ====================
// Halaman "Cek email Anda" — tampil setelah setup akun baru atau forgot password
Route::get('/verify-email', [PasswordSetupController::class, 'showCheckEmail'])->name('password-setup.check-email');
// Halaman form atur/reset password — dari link di email
Route::get('/change-password', [PasswordSetupController::class, 'showChangePassword'])->name('password-setup.change');
// Proses submit password baru
Route::post('/change-password', [PasswordSetupController::class, 'submitChangePassword'])->name('password-setup.submit');
// Forgot password — halaman form input email
Route::get('/forgot-password', [PasswordSetupController::class, 'showForgotPassword'])->name('password-setup.forgot');
// Forgot password — proses kirim email reset
Route::post('/forgot-password', [PasswordSetupController::class, 'submitForgotPassword'])->name('password-setup.forgot.submit');

// NOTE: /auth/login (POST) sudah didefinisikan di prefix group 'api/auth' di atas.
// Route standalone ini dihapus untuk menghindari duplikasi.

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

    // ==================== CALENDAR ====================
    Route::prefix('calendar')->name('calendar.')->group(function () {
        Route::get('/', [CalendarController::class, 'index'])->name('index');
        Route::get('/events', [CalendarController::class, 'events'])->name('events');
        Route::get('/timesheets', [CalendarController::class, 'timesheets'])->name('timesheets');
    });

    // ==================== REPORTING ====================
    Route::get('/reporting',                  [\App\Http\Controllers\ReportingController::class, 'index'])->name('reporting');
    Route::get('/reporting/export-excel',     [\App\Http\Controllers\ReportingController::class, 'exportExcel'])->name('reporting.export');
    Route::get('/reporting/md-recap',         [\App\Http\Controllers\ReportingController::class, 'mdRecapIndex'])->name('reporting.md-recap');
    Route::get('/reporting/md-recap/export',  [\App\Http\Controllers\ReportingController::class, 'exportMdRecap'])->name('reporting.md-recap.export');

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
            Route::get('/grouping', [CustomerController::class, 'grouping'])->name('grouping');
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

    // Period Management (RPMO + Heads + Admin)
    Route::get('/rpmo/periods', [\App\Http\Controllers\PeriodManagementController::class, 'index'])
         ->name('rpmo.periods.index');

    // ==================== LEGAL ====================
    Route::get('/legal', function () {
        return view('legal.legal', ['user' => session('user')]);
    })->name('legal');

    // ==================== ADMIN ====================
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', function () {
            if ((int) session('user.role.id') !== 1) abort(403);
            return view('admin.index');
        })->name('index');
        Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log');
        Route::get('/sessions', [AdminSessionController::class, 'page'])->name('sessions');
        Route::get('/failed-jobs', [AdminJobController::class, 'page'])->name('failed-jobs');
        Route::get('/backup', [AdminBackupController::class, 'page'])->name('backup');
        Route::get('/backup/download/{filename}', [AdminBackupController::class, 'downloadBackup'])->name('backup.download');
        Route::get('/export/employees', [AdminBackupController::class, 'exportEmployees'])->name('export.employees');
        Route::get('/export/customers', [AdminBackupController::class, 'exportCustomers'])->name('export.customers');
        Route::get('/export/tickets',   [AdminBackupController::class, 'exportTickets'])->name('export.tickets');
        Route::get('/import/template/employees', [AdminBackupController::class, 'templateEmployees'])->name('import.template.employees');
        Route::get('/import/template/customers', [AdminBackupController::class, 'templateCustomers'])->name('import.template.customers');
    });

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
    Route::resource('projects', DeliveryProjectController::class)->except(['edit', 'update']);
    Route::patch('/projects/{project}/update-field', [DeliveryProjectController::class, 'updateField'])->name('projects.updateField');
    Route::patch('/projects/{project}/delivery-info', [DeliveryProjectController::class, 'updateDeliveryInfo'])->name('projects.updateDeliveryInfo');
    Route::patch('/projects/{project}/location-info', [DeliveryProjectController::class, 'updateLocationInfo'])->name('projects.updateLocationInfo');
    Route::post('/projects/{project}/generate-folder', [DeliveryProjectController::class, 'generateFolder'])->name('projects.generateFolder');
    Route::delete('/projects/{project}/folder', [DeliveryProjectController::class, 'deleteFolder'])->name('projects.deleteFolder');

    // Document management routes
    Route::post('/projects/{project}/documents', [DeliveryProjectController::class, 'storeDocument'])->name('project.documents.store');
    Route::patch('/project/documents/{document}', [DeliveryProjectController::class, 'updateDocument'])->name('project.documents.update');
    Route::delete('/project/documents/{document}', [DeliveryProjectController::class, 'destroyDocument'])->name('project.documents.destroy');

    // Team member management routes
    Route::get('/projects/{project}/team-members', [DeliveryProjectController::class, 'getTeamMembers'])->name('projects.team.index');
    Route::post('/projects/{project}/team-members', [DeliveryProjectController::class, 'storeTeamMember'])->name('projects.team.store');
    Route::put('/projects/{project}/team-members/{employee}', [DeliveryProjectController::class, 'updateTeamMember'])->name('projects.team.update');
    Route::delete('/projects/{project}/team-members/{employee}', [DeliveryProjectController::class, 'destroyTeamMember'])->name('projects.team.destroy');

    // Project updates/issues routes
    Route::post('/projects/{project}/updates', [DeliveryProjectUpdateController::class, 'store'])->name('project.updates.store');
    Route::patch('/project-updates/{project_update}', [DeliveryProjectUpdateController::class, 'update'])->name('project.updates.update');
    Route::delete('/project-updates/{project_update}', [DeliveryProjectUpdateController::class, 'destroy'])->name('project.updates.destroy');
    Route::get('/project-updates/{project_update}/edit', [DeliveryProjectUpdateController::class, 'edit'])->name('project.updates.edit');

    // API routes for regions/cities
    Route::get('/api/regions', [DeliveryProjectController::class, 'getRegions'])->name('api.regions');
    Route::get('/api/cities', [DeliveryProjectController::class, 'getCities'])->name('api.cities');

    // Issues routes
    Route::get('/issues', [DeliveryProjectIssueController::class, 'index'])->name('issues.index');
    Route::get('/issues/{project}', [DeliveryProjectIssueController::class, 'show'])->name('issues.show');

    // Profile routes
    Route::get('/staging-tickets', [StagingTicketController::class, 'view'])->name('staging.index');
    Route::get('/staging-tickets/rejected', [StagingTicketController::class, 'viewRejected'])->name('staging.rejected');
    Route::get('/staging-email-attachments/{stagingId}', [StagingTicketController::class, 'proxyEmailAttachment'])
        ->name('staging.email-attachment.proxy');
    Route::get('/my-profile', [ProfileController::class, 'myProfile'])->name('profile.my');
    Route::post('/my-profile/change-password', [ProfileController::class, 'changePassword'])->name('profile.change-password');

    // ==================== PROJECT PLANNING ROUTES ====================

    // Planning index - list all projects
    Route::get('/planning', [DeliveryProjectPlanningController::class, 'index'])->name('planning.index');

    // Backward compatibility redirect
    Route::redirect('/projects-planning', '/planning')->name('projects-planning.index');

    // Project-specific planning routes
    Route::prefix('planning/{project}')->name('planning.')->group(function () {

        // Main planning page
        Route::get('/', [DeliveryProjectPlanningController::class, 'show'])->name('show');
        Route::get('/gantt', [DeliveryProjectPlanningController::class, 'gantt'])->name('gantt');
        Route::get('/scurve', [DeliveryProjectPlanningController::class, 'scurve'])->name('scurve');
        Route::get('/phases-list', [DeliveryProjectPlanningController::class, 'getPhases'])->name('phases-list');

        // Phase Management
        Route::prefix('phases')->name('phases.')->group(function () {
            Route::get('/', [DeliveryDynamicPhaseController::class, 'index'])->name('index');
            Route::post('/create-custom', [DeliveryDynamicPhaseController::class, 'createCustomPhase'])->name('create');
            Route::post('/add', [DeliveryDynamicPhaseController::class, 'addPhase'])->name('add');
            Route::put('/{phase}', [DeliveryDynamicPhaseController::class, 'updatePhase'])->name('update');
            Route::delete('/{phase}', [DeliveryDynamicPhaseController::class, 'removePhase'])->name('remove');
            Route::post('/reorder', [DeliveryDynamicPhaseController::class, 'reorderPhases'])->name('reorder');
            Route::post('/{phase}/toggle', [DeliveryDynamicPhaseController::class, 'togglePhaseVisibility'])->name('toggle');
        });

        // View configuration
        Route::post('/view-config', [DeliveryDynamicPhaseController::class, 'updateViewConfig'])->name('view-config');

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
            Route::post('/', [DeliveryProjectStageManagementController::class, 'store'])->name('store');
            Route::get('/{stage}', [DeliveryProjectStageManagementController::class, 'show'])->name('show');
            Route::put('/{stage}', [DeliveryProjectStageManagementController::class, 'update'])->name('update');
            Route::delete('/{stage}', [DeliveryProjectStageManagementController::class, 'destroy'])->name('destroy');
            Route::post('/{stage}/reorder', [DeliveryProjectStageManagementController::class, 'reorder'])->name('reorder');
        });

        // Data endpoints
        Route::prefix('data')->name('data.')->group(function () {
            Route::get('/table', [DeliveryProjectDataController::class, 'getTableData'])->name('table');
            Route::get('/gantt', [DeliveryProjectDataController::class, 'getGanttData'])->name('gantt');
            Route::get('/scurve', [DeliveryProjectDataController::class, 'getSCurveData'])->name('scurve');
        });

        // Export routes
        Route::prefix('export')->name('export.')->group(function () {
            Route::get('/planning-pdf', [DeliveryProjectPlanningExportController::class, 'exportPlanningPDF'])->name('planning-pdf');
            Route::get('/table-pdf', [DeliveryProjectPlanningExportController::class, 'exportPlanningPDF'])->name('table-pdf');
            Route::get('/gantt-pdf', [DeliveryProjectPlanningExportController::class, 'exportGanttPDF'])->name('gantt-pdf');
            Route::get('/scurve-pdf', [DeliveryProjectPlanningExportController::class, 'exportSCurvePDF'])->name('scurve-pdf');

            // Excel export routes
            Route::get('/table-excel', [DeliveryProjectPlanningExportController::class, 'exportTableExcel'])->name('table-excel');
            Route::get('/gantt-excel', [DeliveryProjectPlanningExportController::class, 'exportGanttExcel'])->name('gantt-excel');
            Route::get('/scurve-excel', [DeliveryProjectPlanningExportController::class, 'exportSCurveExcel'])->name('scurve-excel');
        });
    });

    // BACKWARD COMPATIBILITY
    Route::redirect('/projects/{project}/planning', '/planning/{project}')->name('projects-planning.show');

    // ==================== TICKET ====================
    Route::prefix('ticket')->name('ticket.')->group(function () {
        Route::get('/', [TicketViewController::class, 'index'])->name('index');
        Route::get('/create', [TicketViewController::class, 'create'])->name('create');
        Route::get('/consultant-workload', [ConsultantWorkloadController::class, 'index'])->name('consultant-workload');
        Route::get('/task', [TaskController::class, 'index'])->name('task');
        Route::get('/latest-update', [TicketController::class, 'latestUpdate'])->name('latest-update');
        Route::post('/{id}/generate-folder', [TicketController::class, 'generateFolder'])->name('generate-folder');
        Route::delete('/{id}/folder', [TicketController::class, 'deleteFolder'])->name('delete-folder');
        Route::get('/{id}', [TicketViewController::class, 'show'])->name('show');
    });

    // ==================== NOTIFICATIONS ====================
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // ==================== ATTACHMENT PROXY ====================
    // Fetch file dari Microsoft Graph on-demand — tidak disimpan lokal
    Route::get('/attachments/{id}', [AttachmentController::class, 'show'])
        ->name('attachments.show')
        ->where('id', '[0-9]+');
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

// ==================== DELIVERY SUPPORT ROUTES ====================
// Include delivery support module routes
require __DIR__ . '/delivery-support.php';