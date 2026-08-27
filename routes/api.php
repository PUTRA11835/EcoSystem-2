<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeBasicDataController;
use App\Http\Controllers\EmployeeAddressController;
use App\Http\Controllers\EmployeeIdentificationController;
use App\Http\Controllers\EmployeeFamilyController;
use App\Http\Controllers\EmployeeEducationController;
use App\Http\Controllers\EmployeeQualificationController;
use App\Http\Controllers\EmployeeContractController;
use App\Http\Controllers\EmployeeBankController;
use App\Http\Controllers\EmployeePaymentController;
use App\Http\Controllers\EmployeeAttachmentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerBasicDataController;
use App\Http\Controllers\CustomerAddressController;
use App\Http\Controllers\CustomerContactController;
use App\Http\Controllers\CustomerIdentificationController;
use App\Http\Controllers\CustomerBankController;
use App\Http\Controllers\CustomerAttachmentController;
use App\Http\Controllers\CustomerReportTemplateController;
use App\Http\Controllers\CustomerHistoryController;
use App\Http\Controllers\CustomerCredentialController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\TicketActivityLogController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\StagingTicketController;
use App\Http\Controllers\MandaysController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\AdminSessionController;
use App\Http\Controllers\AdminJobController;
use App\Http\Controllers\AdminBackupController;
use App\Http\Controllers\AdminNotificationSoundController;
use App\Http\Controllers\TicketMigrationController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ModuleGroupController;
use App\Http\Controllers\EmployeeModuleController;
use App\Http\Controllers\ModuleLeadController;

// ==================== HEALTH CHECK (public, no auth) ====================
Route::get('/health', function () {
    $checks = [];
    $status = 'ok';

    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Throwable $e) {
        $checks['database'] = 'error';
        $status = 'degraded';
    }

    try {
        $queueSize = \Illuminate\Support\Facades\DB::table('jobs')->count();
        $failedCount = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        $checks['queue_pending'] = $queueSize;
        $checks['queue_failed'] = $failedCount;
    } catch (\Throwable $e) {
        $checks['queue'] = 'unavailable';
    }

    return response()->json([
        'status'    => $status,
        'timestamp' => now()->toISOString(),
        'checks'    => $checks,
    ], $status === 'ok' ? 200 : 503);
})->name('health');

