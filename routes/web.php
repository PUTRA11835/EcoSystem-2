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
use App\Http\Controllers\DeliveryProjectCostController;
use App\Http\Controllers\DeliveryProjectUpdateController;
use App\Http\Controllers\DeliveryProjectPlanController;
use App\Http\Controllers\DeliveryProjectPlanningController;
use App\Http\Controllers\DeliveryProjectGanttController;
use App\Http\Controllers\DeliveryDynamicPhaseController;
use App\Http\Controllers\ActivityStageController;
use App\Http\Controllers\ActivityManagementController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\DeliveryProjectDataController;
use App\Http\Controllers\DeliveryProjectStageManagementController;
use App\Http\Controllers\DeliveryProjectPlanningExportController;
use App\Http\Controllers\DeliveryProjectPlanningImportController;
use App\Http\Controllers\DeliveryProjectRiskController;
use App\Http\Controllers\DeliveryProjectPaymentTermController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TicketViewController;
use App\Http\Controllers\ConsultantWorkloadController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\PasswordSetupController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\AdminSessionController;
use App\Http\Controllers\AdminJobController;
use App\Http\Controllers\AdminBackupController;
use App\Http\Controllers\AdminNotificationSoundController;
use App\Http\Controllers\TicketMigrationController;
use App\Http\Controllers\SlaController;
use App\Http\Middleware\CheckAuthToken;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\MenuController;