Route::middleware(['web'])->group(function () {

    // ==================== AUTH ROUTES (PUBLIC — no session required) ====================
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // ==================== PROTECTED ROUTES (requires valid session) ====================
    Route::middleware(['auth.session'])->group(function () {

    // ==================== EMPLOYEE ROUTES ====================

    // Main Employee endpoints
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'getData']);
        Route::post('/', [EmployeeController::class, 'store'])->middleware('menu:master.employee.create');
        Route::get('/roles', [EmployeeController::class, 'getRoles']);
        Route::get('/mentionable', [EmployeeController::class, 'getMentionable']);
        Route::get('/{id}', [EmployeeController::class, 'getDetail']);
        Route::get('/{id}/header', [EmployeeController::class, 'headerData']);

        // Aksi terhadap record employee-nya sendiri (bukan section) — hanya
        // dari halaman Master > Employee, tidak pernah dari My Profile.
        Route::put('/{id}', [EmployeeController::class, 'update'])->middleware('menu:master.employee.action');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->middleware('menu:master.employee.action');
        Route::post('/{id}/delete', [EmployeeController::class, 'destroy'])->middleware('menu:master.employee.action');
        Route::patch('/{id}/change-password', [EmployeeController::class, 'changePassword'])->middleware('menu:master.employee.action');
        Route::patch('/{id}/change-role', [EmployeeController::class, 'changeRole'])->middleware('menu:master.employee.action');
    });

    // Employee Basic Data endpoints
    Route::prefix('employees/{employeeId}')->group(function () {
        Route::get('/basic-data', [EmployeeBasicDataController::class, 'show']);
        Route::post('/basic-data', [EmployeeBasicDataController::class, 'store'])->middleware('employee.section:basic_data');
        Route::delete('/basic-data', [EmployeeBasicDataController::class, 'destroy'])->middleware('employee.section:basic_data');
        Route::post('/basic-data/delete', [EmployeeBasicDataController::class, 'destroy'])->middleware('employee.section:basic_data');
    });

    // Employee Address endpoints
    Route::middleware(['auth.session'])->group(function () {
        Route::get('/employees/{employeeId}/addresses', [EmployeeAddressController::class, 'index']);
        Route::get('/employees/{employeeId}/addresses/{addressId}', [EmployeeAddressController::class, 'show']);
        Route::post('/employees/{employeeId}/addresses', [EmployeeAddressController::class, 'store'])->middleware('employee.section:address');
        Route::put('/employees/{employeeId}/addresses/{addressId}', [EmployeeAddressController::class, 'update'])->middleware('employee.section:address');
        Route::delete('/employees/{employeeId}/addresses/{addressId}', [EmployeeAddressController::class, 'destroy'])->middleware('employee.section:address');
        Route::post('/employees/{employeeId}/addresses/{addressId}/delete', [EmployeeAddressController::class, 'destroy'])->middleware('employee.section:address');
        Route::patch('/employees/{employeeId}/addresses/{addressId}/set-primary', [EmployeeAddressController::class, 'setPrimary'])->middleware('employee.section:address');

        // Referensi wilayah Indonesia — dropdown alamat cascading
        // (Region → City → District → Rural/Urban Village).
        Route::get('/regions/children', [\App\Http\Controllers\RegionController::class, 'children']);
    });

    // Employee Identification endpoints
    Route::prefix('employees/{employeeId}/identifications')->group(function () {
        Route::get('/', [EmployeeIdentificationController::class, 'index']);
        Route::post('/', [EmployeeIdentificationController::class, 'store'])->middleware('employee.section:identification');
        Route::get('/{identificationId}', [EmployeeIdentificationController::class, 'show']);
        Route::put('/{identificationId}', [EmployeeIdentificationController::class, 'update'])->middleware('employee.section:identification');
        Route::delete('/{identificationId}', [EmployeeIdentificationController::class, 'destroy'])->middleware('employee.section:identification');
        Route::post('/{identificationId}/delete', [EmployeeIdentificationController::class, 'destroy'])->middleware('employee.section:identification');
    });

    // Employee Family endpoints
    Route::prefix('employees/{employeeId}')->group(function () {
        Route::get('/family', [EmployeeFamilyController::class, 'index']);
        Route::get('/family/statistics', [EmployeeFamilyController::class, 'statistics']);
        Route::get('/family/{familyId}', [EmployeeFamilyController::class, 'show']);
        Route::post('/family', [EmployeeFamilyController::class, 'store'])->middleware('employee.section:family');
        Route::put('/family/{familyId}', [EmployeeFamilyController::class, 'update'])->middleware('employee.section:family');
        Route::delete('/family/{familyId}', [EmployeeFamilyController::class, 'destroy'])->middleware('employee.section:family');
        Route::post('/family/{familyId}/delete', [EmployeeFamilyController::class, 'destroy'])->middleware('employee.section:family');
    });

    // Employee Education endpoints
    Route::prefix('employees/{employeeId}/education')->group(function () {
        Route::get('/', [EmployeeEducationController::class, 'index']);
        Route::get('/{educationId}', [EmployeeEducationController::class, 'show']);
        Route::post('/', [EmployeeEducationController::class, 'store'])->middleware('employee.section:education');
        Route::put('/{educationId}', [EmployeeEducationController::class, 'update'])->middleware('employee.section:education');
        Route::delete('/{educationId}', [EmployeeEducationController::class, 'destroy'])->middleware('employee.section:education');
        Route::post('/{educationId}/delete', [EmployeeEducationController::class, 'destroy'])->middleware('employee.section:education');
    });

    // Employee Qualification endpoints
    Route::prefix('employees/{employeeId}/qualification')->group(function () {
        Route::get('/', [EmployeeQualificationController::class, 'index']);
        Route::get('/{qualificationId}', [EmployeeQualificationController::class, 'show']);
        Route::post('/', [EmployeeQualificationController::class, 'store'])->middleware('employee.section:qualification');
        Route::put('/{qualificationId}', [EmployeeQualificationController::class, 'update'])->middleware('employee.section:qualification');
        Route::delete('/{qualificationId}', [EmployeeQualificationController::class, 'destroy'])->middleware('employee.section:qualification');
        Route::post('/{qualificationId}/delete', [EmployeeQualificationController::class, 'destroy'])->middleware('employee.section:qualification');
    });

    // Employee Contract endpoints
    Route::prefix('employees/{employeeId}/contract')->group(function () {
        Route::get('/', [EmployeeContractController::class, 'index']);
        Route::get('/{contractId}', [EmployeeContractController::class, 'show']);
        Route::post('/', [EmployeeContractController::class, 'store'])->middleware('employee.section:contract');
        Route::put('/{contractId}', [EmployeeContractController::class, 'update'])->middleware('employee.section:contract');
        Route::delete('/{contractId}', [EmployeeContractController::class, 'destroy'])->middleware('employee.section:contract');
        Route::post('/{contractId}/delete', [EmployeeContractController::class, 'destroy'])->middleware('employee.section:contract');
    });

    // Employee Bank endpoints
    Route::prefix('employees/{employeeId}/bank')->group(function () {
        Route::get('/', [EmployeeBankController::class, 'index']);
        Route::get('/{bankId}', [EmployeeBankController::class, 'show']);
        Route::post('/', [EmployeeBankController::class, 'store'])->middleware('employee.section:bank');
        Route::put('/{bankId}', [EmployeeBankController::class, 'update'])->middleware('employee.section:bank');
        Route::delete('/{bankId}', [EmployeeBankController::class, 'destroy'])->middleware('employee.section:bank');
        Route::post('/{bankId}/delete', [EmployeeBankController::class, 'destroy'])->middleware('employee.section:bank');
    });

    // Employee Payment endpoints
    Route::prefix('employees/{employeeId}/payment')->group(function () {
        Route::get('/', [EmployeePaymentController::class, 'index']);
        Route::get('/{paymentId}', [EmployeePaymentController::class, 'show']);
        Route::post('/', [EmployeePaymentController::class, 'store'])->middleware('employee.section:payment');
        Route::put('/{paymentId}', [EmployeePaymentController::class, 'update'])->middleware('employee.section:payment');
        Route::delete('/{paymentId}', [EmployeePaymentController::class, 'destroy'])->middleware('employee.section:payment');
        Route::post('/{paymentId}/delete', [EmployeePaymentController::class, 'destroy'])->middleware('employee.section:payment');
    });

    // Employee Attachment endpoints
    Route::prefix('employees/{employeeId}/attachments')->group(function () {
        Route::get('/', [EmployeeAttachmentController::class, 'index']);
        Route::post('/', [EmployeeAttachmentController::class, 'store'])->middleware('employee.section:attachment');
        Route::delete('/{attachmentId}', [EmployeeAttachmentController::class, 'destroy'])->middleware('employee.section:attachment');
        Route::post('/{attachmentId}/delete', [EmployeeAttachmentController::class, 'destroy'])->middleware('employee.section:attachment');
    });

    // Employee Module endpoints
    Route::prefix('employees/{employeeId}/modules')->group(function () {
        Route::get('/', [EmployeeModuleController::class, 'index']);
        // Penugasan module ke employee — murni master data, tidak pernah
        // dipanggil dari My Profile, jadi cukup master.employee.action.
        Route::post('/sync', [EmployeeModuleController::class, 'sync'])->middleware('menu:master.employee.action');
        Route::post('/attach', [EmployeeModuleController::class, 'attach'])->middleware('menu:master.employee.action');
        Route::delete('/{moduleId}', [EmployeeModuleController::class, 'detach'])->middleware('menu:master.employee.action');
        Route::post('/{moduleId}/delete', [EmployeeModuleController::class, 'detach'])->middleware('menu:master.employee.action');
    });

    // Module master data endpoints
    Route::prefix('modules')->group(function () {
        Route::get('/', [ModuleController::class, 'index']);
        Route::post('/', [ModuleController::class, 'store']);
        Route::get('/{id}', [ModuleController::class, 'show']);
        Route::get('/{id}/members', [ModuleController::class, 'members']);
        Route::put('/{id}', [ModuleController::class, 'update']);
        Route::delete('/{id}', [ModuleController::class, 'destroy']);
        Route::post('/{id}/delete', [ModuleController::class, 'destroy']);
    });

    // Module Group master data endpoints — pengelompokan module (mis. "Logistik" berisi ABAP, FI, dll).
    Route::prefix('module-groups')->group(function () {
        Route::get('/', [ModuleGroupController::class, 'index']);
        Route::post('/', [ModuleGroupController::class, 'store']);
        Route::get('/{id}', [ModuleGroupController::class, 'show']);
        Route::put('/{id}', [ModuleGroupController::class, 'update']);
        Route::delete('/{id}', [ModuleGroupController::class, 'destroy']);
        Route::post('/{id}/delete', [ModuleGroupController::class, 'destroy']);
    });

    // Module Lead endpoints — siapa yang jadi lead untuk tiap module.
    // Gating disamakan dengan penugasan module ke employee (menu:master.employee.action).
    Route::get('/module-leads/search-employees', [ModuleLeadController::class, 'searchEmployees']);
    Route::prefix('modules/{moduleId}/leads')->group(function () {
        Route::get('/', [ModuleLeadController::class, 'index']);
        Route::post('/', [ModuleLeadController::class, 'store'])->middleware('menu:master.employee.action');
        Route::delete('/{employeeId}', [ModuleLeadController::class, 'destroy'])->middleware('menu:master.employee.action');
        Route::post('/{employeeId}/delete', [ModuleLeadController::class, 'destroy'])->middleware('menu:master.employee.action');
    });

// ==================== CUSTOMER ROUTES ====================

    // Main Customer endpoints
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'getData']);
        Route::post('/', [CustomerController::class, 'store'])->middleware('menu:master.customer.create');
        Route::get('/search', [CustomerController::class, 'search']);
        Route::get('/statistics', [CustomerController::class, 'statistics']);
        Route::get('/top-level', [CustomerController::class, 'topLevel']);
        Route::get('/sales-employees', [CustomerController::class, 'salesEmployees']);
        Route::get('/grouping-data', [CustomerController::class, 'getGroupingData']);
        Route::get('/{id}', [CustomerController::class, 'show']);
        Route::get('/{id}/header', [CustomerController::class, 'headerData']);
        Route::get('/{id}/end-customers', [CustomerController::class, 'endCustomers']);
        // Aksi terhadap record customer-nya sendiri — menu 'Actions (Edit/Delete)'
        // di bawah Master > Business Partner. Data section-nya dijaga terpisah
        // lewat middleware customer.section (lihat blok-blok di bawah).
        Route::put('/{id}', [CustomerController::class, 'update'])->middleware('menu:master.customer.action');
        Route::delete('/{id}', [CustomerController::class, 'destroy'])->middleware('menu:master.customer.action');
        Route::post('/{id}/delete', [CustomerController::class, 'destroy'])->middleware('menu:master.customer.action');
        Route::post('/{id}/soft-delete', [CustomerController::class, 'softDelete'])->middleware('menu:master.customer.action');
        Route::post('/{id}/restore', [CustomerController::class, 'restore'])->middleware('menu:master.customer.action');
    });

    // Customer Group endpoints (struktural grouping)
    Route::prefix('customer-groups')->group(function () {
        Route::get('/', [CustomerGroupController::class, 'index']);
        Route::post('/', [CustomerGroupController::class, 'store'])->middleware('menu:master.customer.create');
        Route::get('/grouping-data', [CustomerGroupController::class, 'groupingData']);
        Route::get('/available-customers', [CustomerGroupController::class, 'availableCustomers']);
        Route::put('/{id}', [CustomerGroupController::class, 'update'])->middleware('menu:master.customer.action');
        Route::delete('/{id}', [CustomerGroupController::class, 'destroy'])->middleware('menu:master.customer.action');
        Route::post('/{id}/delete', [CustomerGroupController::class, 'destroy'])->middleware('menu:master.customer.action');
        Route::post('/{id}/members', [CustomerGroupController::class, 'addMember'])->middleware('menu:master.customer.action');
        Route::delete('/{id}/members/{customerId}', [CustomerGroupController::class, 'removeMember'])->middleware('menu:master.customer.action');
        Route::post('/{id}/members/{customerId}/delete', [CustomerGroupController::class, 'removeMember'])->middleware('menu:master.customer.action');
    });

    // Customer Basic Data endpoints
    Route::prefix('customers/{customerId}/basic-data')->group(function () {
        Route::get('/', [CustomerBasicDataController::class, 'show']);
        Route::post('/', [CustomerBasicDataController::class, 'store'])->middleware('customer.section:basic_data');
        Route::delete('/', [CustomerBasicDataController::class, 'destroy'])->middleware('customer.section:basic_data');
        Route::post('/delete', [CustomerBasicDataController::class, 'destroy'])->middleware('customer.section:basic_data');
    });

    // Customer Address endpoints
    Route::prefix('customers/{customerId}/addresses')->group(function () {
        Route::get('/', [CustomerAddressController::class, 'index']);
        Route::get('/{addressId}', [CustomerAddressController::class, 'show']);
        Route::post('/', [CustomerAddressController::class, 'store'])->middleware('customer.section:address');
        Route::put('/{addressId}', [CustomerAddressController::class, 'update'])->middleware('customer.section:address');
        Route::delete('/{addressId}', [CustomerAddressController::class, 'destroy'])->middleware('customer.section:address');
        Route::post('/{addressId}/delete', [CustomerAddressController::class, 'destroy'])->middleware('customer.section:address');
    });

    // Customer Contact endpoints
    Route::prefix('customers/{customerId}/contacts')->group(function () {
        Route::get('/', [CustomerContactController::class, 'index']);
        Route::get('/{contactId}', [CustomerContactController::class, 'show']);
        Route::post('/', [CustomerContactController::class, 'store'])->middleware('customer.section:contact');
        Route::put('/{contactId}', [CustomerContactController::class, 'update'])->middleware('customer.section:contact');
        Route::delete('/{contactId}', [CustomerContactController::class, 'destroy'])->middleware('customer.section:contact');
        Route::post('/{contactId}/delete', [CustomerContactController::class, 'destroy'])->middleware('customer.section:contact');
        // Jarvies login management per contact person
        Route::post('/{contactId}/create-login', [CustomerContactController::class, 'createLogin'])->middleware('customer.section:contact');
        Route::delete('/{contactId}/revoke-login', [CustomerContactController::class, 'revokeLogin'])->middleware('customer.section:contact');
        Route::post('/{contactId}/revoke-login', [CustomerContactController::class, 'revokeLogin'])->middleware('customer.section:contact');
        Route::patch('/{contactId}/toggle-view-all', [CustomerContactController::class, 'toggleViewAllTickets'])->middleware('customer.section:contact');
    });

    // Customer Identification endpoints
    Route::prefix('customers/{customerId}/identifications')->group(function () {
        Route::get('/', [CustomerIdentificationController::class, 'index']);
        Route::get('/{identificationId}', [CustomerIdentificationController::class, 'show']);
        Route::post('/', [CustomerIdentificationController::class, 'store'])->middleware('customer.section:identification');
        Route::put('/{identificationId}', [CustomerIdentificationController::class, 'update'])->middleware('customer.section:identification');
        Route::delete('/{identificationId}', [CustomerIdentificationController::class, 'destroy'])->middleware('customer.section:identification');
        Route::post('/{identificationId}/delete', [CustomerIdentificationController::class, 'destroy'])->middleware('customer.section:identification');
    });

    // Customer Bank endpoints
    Route::prefix('customers/{customerId}/banks')->group(function () {
        Route::get('/', [CustomerBankController::class, 'index']);
        Route::get('/{bankId}', [CustomerBankController::class, 'show']);
        Route::post('/', [CustomerBankController::class, 'store'])->middleware('customer.section:bank');
        Route::put('/{bankId}', [CustomerBankController::class, 'update'])->middleware('customer.section:bank');
        Route::delete('/{bankId}', [CustomerBankController::class, 'destroy'])->middleware('customer.section:bank');
        Route::post('/{bankId}/delete', [CustomerBankController::class, 'destroy'])->middleware('customer.section:bank');
    });

    // Customer Attachment endpoints
    Route::prefix('customers/{customerId}/attachments')->group(function () {
        Route::get('/', [CustomerAttachmentController::class, 'index']);
        Route::get('/{attachmentId}', [CustomerAttachmentController::class, 'show']);
        Route::post('/', [CustomerAttachmentController::class, 'store'])->middleware('customer.section:attachment');
        Route::put('/{attachmentId}', [CustomerAttachmentController::class, 'update'])->middleware('customer.section:attachment');
        Route::delete('/{attachmentId}', [CustomerAttachmentController::class, 'destroy'])->middleware('customer.section:attachment');
        Route::post('/{attachmentId}/delete', [CustomerAttachmentController::class, 'destroy'])->middleware('customer.section:attachment');
        Route::get('/{attachmentId}/download', [CustomerAttachmentController::class, 'download']);
    });

    // Customer Report Templates endpoints (library template Word Report Generator)
    Route::prefix('customers/{customerId}/report-templates')->group(function () {
        Route::get('/', [CustomerReportTemplateController::class, 'index'])->middleware('customer.section:report_templates,view');
        Route::post('/', [CustomerReportTemplateController::class, 'store'])->middleware('customer.section:report_templates');
        Route::delete('/{reportTemplate}', [CustomerReportTemplateController::class, 'destroy'])->middleware('customer.section:report_templates');
        Route::get('/{reportTemplate}/download', [CustomerReportTemplateController::class, 'download'])->middleware('customer.section:report_templates,view');
    });

    // Customer Credential endpoints
    Route::get('customers/{customerId}/credential', [CustomerCredentialController::class, 'show']);
    Route::post('customers/{customerId}/credential', [CustomerCredentialController::class, 'store'])->middleware('customer.section:credential');

    // Customer History endpoints
    Route::prefix('customers/{customerId}/history')->group(function () {
        Route::get('/', [CustomerHistoryController::class, 'index']);
        Route::post('/', [CustomerHistoryController::class, 'store'])->middleware('customer.section:history');
        Route::get('/statistics', [CustomerHistoryController::class, 'statistics']);
        Route::delete('/cleanup', [CustomerHistoryController::class, 'cleanup'])->middleware('customer.section:history');
    });

    // ==================== TASK (MY PIC TICKETS) ====================
    Route::get('/task', [\App\Http\Controllers\TaskController::class, 'list']);

    // ==================== CONSULTANT WORKLOAD ROUTES ====================
    Route::prefix('consultant-workload')->group(function () {
        Route::get('/', [\App\Http\Controllers\ConsultantWorkloadController::class, 'list']);
        Route::get('/tickets/{ticketId}/consultant-progress', [\App\Http\Controllers\ConsultantWorkloadController::class, 'getConsultantProgress']);
        Route::patch('/tickets/{ticketId}/consultant-progress', [\App\Http\Controllers\ConsultantWorkloadController::class, 'updateConsultantProgress']);
        Route::get('/{id}', [\App\Http\Controllers\ConsultantWorkloadController::class, 'detail']);
    });

    // ==================== STAGING TICKET ROUTES ====================
    Route::prefix('staging-tickets')->group(function () {
        Route::get('/statistics', [StagingTicketController::class, 'statistics']);
        Route::get('/', [StagingTicketController::class, 'index']);
        Route::post('/', [StagingTicketController::class, 'store']);
        Route::get('/{id}', [StagingTicketController::class, 'show']);
        Route::get('/{id}/preview-body', [StagingTicketController::class, 'previewBody']);
        Route::get('/{id}/email-attachments', [StagingTicketController::class, 'emailAttachments']);
        Route::get('/{id}/attachment-download', [StagingTicketController::class, 'emailAttachmentDownload']);
        Route::post('/{id}/approve', [StagingTicketController::class, 'approve']);
        Route::post('/{id}/reject', [StagingTicketController::class, 'reject']);
        Route::post('/{id}/analyze', [StagingTicketController::class, 'analyze']);
    });

    // ==================== TICKET ROUTES ====================
    Route::prefix('tickets')->group(function () {
        // Static routes first
        Route::get('/', [TicketController::class, 'index']);
        Route::get('/my', [TicketController::class, 'myTickets']);
        Route::get('/my-for-timesheet', [TicketController::class, 'myTicketsForTimesheet']);
        Route::get('/unassigned', [TicketController::class, 'unassignedTickets']);
        Route::get('/hidden', [TicketController::class, 'hiddenIndex']);
        Route::get('/latest-update', [TicketController::class, 'latestUpdate']);
        Route::get('/filter-options', [TicketController::class, 'filterOptions']);
        Route::get('/statistics', [TicketController::class, 'statistics']);
        Route::get('/pending-confirmations', [TicketController::class, 'pendingConfirmations']);
        Route::get('/pending-member-changes', [TicketController::class, 'pendingMemberChanges']);
        Route::get('/available-ticket-leads', [TicketController::class, 'getAvailableTicketLeads']);
        Route::post('/', [TicketController::class, 'store']);
        Route::post('/helpdesk-create', [TicketController::class, 'storeFromHelpdesk']);

        // Routes with specific names
        Route::get('/status/{status}', [TicketController::class, 'getByStatus']);
        Route::post('/confirm-assignment/{confirmationId}', [TicketController::class, 'confirmAssignment']);
        Route::post('/member-change-requests/{changeRequestId}/{action}', [TicketController::class, 'processMemberChangeRequest']);

        // Routes with {id} parameter last
        Route::get('/{id}', [TicketController::class, 'show']);
        Route::get('/{id}/mandays-history', [TicketController::class, 'getMandaysHistory']);
        Route::patch('/{id}/hide', [TicketController::class, 'hide']);
        Route::patch('/{id}/unhide', [TicketController::class, 'unhide']);
        Route::post('/{id}/take', [TicketController::class, 'takeTicket']);
        Route::post('/{id}/assign-ticket-lead', [TicketController::class, 'assignTicketLead']);
        Route::patch('/{id}/pic', [TicketController::class, 'updatePic']);
        Route::put('/{id}', [TicketController::class, 'update']);
        // POST alias untuk server yang memblokir method PUT/DELETE (ikuti pola /members/.../remove)
        Route::post('/{id}/update', [TicketController::class, 'update']);
        Route::put('/{id}/update-status', [TicketController::class, 'updateTicketStatus']);
        Route::put('/{id}/update-mandays', [TicketController::class, 'updateManDays']);
        Route::post('/{id}/members', [TicketController::class, 'addMember']);
        Route::delete('/{id}/members/{employeeId}', [TicketController::class, 'removeMember']);
        Route::post('/{id}/members/{employeeId}/remove', [TicketController::class, 'removeMember']);
        Route::post('/{id}/update-members', [TicketController::class, 'updateMembers']);
        Route::post('/{id}/request-member-change', [TicketController::class, 'requestMemberChange']);
        Route::delete('/{id}/request-member-removal/{employeeId}', [TicketController::class, 'requestMemberRemoval']);
        Route::post('/{id}/request-member-removal/{employeeId}/delete', [TicketController::class, 'requestMemberRemoval']);
        Route::delete('/{id}', [TicketController::class, 'destroy']);
        Route::post('/{id}/delete', [TicketController::class, 'destroy']);

        // Ticket Messages
        Route::get('/{ticketId}/messages', [TicketMessageController::class, 'index']);
        Route::post('/{ticketId}/messages', [TicketMessageController::class, 'store']);
        Route::post('/{ticketId}/customer-reply', [TicketMessageController::class, 'customerReply']);
        Route::put('/{ticketId}/messages/mark-all-read', [TicketMessageController::class, 'markAllRead']);
        Route::post('/{ticketId}/initiate-email', [TicketMessageController::class, 'initiateEmail']);
        Route::patch('/{ticketId}/messages/{messageId}/sla-message', [TicketMessageController::class, 'updateSlaMessage']);
        Route::post('/{ticketId}/messages/{messageId}/internal-note', [TicketMessageController::class, 'updateInternalNote']);
        Route::delete('/{ticketId}/messages/{messageId}/internal-note', [TicketMessageController::class, 'destroyInternalNote']);
        Route::post('/{ticketId}/messages/{messageId}/internal-note/delete', [TicketMessageController::class, 'destroyInternalNote']);

        // ==================== DELIVERABLE ROUTES ====================
        Route::get('/{id}/deliverables', [\App\Http\Controllers\TicketDeliverableController::class, 'index']);
        Route::post('/{id}/deliverables', [\App\Http\Controllers\TicketDeliverableController::class, 'store']);
        Route::patch('/{id}/deliverables/{delivId}', [\App\Http\Controllers\TicketDeliverableController::class, 'update']);
        Route::patch('/{id}/deliverables/{delivId}/send', [\App\Http\Controllers\TicketDeliverableController::class, 'send']);
        Route::delete('/{id}/deliverables/{delivId}', [\App\Http\Controllers\TicketDeliverableController::class, 'destroy']);
        Route::post('/{id}/deliverables/{delivId}/delete', [\App\Http\Controllers\TicketDeliverableController::class, 'destroy']);

        // Assign ticket to delivery support
        Route::get('/{id}/available-supports', [TicketController::class, 'getAvailableSupports']);
        Route::post('/{id}/assign-to-support', [TicketController::class, 'assignToSupport']);
        Route::post('/{id}/create-delivery-support', [TicketController::class, 'createDeliverySupport']);

        // ==================== MANDAYS ROUTES ====================
        // Shared utility
        Route::get('/{ticketId}/mandays/modules',  [MandaysController::class, 'getModules']);
        Route::get('/{ticketId}/mandays/history',  [MandaysController::class, 'getCustomerMandaysHistory']);
        Route::get('/{ticketId}/mandays/approved',             [MandaysController::class, 'getApprovedMandays']);
        Route::get('/{ticketId}/consultant-mandays/approved', [MandaysController::class, 'getApprovedConsultantMandays']);
        Route::get('/{ticketId}/mandays/version/{mandaysId}', [MandaysController::class, 'getCustomerMandaysVersionDetail']);

        // Customer Mandays — PIC
        Route::get('/{ticketId}/mandays/pic-draft', [MandaysController::class, 'getCustomerDraft']);
        Route::post('/{ticketId}/mandays/pic-draft', [MandaysController::class, 'saveCustomerDraft']);
        Route::post('/{ticketId}/mandays/pic-draft/submit', [MandaysController::class, 'submitCustomerDraft']);
        Route::delete('/{ticketId}/mandays/pic-draft', [MandaysController::class, 'deleteCustomerDraft']);

        // Customer Mandays — Helpdesk
        Route::get('/{ticketId}/mandays/hd-draft', [MandaysController::class, 'getHelpdeskDraft']);
        Route::put('/{ticketId}/mandays/hd-draft', [MandaysController::class, 'saveHelpdeskDraft']);
        Route::post('/{ticketId}/mandays/hd-draft/submit-chat', [MandaysController::class, 'submitToChat']);
        Route::post('/{ticketId}/mandays/hd-draft/approve', [MandaysController::class, 'approveCustomerMandays']);
        Route::post('/{ticketId}/mandays/hd-draft/cancel', [MandaysController::class, 'cancelCustomerMandays']);

        // Resolution Days — PIC + Head of Support
        Route::get('/{ticketId}/mandays/resolution', [MandaysController::class, 'getResolutionProposal']);
        Route::post('/{ticketId}/mandays/resolution', [MandaysController::class, 'saveResolutionProposal']);
        Route::post('/{ticketId}/mandays/resolution/submit', [MandaysController::class, 'submitResolutionProposal']);
        Route::post('/{ticketId}/mandays/resolution/approve', [MandaysController::class, 'approveResolutionProposal']);
    });

    // ==================== DELIVERY SUPPORT API ROUTES ====================
    Route::prefix('delivery/support')->group(function () {
        Route::get('/search', [\App\Http\Controllers\Delivery\DeliverySupportController::class, 'search']);
    });

    // ==================== CALENDAR/EVENT ROUTES ====================
    Route::prefix('events')->group(function () {
        Route::get('/', [CalendarController::class, 'getEvents']);
        Route::get('/statistics', [CalendarController::class, 'statistics']);
        Route::get('/{id}', [CalendarController::class, 'show']);
        Route::post('/', [CalendarController::class, 'store']);
        Route::put('/{id}', [CalendarController::class, 'update']);
        Route::delete('/{id}', [CalendarController::class, 'destroy']);
    });

    // ==================== TICKET ACTIVITY LOG ROUTES ====================
    Route::prefix('tickets/{ticketId}/activity-logs')->group(function () {
        Route::get('/',              [TicketActivityLogController::class, 'index']);
        Route::post('/',             [TicketActivityLogController::class, 'store']);
        Route::post('/{logId}/update', [TicketActivityLogController::class, 'update']);
        Route::post('/{logId}/delete', [TicketActivityLogController::class, 'destroy']);
    });

    // ==================== TIMESHEET ROUTES ====================
    Route::prefix('timesheets')->group(function () {
        Route::get('/', [TimesheetController::class, 'index']);
        Route::get('/statistics', [TimesheetController::class, 'statistics']);
        Route::get('/submitted-for-approval', [TimesheetController::class, 'submittedForApproval']); // For heads to review
        Route::get('/my-projects', [TimesheetController::class, 'myProjects']);
        Route::get('/my-activities/all', [TimesheetController::class, 'allMyActivities']); // Get ALL assigned activities
        Route::get('/my-activities/{projectId}', [TimesheetController::class, 'myActivities']); // Get activities for specific project
        Route::get('/remaining-md', [TimesheetController::class, 'remainingMd']); // Remaining MD quota for a ticket
        Route::get('/my-late-exceptions', [TimesheetController::class, 'myLateExceptions']); // Approved late exception requests (not expired)
        Route::get('/valid-periods',      [TimesheetController::class, 'validPeriods']);      // Active window + late exceptions for the current user
        Route::post('/export', [TimesheetController::class, 'exportToExcel']);
        Route::get('/{id}', [TimesheetController::class, 'show']);
        Route::post('/', [TimesheetController::class, 'store']);
        Route::post('/{id}/update', [TimesheetController::class, 'update']);
        Route::post('/{id}/delete', [TimesheetController::class, 'destroy']);
        Route::post('/{id}/submit', [TimesheetController::class, 'submit']);
        Route::post('/{id}/approve', [TimesheetController::class, 'approve']);
        Route::post('/{id}/reject', [TimesheetController::class, 'reject']);
    });

    // ==================== REPORTING ROUTES ====================
    Route::prefix('reporting')->group(function () {
        Route::get('/timesheet-support', [\App\Http\Controllers\ReportingController::class, 'timesheetSupport']);
        Route::get('/current-period',    [\App\Http\Controllers\ReportingController::class, 'currentPeriod']);
        Route::post('/close-period',     [\App\Http\Controllers\ReportingController::class, 'closePeriod']);
        Route::get('/md-recap',          [\App\Http\Controllers\ReportingController::class, 'mdRecap']);
        Route::get('/collection-outlook', [\App\Http\Controllers\ReportingController::class, 'collectionOutlook']);
        // Ubah status penagihan TOP langsung dari matrix Collection Outlook.
        // Pakai POST (bukan PUT) — verb PUT/DELETE pernah diblok edge/WAF di production.
        Route::post('/collection-outlook/terms/{term}', [\App\Http\Controllers\ReportingController::class, 'collectionOutlookUpdateTerm'])
            ->middleware('menu:reporting.collection-outlook.edit');
        // Collection Outlook — Delivery Support (sumber TOP support)
        Route::get('/collection-outlook-support', [\App\Http\Controllers\ReportingController::class, 'collectionOutlookSupport']);
        Route::post('/collection-outlook-support/terms/{term}', [\App\Http\Controllers\ReportingController::class, 'collectionOutlookSupportUpdateTerm'])
            ->middleware('menu:reporting.collection-outlook-support.edit');
        Route::get('/ticketing-overview', [\App\Http\Controllers\ReportingController::class, 'ticketingOverview']);
        Route::get('/ticketing-overview/{customerId}', [\App\Http\Controllers\ReportingController::class, 'ticketingOverviewDetail']);
        Route::get('/ticket-by-module', [\App\Http\Controllers\ReportingController::class, 'ticketByModule']);
        // Diagram Report page (Grafik 1/2/3) — dummy charts on the page use hard-coded
        // sample data; only these three fetch live data.
        Route::get('/diagram-report/ticket-qty',           [\App\Http\Controllers\ReportingController::class, 'diagramTicketQty']);
        Route::get('/diagram-report/ticket-by-module',     [\App\Http\Controllers\ReportingController::class, 'diagramTicketByModule']);
        Route::get('/diagram-report/ticket-type-by-month', [\App\Http\Controllers\ReportingController::class, 'diagramTicketTypeByMonth']);
        Route::get('/diagram-report/ticket-by-module-type', [\App\Http\Controllers\ReportingController::class, 'diagramTicketByModuleType']);
        Route::get('/diagram-report/ticket-type-by-module-table', [\App\Http\Controllers\ReportingController::class, 'diagramTicketTypeByModuleTable']);
        Route::get('/diagram-report/ticket-by-module-current-period', [\App\Http\Controllers\ReportingController::class, 'diagramTicketByModuleCurrentPeriod']);
        Route::get('/diagram-report/ticket-by-type', [\App\Http\Controllers\ReportingController::class, 'diagramTicketByType']);
        Route::get('/diagram-report/ticket-by-cr-status', [\App\Http\Controllers\ReportingController::class, 'diagramTicketByCrStatus']);
        Route::get('/diagram-report/ticket-by-cr-per-month', [\App\Http\Controllers\ReportingController::class, 'diagramTicketByCrPerMonth']);
        Route::get('/diagram-report/ticket-closed-per-month', [\App\Http\Controllers\ReportingController::class, 'diagramTicketClosedPerMonth']);
        Route::get('/log-shifting', [\App\Http\Controllers\ReportingController::class, 'logShifting']);
        Route::get('/log-shifting/{ticketId}', [\App\Http\Controllers\ReportingController::class, 'logShiftingDetail']);
        Route::get('/resolution-days', [\App\Http\Controllers\ReportingController::class, 'resolutionDays']);
        // Consultant Assignment — daftar consultant yang tergabung di Delivery Project.
        // Izinnya diperiksa di controller lewat Employee::canAccessMenu().
        Route::get('/consultant-assignment', [\App\Http\Controllers\ReportingController::class, 'consultantAssignment']);
        Route::get('/consultant-assignment/filter-options', [\App\Http\Controllers\ReportingController::class, 'consultantAssignmentFilterOptions']);
    });

    // ==================== NOTIFICATION ROUTES ====================
    Route::prefix('notifications')->group(function () {
        Route::get('/',              [NotificationController::class, 'apiIndex']);
        Route::get('/unread-count',  [NotificationController::class, 'unreadCount']);
        Route::put('/read-all',      [NotificationController::class, 'markAllRead']);
        Route::put('/{id}/read',     [NotificationController::class, 'markRead']);
        Route::delete('/bulk-delete', [NotificationController::class, 'bulkDelete']);
        Route::post('/bulk-delete',  [NotificationController::class, 'bulkDelete']);
        Route::delete('/{id}',       [NotificationController::class, 'deleteOne']);
        Route::post('/{id}/delete',  [NotificationController::class, 'deleteOne']);
    });

    // ==================== WEB PUSH ROUTES ====================
    Route::prefix('push')->group(function () {
        Route::get('/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey']);
        Route::post('/subscribe',       [PushSubscriptionController::class, 'subscribe']);
        Route::delete('/unsubscribe',   [PushSubscriptionController::class, 'unsubscribe']);
        Route::post('/unsubscribe',     [PushSubscriptionController::class, 'unsubscribe']);
        Route::post('/test',            [PushSubscriptionController::class, 'test']);
    });

    // ==================== EMAIL ROUTES ====================
    Route::prefix('email')->group(function () {
        Route::get('/inbox', [EmailController::class, 'inbox']);
        Route::post('/process-inbox', [EmailController::class, 'processInbox']);
        Route::post('/process-sent',  [EmailController::class, 'processSentItems']);
        Route::post('/send', [EmailController::class, 'send']);
        Route::post('/reply', [EmailController::class, 'reply']);
        Route::post('/messages/{messageId}/reprocess-attachments', [EmailController::class, 'reprocessAttachments']);
    });

    // ==================== PERIOD MANAGEMENT ROUTES ====================
    Route::prefix('periods')->group(function () {
        Route::get('/active',  [\App\Http\Controllers\PeriodManagementController::class, 'activePeriod']);
        Route::get('/closed',  [\App\Http\Controllers\PeriodManagementController::class, 'closedPeriods']);
        Route::post('/',       [\App\Http\Controllers\PeriodManagementController::class, 'store']);

        // Late exception request flow (2-level approval)
        Route::get('/my-exception-requests',   [\App\Http\Controllers\PeriodManagementController::class, 'myExceptionRequests']);
        Route::post('/exception-requests',     [\App\Http\Controllers\PeriodManagementController::class, 'createExceptionRequest']);
        Route::get('/exception-requests',      [\App\Http\Controllers\PeriodManagementController::class, 'listExceptionRequests']);
        Route::patch('/exception-requests/{exRequest}/head-decide', [\App\Http\Controllers\PeriodManagementController::class, 'headDecideRequest']);
        Route::patch('/exception-requests/{exRequest}/rpmo-decide', [\App\Http\Controllers\PeriodManagementController::class, 'rpmoDecideRequest']);

        Route::prefix('/{period}')->group(function () {
            // RPMO: global lifecycle
            Route::post('/open-global',  [\App\Http\Controllers\PeriodManagementController::class, 'openGlobal']);
            Route::post('/close-global', [\App\Http\Controllers\PeriodManagementController::class, 'closeGlobal']);
            Route::post('/force-close',  [\App\Http\Controllers\PeriodManagementController::class, 'forceCloseDomain']);
            // RPMO / Admin: edit dates & delete
            Route::patch('/dates',       [\App\Http\Controllers\PeriodManagementController::class, 'updateDates']);
            Route::delete('/',           [\App\Http\Controllers\PeriodManagementController::class, 'destroy']);
            Route::post('/delete',       [\App\Http\Controllers\PeriodManagementController::class, 'destroy']);
            // Heads: domain lifecycle
            Route::post('/open-domain',  [\App\Http\Controllers\PeriodManagementController::class, 'openDomain']);
            Route::post('/close-domain', [\App\Http\Controllers\PeriodManagementController::class, 'closeDomain']);
            // Audit logs (Admin + Heads + RPMO)
            Route::get('/audit-logs',    [\App\Http\Controllers\PeriodManagementController::class, 'auditLogs']);
        });
    });

    // ==================== ADMIN ROUTES ====================
    Route::prefix('admin')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'getData']);
        Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'getData']);
        Route::get('/audit-logs/modules', [\App\Http\Controllers\AuditLogController::class, 'modules']);
        Route::get('/login-logs', [LoginLogController::class, 'getData']);

        // Session Management
        Route::get('/sessions', [AdminSessionController::class, 'index']);
        Route::delete('/sessions/{sessionId}', [AdminSessionController::class, 'destroy']);
        Route::post('/sessions/{sessionId}/delete', [AdminSessionController::class, 'destroy']);
        Route::delete('/sessions', [AdminSessionController::class, 'destroyAll']);
        Route::post('/sessions/delete-all', [AdminSessionController::class, 'destroyAll']);

        // DB Backup
        Route::get('/backup/list', [AdminBackupController::class, 'listBackups']);
        Route::post('/backup/create', [AdminBackupController::class, 'createBackup']);
        Route::delete('/backup/{filename}', [AdminBackupController::class, 'deleteBackup']);
        Route::post('/backup/{filename}/delete', [AdminBackupController::class, 'deleteBackup']);

        // Import
        Route::post('/import/employees', [AdminBackupController::class, 'importEmployees']);
        Route::post('/import/customers', [AdminBackupController::class, 'importCustomers']);
        Route::post('/import/tickets',         [AdminBackupController::class, 'importTickets']);
        Route::post('/import/ticket-members',  [AdminBackupController::class, 'importTicketMembers']);
        Route::post('/import/tickets/zip', [TicketMigrationController::class, 'importZip']);
        Route::post('/import/tickets/from-api', [TicketMigrationController::class, 'importFromApi']);
        Route::post('/import/resolution-days',    [AdminBackupController::class, 'importResolutionDays']);
        Route::post('/import/timesheet',           [AdminBackupController::class, 'importTimesheet']);
        Route::post('/import/customer-contacts',      [AdminBackupController::class, 'importCustomerContacts']);
        Route::post('/import/delivery-support',       [AdminBackupController::class, 'importDeliverySupport']);
        Route::post('/import/employee-qualification', [AdminBackupController::class, 'importEmployeeQualification']);

        // Failed Job Monitor
        Route::get('/failed-jobs', [AdminJobController::class, 'index']);
        Route::get('/failed-jobs/{uuid}', [AdminJobController::class, 'show']);
        Route::post('/failed-jobs/{uuid}/retry', [AdminJobController::class, 'retry']);
        Route::post('/failed-jobs/retry-all', [AdminJobController::class, 'retryAll']);
        Route::delete('/failed-jobs/{uuid}', [AdminJobController::class, 'destroy']);
        Route::post('/failed-jobs/{uuid}/delete', [AdminJobController::class, 'destroy']);
        Route::delete('/failed-jobs', [AdminJobController::class, 'clearAll']);
        Route::post('/failed-jobs/clear', [AdminJobController::class, 'clearAll']);
    });

    // ── SLA ────────────────────────────────────────────────────────────────
    Route::get('/tickets/{id}/sla',               [\App\Http\Controllers\SlaController::class, 'getTicketSla']);
    Route::post('/tickets/{id}/sla/meeting/start', [\App\Http\Controllers\SlaController::class, 'startMeeting']);
    Route::post('/tickets/{id}/sla/meeting/end',   [\App\Http\Controllers\SlaController::class, 'endMeeting']);

    // ── Meeting Templates (terikat per tiket) ───────────────────────────────────
    Route::get('/tickets/{id}/meeting-templates',            [\App\Http\Controllers\MeetingTemplateController::class, 'index']);
    Route::post('/tickets/{id}/meeting-templates',           [\App\Http\Controllers\MeetingTemplateController::class, 'store']);
    Route::put('/tickets/{id}/meeting-templates/{tplId}',    [\App\Http\Controllers\MeetingTemplateController::class, 'update']);
    Route::delete('/tickets/{id}/meeting-templates/{tplId}', [\App\Http\Controllers\MeetingTemplateController::class, 'destroy']);
    Route::post('/tickets/{id}/meeting-templates/{tplId}/delete', [\App\Http\Controllers\MeetingTemplateController::class, 'destroy']);

    // Admin-only SLA endpoints
    Route::get('/admin/sla/policies',          [\App\Http\Controllers\SlaController::class, 'getPolicies']);
    Route::post('/admin/sla/policies',         [\App\Http\Controllers\SlaController::class, 'storePolicy']);
    Route::put('/admin/sla/policies/{id}',     [\App\Http\Controllers\SlaController::class, 'updatePolicy']);
    Route::delete('/admin/sla/policies/{id}',  [\App\Http\Controllers\SlaController::class, 'destroyPolicy']);
    Route::post('/admin/sla/policies/{id}/delete', [\App\Http\Controllers\SlaController::class, 'destroyPolicy']);
    Route::get('/admin/sla/report',            [\App\Http\Controllers\SlaController::class, 'getReport']);

    // Notification sounds list (accessible to all authenticated users)
    Route::get('/notification-sounds', [AdminNotificationSoundController::class, 'list']);

    // ==================== ROLE & MENU MANAGEMENT ====================
    Route::get('/my-menus', [\App\Http\Controllers\MenuController::class, 'getMyMenus']);

    // Menu management
    Route::get('/menus',                                            [\App\Http\Controllers\MenuController::class, 'index']);
    Route::get('/menus/all',                                        [\App\Http\Controllers\MenuController::class, 'allWithPermissions']);
    Route::get('/menus/with-roles',                                 [\App\Http\Controllers\MenuController::class, 'withRoles']);
    Route::post('/menus',                                           [\App\Http\Controllers\MenuController::class, 'store']);
    Route::put('/menus/{menuId}',                                   [\App\Http\Controllers\MenuController::class, 'update']);
    Route::delete('/menus/{menuId}',                                [\App\Http\Controllers\MenuController::class, 'destroy']);
    Route::post('/menus/{menuId}/delete',                           [\App\Http\Controllers\MenuController::class, 'destroy']);
    Route::put('/menus/{menuId}/roles/{roleId}',                    [\App\Http\Controllers\MenuController::class, 'updateRolePermission']);
    Route::delete('/menus/{menuId}/roles/{roleId}',                 [\App\Http\Controllers\MenuController::class, 'removeRolePermission']);
    Route::post('/menus/{menuId}/roles/{roleId}/delete',            [\App\Http\Controllers\MenuController::class, 'removeRolePermission']);

    // Role management
    Route::get('/roles',                                            [\App\Http\Controllers\RoleController::class, 'index']);
    Route::post('/roles',                                           [\App\Http\Controllers\RoleController::class, 'store']);
    Route::get('/roles/{id}',                                       [\App\Http\Controllers\RoleController::class, 'show']);
    Route::put('/roles/{id}',                                       [\App\Http\Controllers\RoleController::class, 'update']);
    Route::delete('/roles/{id}',                                    [\App\Http\Controllers\RoleController::class, 'destroy']);
    Route::post('/roles/{id}/delete',                               [\App\Http\Controllers\RoleController::class, 'destroy']);
    Route::get('/roles/{id}/permissions',                           [\App\Http\Controllers\RoleController::class, 'permissions']);
    Route::put('/roles/{id}/permissions/{menuId}',                  [\App\Http\Controllers\RoleController::class, 'updatePermission']);
    Route::post('/roles/{id}/permissions/{menuId}/revoke',          [\App\Http\Controllers\RoleController::class, 'removePermission']);
    Route::delete('/roles/{id}/permissions/{menuId}',               [\App\Http\Controllers\RoleController::class, 'removePermission']);
    Route::post('/roles/{id}/permissions/{menuId}/delete',          [\App\Http\Controllers\RoleController::class, 'removePermission']);
    Route::get('/roles/{id}/employees',                             [\App\Http\Controllers\RoleController::class, 'employees']);

    // Holiday management (Manajemen → Hari Libur)
    Route::get('/management/holidays',         [\App\Http\Controllers\HolidayManagementController::class, 'index']);
    Route::post('/management/holidays',        [\App\Http\Controllers\HolidayManagementController::class, 'store']);
    Route::put('/management/holidays/{id}',    [\App\Http\Controllers\HolidayManagementController::class, 'update']);
    Route::delete('/management/holidays/{id}', [\App\Http\Controllers\HolidayManagementController::class, 'destroy']);
    Route::post('/management/holidays/{id}/delete', [\App\Http\Controllers\HolidayManagementController::class, 'destroy']);

    // Employee ↔ Role assignment
    // Menentukan role seseorang = menentukan izinnya, jadi digate sama dengan
    // aksi master employee lain (mirror PATCH /employees/{id}/change-role).
    Route::get('/employees/{employeeId}/roles',                     [\App\Http\Controllers\RoleController::class, 'employeeRoles']);
    Route::post('/employees/{employeeId}/roles',                    [\App\Http\Controllers\RoleController::class, 'assignRoles'])->middleware('menu:master.employee.action');
    Route::put('/employees/{employeeId}/roles',                     [\App\Http\Controllers\RoleController::class, 'syncRoles'])->middleware('menu:master.employee.action');
    Route::delete('/employees/{employeeId}/roles/{roleId}',         [\App\Http\Controllers\RoleController::class, 'revokeRole'])->middleware('menu:master.employee.action');
    Route::post('/employees/{employeeId}/roles/{roleId}/delete',    [\App\Http\Controllers\RoleController::class, 'revokeRole'])->middleware('menu:master.employee.action');

    }); // end auth.session protected group
});