// ==================== ROOT REDIRECT ====================
Route::get('/', function () {
    return session('auth_token')
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

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
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('menu:dashboard');

    // ==================== CALENDAR ====================
    Route::prefix('calendar')->name('calendar.')->group(function () {
        Route::get('/', [CalendarController::class, 'index'])->name('index');
        Route::get('/events', [CalendarController::class, 'events'])->name('events')->middleware('menu:calendar.events');
        Route::get('/timesheets', [CalendarController::class, 'timesheets'])->name('timesheets')->middleware('menu:calendar.timesheets');
    });

    // ==================== REPORTING ====================
    Route::get('/reporting',                  [\App\Http\Controllers\ReportingController::class, 'index'])->name('reporting')->middleware('menu:reporting.validation');
    Route::get('/reporting/export-excel',     [\App\Http\Controllers\ReportingController::class, 'exportExcel'])->name('reporting.export');
    Route::get('/reporting/md-recap',         [\App\Http\Controllers\ReportingController::class, 'mdRecapIndex'])->name('reporting.md-recap')->middleware('menu:reporting.md-recap');
    Route::get('/reporting/md-recap/export',           [\App\Http\Controllers\ReportingController::class, 'exportMdRecap'])->name('reporting.md-recap.export');
    Route::get('/reporting/resolution-days/export',    [\App\Http\Controllers\ReportingController::class, 'exportResolutionDays'])->name('reporting.resolution-days.export');
    Route::get('/reporting/collection-outlook',        [\App\Http\Controllers\ReportingController::class, 'collectionOutlookIndex'])->name('reporting.collection-outlook')->middleware('menu:reporting.collection-outlook');
    Route::get('/reporting/collection-outlook/export', [\App\Http\Controllers\ReportingController::class, 'exportCollectionOutlook'])->name('reporting.collection-outlook.export')->middleware('menu:reporting.collection-outlook');
    Route::get('/reporting/collection-outlook-support',        [\App\Http\Controllers\ReportingController::class, 'collectionOutlookSupportIndex'])->name('reporting.collection-outlook-support')->middleware('menu:reporting.collection-outlook-support');
    Route::get('/reporting/collection-outlook-support/export', [\App\Http\Controllers\ReportingController::class, 'exportCollectionOutlookSupport'])->name('reporting.collection-outlook-support.export')->middleware('menu:reporting.collection-outlook-support');
    Route::get('/reporting/ticketing-overview',        [\App\Http\Controllers\ReportingController::class, 'ticketingOverviewIndex'])->name('reporting.ticketing-overview')->middleware('menu:reporting.ticketing-overview');
    Route::get('/reporting/ticket-by-module',           [\App\Http\Controllers\ReportingController::class, 'ticketByModuleIndex'])->name('reporting.ticket-by-module')->middleware('menu:reporting.ticket-by-module');
    Route::get('/reporting/log-shifting',               [\App\Http\Controllers\ReportingController::class, 'logShiftingIndex'])->name('reporting.log-shifting')->middleware('menu:reporting.log-shifting');
    Route::get('/reporting/ticket-by-module/export',    [\App\Http\Controllers\ReportingController::class, 'exportTicketByModule'])->name('reporting.ticket-by-module.export')->middleware('menu:reporting.ticket-by-module');
    Route::get('/reporting/resolution-days',             [\App\Http\Controllers\ReportingController::class, 'resolutionDaysIndex'])->name('reporting.resolution-days')->middleware('menu:reporting.resolution-days');

    // ==================== MASTER ====================
    Route::prefix('master')->name('master.')->group(function () {
        // Employee routes
        Route::prefix('employee')->name('employee.')->group(function () {
            Route::get('/', [EmployeeController::class, 'index'])->name('index')->middleware('menu:master.employee');
            Route::get('/export', [EmployeeController::class, 'exportToExcel'])->name('export');
            Route::get('/{id}', [EmployeeController::class, 'show'])->name('detail');
        });
        
        // Customer routes
        Route::prefix('customer')->name('customer.')->group(function () {
            Route::get('/', [CustomerController::class, 'index'])->name('index')->middleware('menu:master.customer');
            Route::get('/grouping', [CustomerController::class, 'grouping'])->name('grouping');
            Route::get('/{id}', [CustomerController::class, 'show'])->name('detail');
        });
    });

    // ==================== FINANCIAL ====================
    Route::get('/financial', function () {
        return view('financial.financial', ['user' => session('user')]);
    })->name('financial')->middleware('menu:financial');

    // ==================== HR & GENERAL ====================
    Route::get('/general', function () {
        return view('general.general', ['user' => session('user')]);
    })->name('general')->middleware('menu:general');

    // ==================== BUSINESS ====================
    Route::get('/business', function () {
        return view('business.business', ['user' => session('user')]);
    })->name('business')->middleware('menu:business');

    // ==================== DELIVERY SUPPORT ====================
    Route::prefix('delivery')->name('delivery.')->group(function () {
        // Support/Ticket Management
        Route::get('/support', function () {
            $user = session('user');
            return view('delivery.support.index', ['user' => $user]);
        })->name('support.index')->middleware('menu:delivery.support');
    });

    // ==================== RPMO ====================
    Route::get('/rpmo', function () {
        return view('rpmo.rpmo', ['user' => session('user')]);
    })->name('rpmo')->middleware('menu:rpmo.overview');

    // Period Management (RPMO + Heads + Admin)
    Route::get('/rpmo/periods', [\App\Http\Controllers\PeriodManagementController::class, 'index'])
         ->name('rpmo.periods.index')->middleware('menu:rpmo.periods');

    // ==================== LEGAL ====================
    Route::get('/legal', function () {
        return view('legal.legal', ['user' => session('user')]);
    })->name('legal')->middleware('menu:legal');

    // ==================== ADMIN ====================
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', function () {
            if ((int) session('user.role.id') !== 1) abort(403);
            return view('admin.index');
        })->name('index')->middleware('menu:control-center.overview');
        Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log')->middleware('menu:control-center.activity-log');
        Route::get('/login-log', [LoginLogController::class, 'index'])->name('login-log')->middleware('menu:control-center.login-log');
        Route::get('/sessions', [AdminSessionController::class, 'page'])->name('sessions')->middleware('menu:control-center.sessions');
        Route::get('/failed-jobs', [AdminJobController::class, 'page'])->name('failed-jobs')->middleware('menu:control-center.failed-jobs');
        Route::get('/backup', [AdminBackupController::class, 'page'])->name('backup')->middleware('menu:control-center.backup');
        Route::get('/backup/download/{filename}', [AdminBackupController::class, 'downloadBackup'])->name('backup.download');
        Route::get('/export/employees', [AdminBackupController::class, 'exportEmployees'])->name('export.employees');
        Route::get('/export/customers', [AdminBackupController::class, 'exportCustomers'])->name('export.customers');
        Route::get('/export/tickets',   [AdminBackupController::class, 'exportTickets'])->name('export.tickets');
        Route::get('/import/template/employees', [AdminBackupController::class, 'templateEmployees'])->name('import.template.employees');
        Route::get('/import/template/customers', [AdminBackupController::class, 'templateCustomers'])->name('import.template.customers');
        Route::get('/import/template/tickets',   [AdminBackupController::class, 'templateTickets'])->name('import.template.tickets');
        Route::get('/import/template/resolution-days',    [AdminBackupController::class, 'templateResolutionDays'])->name('import.template.resolution-days');
        Route::get('/import/template/timesheet',           [AdminBackupController::class, 'templateTimesheet'])->name('import.template.timesheet');
        Route::get('/import/template/customer-contacts',   [AdminBackupController::class, 'templateCustomerContacts'])->name('import.template.customer-contacts');
        Route::get('/import/template/delivery-support',       [AdminBackupController::class, 'templateDeliverySupport'])->name('import.template.delivery-support');
        Route::get('/import/template/employee-qualification', [AdminBackupController::class, 'templateEmployeeQualification'])->name('import.template.employee-qualification');
        Route::post('/import/employees', [AdminBackupController::class, 'importEmployees'])->name('import.employees');
        Route::post('/import/customers', [AdminBackupController::class, 'importCustomers'])->name('import.customers');
        Route::post('/import/tickets',   [AdminBackupController::class, 'importTickets'])->name('import.tickets');
        Route::post('/import/resolution-days', [AdminBackupController::class, 'importResolutionDays'])->name('import.resolution-days');
        Route::post('/import/timesheet',       [AdminBackupController::class, 'importTimesheet'])->name('import.timesheet');
        Route::get('/export/tickets/zip', [TicketMigrationController::class, 'exportZip'])->name('export.tickets.zip');
        Route::get('/sounds', [AdminNotificationSoundController::class, 'index'])->name('sounds')->middleware('menu:control-center.sounds');
        Route::post('/sounds', [AdminNotificationSoundController::class, 'store'])->name('sounds.store');
        Route::delete('/sounds/{id}', [AdminNotificationSoundController::class, 'destroy'])->name('sounds.destroy');
    });

    // ==================== SLA ====================
    Route::get('/sla/config', [SlaController::class, 'configPage'])->name('sla.config')->middleware('menu:sla.config');
    Route::get('/sla/report', [SlaController::class, 'reportPage'])->name('sla.report')->middleware('menu:sla.report');
    Route::get('/admin/sla/tickets/{id}/pdf',     [SlaController::class, 'downloadTicketPdf'])->name('sla.ticket.pdf');
    Route::get('/admin/sla/tickets/{id}/log-pdf', [SlaController::class, 'downloadLogPdf'])->name('sla.ticket.log-pdf');

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
    // CATATAN: keyed-array middleware pada Route::resource() TIDAK per-method —
    // Laravel menerapkan semua nilainya ke setiap route resource. Jadi begitu
    // tiap aksi butuh izin berbeda, route-nya harus didaftarkan eksplisit.
    // `projects/create` wajib didaftarkan sebelum `projects/{project}` agar
    // "create" tidak tertangkap sebagai parameter {project}.
    Route::get('/projects/create', [DeliveryProjectController::class, 'create'])->name('projects.create')->middleware('menu:delivery-project.add-new');
    Route::post('/projects', [DeliveryProjectController::class, 'store'])->name('projects.store')->middleware('menu:delivery-project.add-new');
    Route::delete('/projects/{project}', [DeliveryProjectController::class, 'destroy'])->name('projects.destroy')->middleware('menu:delivery-project.delete-project');
    Route::post('/projects/{project}/delete', [DeliveryProjectController::class, 'destroy'])->name('projects.destroy.post')->middleware('menu:delivery-project.delete-project');

    // Close / Reopen project (manual). Close mengunci project jadi read-only;
    // Reopen membukanya lagi. Keduanya butuh izin delivery-project.close-project.
    Route::post('/projects/{project}/close',  [DeliveryProjectController::class, 'close'])->name('projects.close')->middleware('menu:delivery-project.close-project');
    Route::post('/projects/{project}/reopen', [DeliveryProjectController::class, 'reopen'])->name('projects.reopen')->middleware('menu:delivery-project.close-project');

    Route::get('/projects', [DeliveryProjectController::class, 'index'])->name('projects.index')->middleware('menu:delivery.project');
    Route::get('/projects/{project}', [DeliveryProjectController::class, 'show'])->name('projects.show')->middleware('menu:delivery.project');

    // Write endpoints per section — dipetakan ke function menu section-nya masing-masing
    // supaya sebuah role bisa dibatasi hanya boleh mengedit section tertentu.
    Route::middleware(['menu:delivery-project.edit-general-info', 'project.editable'])->group(function () {
        Route::patch('/projects/{project}/general-info', [DeliveryProjectController::class, 'updateGeneralInfo'])->name('projects.updateGeneralInfo');
        Route::patch('/projects/{project}/update-field', [DeliveryProjectController::class, 'updateField'])->name('projects.updateField');
    });

    Route::middleware(['menu:delivery-project.edit-location-info', 'project.editable'])->group(function () {
        Route::patch('/projects/{project}/location-info', [DeliveryProjectController::class, 'updateLocationInfo'])->name('projects.updateLocationInfo');
    });

    Route::middleware(['menu:delivery-project.manage-documents', 'project.editable'])->group(function () {
        Route::post('/projects/{project}/generate-folder', [DeliveryProjectController::class, 'generateFolder'])->name('projects.generateFolder');
        Route::delete('/projects/{project}/folder', [DeliveryProjectController::class, 'deleteFolder'])->name('projects.deleteFolder');
        Route::post('/projects/{project}/folder/delete', [DeliveryProjectController::class, 'deleteFolder'])->name('projects.deleteFolder.post');
    });

    // Delivery Information — termasuk Term Of Payment (TOP) Plan yang tampil di
    // dalam section yang sama pada halaman project detail.
    Route::middleware(['menu:delivery-project.edit-delivery-info', 'project.editable'])->group(function () {
        Route::patch('/projects/{project}/delivery-info', [DeliveryProjectController::class, 'updateDeliveryInfo'])->name('projects.updateDeliveryInfo');
        Route::patch('/projects/{project}/delivery-data', [DeliveryProjectController::class, 'updateDeliveryData'])->name('projects.updateDeliveryData');
        Route::patch('/projects/{project}/financial-info', [DeliveryProjectController::class, 'updateFinancialInfo'])->name('projects.updateFinancialInfo');

        Route::post('/projects/{project}/payment-terms',        [DeliveryProjectPaymentTermController::class, 'store'])->name('projects.paymentTerms.store');
        Route::put('/projects/{project}/payment-terms/{term}',  [DeliveryProjectPaymentTermController::class, 'update'])->name('projects.paymentTerms.update');
        Route::delete('/projects/{project}/payment-terms/{term}',[DeliveryProjectPaymentTermController::class, 'destroy'])->name('projects.paymentTerms.destroy');
        Route::post('/projects/{project}/payment-terms/{term}/delete',[DeliveryProjectPaymentTermController::class, 'destroy'])->name('projects.paymentTerms.destroy.post');
    });

    // Term of Payment (TOP) Plan — read-only
    Route::get('/projects/{project}/payment-terms',         [DeliveryProjectPaymentTermController::class, 'index'])->name('projects.paymentTerms.index');

    // Risk Register routes
    Route::get('/projects/{project}/risks',          [DeliveryProjectRiskController::class, 'index'])->name('projects.risks.index');
    Route::middleware(['menu:delivery-project.manage-risk', 'project.editable'])->group(function () {
        Route::post('/projects/{project}/risks',         [DeliveryProjectRiskController::class, 'store'])->name('projects.risks.store');
        Route::put('/projects/{project}/risks/{risk}',   [DeliveryProjectRiskController::class, 'update'])->name('projects.risks.update');
        Route::delete('/projects/{project}/risks/{risk}',[DeliveryProjectRiskController::class, 'destroy'])->name('projects.risks.destroy');
        Route::post('/projects/{project}/risks/{risk}/delete',[DeliveryProjectRiskController::class, 'destroy'])->name('projects.risks.destroy.post');
    });

    // Plan Cost routes
    Route::get('/projects/{project}/costs',                                      [DeliveryProjectCostController::class, 'index'])->name('projects.costs.index');
    Route::get('/projects/{project}/costs/{cost}/items',                         [DeliveryProjectCostController::class, 'indexItems'])->name('projects.costs.items.index');
    Route::middleware(['menu:delivery-project.manage-plan-cost', 'project.editable'])->group(function () {
        Route::post('/projects/{project}/costs',                                     [DeliveryProjectCostController::class, 'store'])->name('projects.costs.store');
        Route::post('/projects/{project}/costs/init',                                [DeliveryProjectCostController::class, 'init'])->name('projects.costs.init');
        Route::put('/projects/{project}/costs/{cost}',                               [DeliveryProjectCostController::class, 'update'])->name('projects.costs.update');
        Route::delete('/projects/{project}/costs/{cost}',                            [DeliveryProjectCostController::class, 'destroy'])->name('projects.costs.destroy');
        Route::post('/projects/{project}/costs/{cost}/delete',                        [DeliveryProjectCostController::class, 'destroy'])->name('projects.costs.destroy.post');
        // Cost item (expense line-items) routes
        Route::post('/projects/{project}/costs/{cost}/items',                        [DeliveryProjectCostController::class, 'storeItem'])->name('projects.costs.items.store');
        Route::put('/projects/{project}/costs/{cost}/items/{item}',                  [DeliveryProjectCostController::class, 'updateItem'])->name('projects.costs.items.update');
        Route::delete('/projects/{project}/costs/{cost}/items/{item}',               [DeliveryProjectCostController::class, 'destroyItem'])->name('projects.costs.items.destroy');
        Route::post('/projects/{project}/costs/{cost}/items/{item}/delete',           [DeliveryProjectCostController::class, 'destroyItem'])->name('projects.costs.items.destroy.post');
    });

    // Document management routes
    Route::middleware(['menu:delivery-project.manage-documents', 'project.editable'])->group(function () {
        Route::post('/projects/{project}/documents', [DeliveryProjectController::class, 'storeDocument'])->name('project.documents.store');
        Route::post('/projects/{project}/documents/upload', [DeliveryProjectController::class, 'uploadDocument'])->name('project.documents.upload');
        Route::post('/projects/{project}/documents/create-upload-session', [DeliveryProjectController::class, 'createDocumentUploadSession'])->name('project.documents.create-upload-session');
        Route::post('/projects/{project}/documents/finalize-upload', [DeliveryProjectController::class, 'finalizeDocumentUpload'])->name('project.documents.finalize-upload');
        Route::patch('/project/documents/{document}', [DeliveryProjectController::class, 'updateDocument'])->name('project.documents.update');
        Route::delete('/project/documents/{document}', [DeliveryProjectController::class, 'destroyDocument'])->name('project.documents.destroy');
        Route::post('/project/documents/{document}/delete', [DeliveryProjectController::class, 'destroyDocument'])->name('project.documents.destroy.post');
    });

    // Team member management routes
    Route::get('/projects/{project}/team-members', [DeliveryProjectController::class, 'getTeamMembers'])->name('projects.team.index');
    Route::middleware(['menu:delivery-project.manage-team', 'project.editable'])->group(function () {
        Route::post('/projects/{project}/team-members', [DeliveryProjectController::class, 'storeTeamMember'])->name('projects.team.store');
        Route::put('/projects/{project}/team-members/{employee}', [DeliveryProjectController::class, 'updateTeamMember'])->name('projects.team.update');
        Route::delete('/projects/{project}/team-members/{employee}', [DeliveryProjectController::class, 'destroyTeamMember'])->name('projects.team.destroy');
        Route::post('/projects/{project}/team-members/{employee}/delete', [DeliveryProjectController::class, 'destroyTeamMember'])->name('projects.team.destroy.post');
    });

    // Project updates/issues routes
    Route::get('/project-updates/{project_update}/edit', [DeliveryProjectUpdateController::class, 'edit'])->name('project.updates.edit');
    Route::middleware(['menu:delivery-project.manage-issue-log', 'project.editable'])->group(function () {
        Route::post('/projects/{project}/updates', [DeliveryProjectUpdateController::class, 'store'])->name('project.updates.store');
        Route::patch('/project-updates/{project_update}', [DeliveryProjectUpdateController::class, 'update'])->name('project.updates.update');
        Route::delete('/project-updates/{project_update}', [DeliveryProjectUpdateController::class, 'destroy'])->name('project.updates.destroy');
        Route::post('/project-updates/{project_update}/delete', [DeliveryProjectUpdateController::class, 'destroy'])->name('project.updates.destroy.post');
    });

    // API routes for regions/cities
    Route::get('/api/regions', [DeliveryProjectController::class, 'getRegions'])->name('api.regions');
    Route::get('/api/cities', [DeliveryProjectController::class, 'getCities'])->name('api.cities');

    // Issues routes (global pages)
    Route::get('/issues', [DeliveryProjectIssueController::class, 'index'])->name('issues.index');
    Route::get('/issues/{project}', [DeliveryProjectIssueController::class, 'show'])->name('issues.show');

    // Project Issue Log routes (AJAX CRUD on the project detail page)
    Route::get('/projects/{project}/issues',           [DeliveryProjectIssueController::class, 'apiIndex'])->name('projects.issues.index');
    Route::middleware(['menu:delivery-project.manage-issue-log', 'project.editable'])->group(function () {
        Route::post('/projects/{project}/issues',          [DeliveryProjectIssueController::class, 'store'])->name('projects.issues.store');
        Route::put('/projects/{project}/issues/{issue}',   [DeliveryProjectIssueController::class, 'update'])->name('projects.issues.update');
        Route::delete('/projects/{project}/issues/{issue}',[DeliveryProjectIssueController::class, 'destroy'])->name('projects.issues.destroy');
        Route::post('/projects/{project}/issues/{issue}/delete',[DeliveryProjectIssueController::class, 'destroy'])->name('projects.issues.destroy.post');
    });

    // Profile routes
    Route::get('/staging-tickets', [StagingTicketController::class, 'view'])->name('staging.index')->middleware('menu:tickets.staging');
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
    // Indonesian holidays for date pickers (national + cuti bersama)
    Route::get('/api/holidays', [HolidayController::class, 'index'])->name('holidays.index');

    Route::prefix('planning/{project}')->middleware('project.editable')->name('planning.')->group(function () {

        // Main planning page
        Route::get('/', [DeliveryProjectPlanningController::class, 'show'])->name('show');
        Route::get('/gantt', [DeliveryProjectPlanningController::class, 'gantt'])->name('gantt');
        Route::get('/scurve', [DeliveryProjectPlanningController::class, 'scurve'])->name('scurve');
        Route::get('/phases-list', [DeliveryProjectPlanningController::class, 'getPhases'])->name('phases-list');

        // Phase Management — baca terbuka, tulis butuh izin Manage Planning
        Route::prefix('phases')->name('phases.')->group(function () {
            Route::get('/', [DeliveryDynamicPhaseController::class, 'index'])->name('index');

            Route::middleware('menu:delivery-project.manage-planning')->group(function () {
                Route::post('/create-custom', [DeliveryDynamicPhaseController::class, 'createCustomPhase'])->name('create');
                Route::post('/add', [DeliveryDynamicPhaseController::class, 'addPhase'])->name('add');
                Route::put('/{phase}', [DeliveryDynamicPhaseController::class, 'updatePhase'])->name('update');
                Route::delete('/{phase}', [DeliveryDynamicPhaseController::class, 'removePhase'])->name('remove');
                Route::post('/{phase}/delete', [DeliveryDynamicPhaseController::class, 'removePhase'])->name('remove.post');
                Route::post('/reorder', [DeliveryDynamicPhaseController::class, 'reorderPhases'])->name('reorder');
                Route::post('/{phase}/toggle', [DeliveryDynamicPhaseController::class, 'togglePhaseVisibility'])->name('toggle');
            });
        });

        // View configuration
        Route::post('/view-config', [DeliveryDynamicPhaseController::class, 'updateViewConfig'])->name('view-config')->middleware('menu:delivery-project.manage-planning');

        // Phase weight info
        Route::get('/phases/{phaseId}/weight-info', [ActivityManagementController::class, 'getPhaseWeightInfo'])->name('phases.weight-info');

        // Activity Management
        Route::prefix('activities')->name('activities.')->group(function () {
            Route::get('/{activity}', [ActivityManagementController::class, 'show'])->name('show');
            Route::get('/{activity}/members', [ActivityManagementController::class, 'getAssignedMembers'])->name('members.index');

            Route::middleware('menu:delivery-project.manage-planning')->group(function () {
                Route::post('/', [ActivityManagementController::class, 'store'])->name('store');
                Route::put('/{activity}', [ActivityManagementController::class, 'update'])->name('update');
                Route::delete('/{activity}', [ActivityManagementController::class, 'destroy'])->name('destroy');
                Route::post('/{activity}/delete', [ActivityManagementController::class, 'destroy'])->name('destroy.post');

                // Activity Member Assignment
                Route::post('/{activity}/members', [ActivityManagementController::class, 'assignMember'])->name('members.store');
                Route::put('/{activity}/members/{employee}', [ActivityManagementController::class, 'updateAssignedMember'])->name('members.update');
                Route::delete('/{activity}/members/{employee}', [ActivityManagementController::class, 'unassignMember'])->name('members.destroy');
                Route::post('/{activity}/members/{employee}/delete', [ActivityManagementController::class, 'unassignMember'])->name('members.destroy.post');
            });
        });

        // Stage Management
        Route::prefix('stages')->name('stages.')->group(function () {
            Route::get('/{stage}', [DeliveryProjectStageManagementController::class, 'show'])->name('show');

            Route::middleware('menu:delivery-project.manage-planning')->group(function () {
                Route::post('/', [DeliveryProjectStageManagementController::class, 'store'])->name('store');
                Route::put('/{stage}', [DeliveryProjectStageManagementController::class, 'update'])->name('update');
                Route::delete('/{stage}', [DeliveryProjectStageManagementController::class, 'destroy'])->name('destroy');
                Route::post('/{stage}/delete', [DeliveryProjectStageManagementController::class, 'destroy'])->name('destroy.post');
                Route::post('/{stage}/reorder', [DeliveryProjectStageManagementController::class, 'reorder'])->name('reorder');
            });
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

        // Import routes (bulk migration of the planning structure from CSV)
        Route::prefix('import')->name('import.')->group(function () {
            Route::get('/template', [DeliveryProjectPlanningImportController::class, 'template'])->name('template');
            Route::post('/', [DeliveryProjectPlanningImportController::class, 'import'])->name('store')->middleware('menu:delivery-project.manage-planning');
        });
    });

    // BACKWARD COMPATIBILITY
    Route::redirect('/projects/{project}/planning', '/planning/{project}')->name('projects-planning.show');

    // ==================== TICKET ====================
    Route::prefix('ticket')->name('ticket.')->group(function () {
        Route::get('/', [TicketViewController::class, 'index'])->name('index')->middleware('menu:tickets.inbox');
        Route::get('/create', [TicketViewController::class, 'create'])->name('create');
        Route::get('/export', [TicketController::class, 'exportToExcel'])->name('export');
        Route::get('/consultant-workload', [ConsultantWorkloadController::class, 'index'])->name('consultant-workload')->middleware('menu:ticket.consultant-workload');
        Route::get('/task', [TaskController::class, 'index'])->name('task')->middleware('menu:ticket.my-tasks');
        Route::get('/latest-update', [TicketController::class, 'latestUpdate'])->name('latest-update');
        Route::get('/{id}', [TicketViewController::class, 'show'])->name('show');
    });

    // ==================== NOTIFICATIONS ====================
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // ==================== MANAGEMENT ====================
    Route::prefix('management')->name('management.')->group(function () {
        Route::get('/roles', [RoleController::class, 'page'])
            ->middleware('menu:management.roles')
            ->name('roles.index');

        Route::get('/permissions', [MenuController::class, 'page'])
            ->middleware('menu:management.permissions')
            ->name('permissions.index');

        Route::get('/holidays', [\App\Http\Controllers\HolidayManagementController::class, 'page'])
            ->middleware('menu:management.holidays')
            ->name('holidays.index');

        Route::get('/hidden-tickets', [\App\Http\Controllers\HiddenTicketController::class, 'page'])
            ->middleware('menu:management.hidden-tickets')
            ->name('hidden-tickets.index');

        Route::prefix('employee')->name('employee.')->group(function () {
            Route::get('/basic-data',     [\App\Http\Controllers\ManagementEmployeeController::class, 'basicData'])    ->middleware('menu:management.employee.basic-data')    ->name('basic-data.index');
            Route::get('/address',        [\App\Http\Controllers\ManagementEmployeeController::class, 'address'])      ->middleware('menu:management.employee.address')        ->name('address.index');
            Route::get('/identification', [\App\Http\Controllers\ManagementEmployeeController::class, 'identification'])->middleware('menu:management.employee.identification') ->name('identification.index');
            Route::get('/family',         [\App\Http\Controllers\ManagementEmployeeController::class, 'family'])       ->middleware('menu:management.employee.family')         ->name('family.index');
            Route::get('/education',      [\App\Http\Controllers\ManagementEmployeeController::class, 'education'])    ->middleware('menu:management.employee.education')      ->name('education.index');
            Route::get('/qualification',  [\App\Http\Controllers\ManagementEmployeeController::class, 'qualification']) ->middleware('menu:management.employee.qualification')  ->name('qualification.index');
            Route::get('/contract',       [\App\Http\Controllers\ManagementEmployeeController::class, 'contract'])     ->middleware('menu:management.employee.contract')       ->name('contract.index');
            Route::get('/bank',           [\App\Http\Controllers\ManagementEmployeeController::class, 'bank'])         ->middleware('menu:management.employee.bank')           ->name('bank.index');
            Route::get('/payment',        [\App\Http\Controllers\ManagementEmployeeController::class, 'payment'])      ->middleware('menu:management.employee.payment')        ->name('payment.index');
            Route::get('/attachment',     [\App\Http\Controllers\ManagementEmployeeController::class, 'attachment'])   ->middleware('menu:management.employee.attachment')     ->name('attachment.index');
        });
    });

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