// ==================== JARVIES EXTERNAL API ====================
// Diakses dari server Jarvies menggunakan X-Api-Key header
// Tidak butuh browser session — autentikasi via JARVIES_API_KEY di .env
Route::middleware(['jarvies.api_key'])->prefix('jarvies')->group(function () {

    // --- Customer data (read-only) ---
    Route::get('/customers/{customerId}', [CustomerController::class, 'show']);
    Route::get('/customers/{customerId}/basic-data', [CustomerBasicDataController::class, 'show']);

    Route::get('/customers/{customerId}/contacts', [CustomerContactController::class, 'index']);
    Route::get('/customers/{customerId}/contacts/{contactId}', [CustomerContactController::class, 'show']);

    Route::get('/customers/{customerId}/addresses', [CustomerAddressController::class, 'index']);
    Route::get('/customers/{customerId}/addresses/{addressId}', [CustomerAddressController::class, 'show']);

    Route::get('/customers/{customerId}/identifications', [CustomerIdentificationController::class, 'index']);
    Route::get('/customers/{customerId}/identifications/{identificationId}', [CustomerIdentificationController::class, 'show']);

    Route::get('/customers/{customerId}/banks', [CustomerBankController::class, 'index']);
    Route::get('/customers/{customerId}/banks/{bankId}', [CustomerBankController::class, 'show']);

    Route::get('/customers/{customerId}/attachments', [CustomerAttachmentController::class, 'index']);
    Route::get('/customers/{customerId}/attachments/{attachmentId}', [CustomerAttachmentController::class, 'show']);
    Route::get('/customers/{customerId}/attachments/{attachmentId}/download', [CustomerAttachmentController::class, 'download']);

    // --- Tickets (customer-scoped) ---
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::get('/tickets/{ticketId}/messages', [TicketMessageController::class, 'index']);
    Route::post('/tickets/{ticketId}/customer-reply', [TicketMessageController::class, 'customerReply']);
    Route::put('/tickets/{ticketId}/messages/mark-all-read', [TicketMessageController::class, 'markAllRead']);

    // --- Staging tickets (submit new ticket from Jarvies) ---
    Route::post('/staging-tickets', [StagingTicketController::class, 'jarviesStore']);
    Route::get('/staging-tickets', [StagingTicketController::class, 'index']);
    Route::get('/staging-tickets/{id}', [StagingTicketController::class, 'show']);
    Route::get('/staging-tickets/{id}/preview-body', [StagingTicketController::class, 'previewBody']);
    Route::get('/staging-tickets/{id}/email-attachments', [StagingTicketController::class, 'emailAttachments']);
    Route::get('/staging-tickets/{id}/attachment-download', [StagingTicketController::class, 'emailAttachmentDownload']);

    // --- Customer Mandays (Jarvies customer-side) ---
    Route::get('/tickets/{ticketId}/mandays', [MandaysController::class, 'customerMandaysForJarvies']);
    Route::post('/tickets/{ticketId}/mandays/approve', [MandaysController::class, 'customerApproveMandays']);
    Route::post('/tickets/{ticketId}/mandays/reject', [MandaysController::class, 'customerRejectMandays']);
});

// ==================== EXTERNAL TICKET API ====================
// Requires X-Api-Key header matching EXTERNAL_TICKET_API_KEY
Route::middleware(['external.api_key'])->prefix('external')->group(function () {
    Route::get('/tickets', [TicketController::class, 'externalIndex']);
    Route::get('/tickets/create', [TicketController::class, 'storeExternalQuery']);
    Route::get('/tickets/{data}', [TicketController::class, 'storeExternal']);
});
