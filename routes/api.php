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
use App\Http\Controllers\CustomerHistoryController;
use App\Http\Controllers\CustomerCredentialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\StagingTicketController;
use App\Http\Controllers\MandaysController;
use App\Http\Controllers\NotificationController;

Route::middleware(['web'])->group(function () {

    // ==================== AUTH ROUTES ====================
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // ==================== EMPLOYEE ROUTES ====================

    // Main Employee endpoints
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'getData']);
        Route::post('/', [EmployeeController::class, 'store']);
        Route::get('/roles', [EmployeeController::class, 'getRoles']);
        Route::get('/mentionable', [EmployeeController::class, 'getMentionable']);
        Route::get('/{id}', [EmployeeController::class, 'getDetail']);
        Route::put('/{id}', [EmployeeController::class, 'update']);
        Route::delete('/{id}', [EmployeeController::class, 'destroy']);
        Route::patch('/{id}/change-password', [EmployeeController::class, 'changePassword']);
        Route::patch('/{id}/change-role', [EmployeeController::class, 'changeRole']);
    });

    // Employee Basic Data endpoints
    Route::prefix('employees/{employeeId}')->group(function () {
        Route::get('/basic-data', [EmployeeBasicDataController::class, 'show']);
        Route::post('/basic-data', [EmployeeBasicDataController::class, 'store']);
        Route::delete('/basic-data', [EmployeeBasicDataController::class, 'destroy']);
    });

    // Employee Address endpoints
    Route::middleware(['auth.session'])->group(function () {
        Route::get('/employees/{employeeId}/addresses', [EmployeeAddressController::class, 'index']);
        Route::get('/employees/{employeeId}/addresses/{addressId}', [EmployeeAddressController::class, 'show']);
        Route::post('/employees/{employeeId}/addresses', [EmployeeAddressController::class, 'store']);
        Route::put('/employees/{employeeId}/addresses/{addressId}', [EmployeeAddressController::class, 'update']);
        Route::delete('/employees/{employeeId}/addresses/{addressId}', [EmployeeAddressController::class, 'destroy']);
        Route::patch('/employees/{employeeId}/addresses/{addressId}/set-primary', [EmployeeAddressController::class, 'setPrimary']);
    });

    // Employee Identification endpoints
    Route::prefix('employees/{employeeId}/identifications')->group(function () {
        Route::get('/', [EmployeeIdentificationController::class, 'index']);
        Route::post('/', [EmployeeIdentificationController::class, 'store']);
        Route::get('/{identificationId}', [EmployeeIdentificationController::class, 'show']);
        Route::put('/{identificationId}', [EmployeeIdentificationController::class, 'update']);
        Route::delete('/{identificationId}', [EmployeeIdentificationController::class, 'destroy']);
    });

    // Employee Family endpoints
    Route::prefix('employees/{employeeId}')->group(function () {
        Route::get('/family', [EmployeeFamilyController::class, 'index']);
        Route::get('/family/statistics', [EmployeeFamilyController::class, 'statistics']);
        Route::get('/family/{familyId}', [EmployeeFamilyController::class, 'show']);
        Route::post('/family', [EmployeeFamilyController::class, 'store']);
        Route::put('/family/{familyId}', [EmployeeFamilyController::class, 'update']);
        Route::delete('/family/{familyId}', [EmployeeFamilyController::class, 'destroy']);
    });

    // Employee Education endpoints
    Route::prefix('employees/{employeeId}/education')->group(function () {
        Route::get('/', [EmployeeEducationController::class, 'index']);
        Route::get('/{educationId}', [EmployeeEducationController::class, 'show']);
        Route::post('/', [EmployeeEducationController::class, 'store']);
        Route::put('/{educationId}', [EmployeeEducationController::class, 'update']);
        Route::delete('/{educationId}', [EmployeeEducationController::class, 'destroy']);
    });

    // Employee Qualification endpoints
    Route::prefix('employees/{employeeId}/qualification')->group(function () {
        Route::get('/', [EmployeeQualificationController::class, 'index']);
        Route::get('/{qualificationId}', [EmployeeQualificationController::class, 'show']);
        Route::post('/', [EmployeeQualificationController::class, 'store']);
        Route::put('/{qualificationId}', [EmployeeQualificationController::class, 'update']);
        Route::delete('/{qualificationId}', [EmployeeQualificationController::class, 'destroy']);
    });

    // Employee Contract endpoints
    Route::prefix('employees/{employeeId}/contract')->group(function () {
        Route::get('/', [EmployeeContractController::class, 'index']);
        Route::get('/{contractId}', [EmployeeContractController::class, 'show']);
        Route::post('/', [EmployeeContractController::class, 'store']);
        Route::put('/{contractId}', [EmployeeContractController::class, 'update']);
        Route::delete('/{contractId}', [EmployeeContractController::class, 'destroy']);
    });

    // Employee Bank endpoints
    Route::prefix('employees/{employeeId}/bank')->group(function () {
        Route::get('/', [EmployeeBankController::class, 'index']);
        Route::get('/{bankId}', [EmployeeBankController::class, 'show']);
        Route::post('/', [EmployeeBankController::class, 'store']);
        Route::put('/{bankId}', [EmployeeBankController::class, 'update']);
        Route::delete('/{bankId}', [EmployeeBankController::class, 'destroy']);
    });

    // Employee Payment endpoints
    Route::prefix('employees/{employeeId}/payment')->group(function () {
        Route::get('/', [EmployeePaymentController::class, 'index']);
        Route::get('/{paymentId}', [EmployeePaymentController::class, 'show']);
        Route::post('/', [EmployeePaymentController::class, 'store']);
        Route::put('/{paymentId}', [EmployeePaymentController::class, 'update']);
        Route::delete('/{paymentId}', [EmployeePaymentController::class, 'destroy']);
    });

    // Employee Attachment endpoints
    Route::prefix('employees/{employeeId}/attachments')->group(function () {
        Route::get('/', [EmployeeAttachmentController::class, 'index']);
        Route::post('/', [EmployeeAttachmentController::class, 'store']);
        Route::delete('/{attachmentId}', [EmployeeAttachmentController::class, 'destroy']);
    });

// ==================== CUSTOMER ROUTES ====================

    // Main Customer endpoints
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'getData']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::get('/search', [CustomerController::class, 'search']);
        Route::get('/statistics', [CustomerController::class, 'statistics']);
        Route::get('/{id}', [CustomerController::class, 'show']);
        Route::put('/{id}', [CustomerController::class, 'update']);
        Route::delete('/{id}', [CustomerController::class, 'destroy']);
        Route::post('/{id}/soft-delete', [CustomerController::class, 'softDelete']);
        Route::post('/{id}/restore', [CustomerController::class, 'restore']);
    });

    // Customer Basic Data endpoints
    Route::prefix('customers/{customerId}/basic-data')->group(function () {
        Route::get('/', [CustomerBasicDataController::class, 'show']);
        Route::post('/', [CustomerBasicDataController::class, 'store']);
        Route::delete('/', [CustomerBasicDataController::class, 'destroy']);
    });

    // Customer Address endpoints
    Route::prefix('customers/{customerId}/addresses')->group(function () {
        Route::get('/', [CustomerAddressController::class, 'index']);
        Route::get('/{addressId}', [CustomerAddressController::class, 'show']);
        Route::post('/', [CustomerAddressController::class, 'store']);
        Route::put('/{addressId}', [CustomerAddressController::class, 'update']);
        Route::delete('/{addressId}', [CustomerAddressController::class, 'destroy']);
    });

    // Customer Contact endpoints
    Route::prefix('customers/{customerId}/contacts')->group(function () {
        Route::get('/', [CustomerContactController::class, 'index']);
        Route::get('/{contactId}', [CustomerContactController::class, 'show']);
        Route::post('/', [CustomerContactController::class, 'store']);
        Route::put('/{contactId}', [CustomerContactController::class, 'update']);
        Route::delete('/{contactId}', [CustomerContactController::class, 'destroy']);
        // Jarvies login management per contact person
        Route::post('/{contactId}/create-login', [CustomerContactController::class, 'createLogin']);
        Route::delete('/{contactId}/revoke-login', [CustomerContactController::class, 'revokeLogin']);
    });

    // Customer Identification endpoints
    Route::prefix('customers/{customerId}/identifications')->group(function () {
        Route::get('/', [CustomerIdentificationController::class, 'index']);
        Route::get('/{identificationId}', [CustomerIdentificationController::class, 'show']);
        Route::post('/', [CustomerIdentificationController::class, 'store']);
        Route::put('/{identificationId}', [CustomerIdentificationController::class, 'update']);
        Route::delete('/{identificationId}', [CustomerIdentificationController::class, 'destroy']);
    });

    // Customer Bank endpoints
    Route::prefix('customers/{customerId}/banks')->group(function () {
        Route::get('/', [CustomerBankController::class, 'index']);
        Route::get('/{bankId}', [CustomerBankController::class, 'show']);
        Route::post('/', [CustomerBankController::class, 'store']);
        Route::put('/{bankId}', [CustomerBankController::class, 'update']);
        Route::delete('/{bankId}', [CustomerBankController::class, 'destroy']);
    });

    // Customer Attachment endpoints
    Route::prefix('customers/{customerId}/attachments')->group(function () {
        Route::get('/', [CustomerAttachmentController::class, 'index']);
        Route::get('/{attachmentId}', [CustomerAttachmentController::class, 'show']);
        Route::post('/', [CustomerAttachmentController::class, 'store']);
        Route::put('/{attachmentId}', [CustomerAttachmentController::class, 'update']);
        Route::delete('/{attachmentId}', [CustomerAttachmentController::class, 'destroy']);
        Route::get('/{attachmentId}/download', [CustomerAttachmentController::class, 'download']);
    });

    // Customer Credential endpoints
    Route::get('customers/{customerId}/credential', [CustomerCredentialController::class, 'show']);
    Route::post('customers/{customerId}/credential', [CustomerCredentialController::class, 'store']);

    // Customer History endpoints
    Route::prefix('customers/{customerId}/history')->group(function () {
        Route::get('/', [CustomerHistoryController::class, 'index']);
        Route::post('/', [CustomerHistoryController::class, 'store']);
        Route::get('/statistics', [CustomerHistoryController::class, 'statistics']);
        Route::delete('/cleanup', [CustomerHistoryController::class, 'cleanup']);
    });

    // ==================== STAGING TICKET ROUTES ====================
    Route::prefix('staging-tickets')->group(function () {
        Route::get('/statistics', [StagingTicketController::class, 'statistics']);
        Route::get('/', [StagingTicketController::class, 'index']);
        Route::post('/', [StagingTicketController::class, 'store']);
        Route::get('/{id}', [StagingTicketController::class, 'show']);
        Route::get('/{id}/preview-body', [StagingTicketController::class, 'previewBody']);
        Route::post('/{id}/approve', [StagingTicketController::class, 'approve']);
        Route::post('/{id}/reject', [StagingTicketController::class, 'reject']);
    });

    // ==================== TICKET ROUTES ====================
    Route::prefix('tickets')->group(function () {
        // Static routes first
        Route::get('/', [TicketController::class, 'index']);
        Route::get('/my', [TicketController::class, 'myTickets']);
        Route::get('/statistics', [TicketController::class, 'statistics']);
        Route::get('/pending-confirmations', [TicketController::class, 'pendingConfirmations']);
        Route::get('/pending-member-changes', [TicketController::class, 'pendingMemberChanges']);
        Route::post('/', [TicketController::class, 'store']);

        // Routes with specific names
        Route::get('/status/{status}', [TicketController::class, 'getByStatus']);
        Route::post('/confirm-assignment/{confirmationId}', [TicketController::class, 'confirmAssignment']);
        Route::post('/member-change-requests/{changeRequestId}/{action}', [TicketController::class, 'processMemberChangeRequest']);

        // Routes with {id} parameter last
        Route::get('/{id}', [TicketController::class, 'show']);
        Route::get('/{id}/mandays-history', [TicketController::class, 'getMandaysHistory']);
        Route::get('/{id}/negotiation-history', [TicketController::class, 'getNegotiationHistory']);
        Route::post('/{id}/take', [TicketController::class, 'takeTicket']);
        Route::put('/{id}', [TicketController::class, 'update']);
        Route::put('/{id}/update-status', [TicketController::class, 'updateTicketStatus']);
        Route::put('/{id}/update-mandays', [TicketController::class, 'updateManDays']);
        Route::delete('/{id}', [TicketController::class, 'destroy']);
        Route::post('/{id}/send-to-customer', [TicketController::class, 'sendToCustomer']);
        Route::post('/{id}/customer-response', [TicketController::class, 'customerResponse']);
        Route::post('/{id}/admin-response', [TicketController::class, 'adminResponse']);
        Route::post('/{id}/members', [TicketController::class, 'addMember']);
        Route::delete('/{id}/members/{employeeId}', [TicketController::class, 'removeMember']);
        Route::post('/{id}/update-members', [TicketController::class, 'updateMembers']);
        Route::post('/{id}/request-member-change', [TicketController::class, 'requestMemberChange']);
        Route::delete('/{id}/remove-member/{employeeId}', [TicketController::class, 'removeMember']);
        Route::delete('/{id}/request-member-removal/{employeeId}', [TicketController::class, 'requestMemberRemoval']);

        // Ticket Messages
        Route::get('/{ticketId}/messages', [TicketMessageController::class, 'index']);
        Route::post('/{ticketId}/messages', [TicketMessageController::class, 'store']);
        Route::post('/{ticketId}/customer-reply', [TicketMessageController::class, 'customerReply']);
        Route::put('/{ticketId}/messages/mark-all-read', [TicketMessageController::class, 'markAllRead']);

        // Assign ticket to delivery support
        Route::get('/{id}/available-supports', [TicketController::class, 'getAvailableSupports']);
        Route::post('/{id}/assign-to-support', [TicketController::class, 'assignToSupport']);
        Route::post('/{id}/create-delivery-support', [TicketController::class, 'createDeliverySupport']);

        // ==================== MANDAYS ROUTES ====================
        // Shared utility
        Route::get('/{ticketId}/mandays/modules', [MandaysController::class, 'getModules']);

        // Customer Mandays — PIC
        Route::get('/{ticketId}/mandays/pic-draft', [MandaysController::class, 'getCustomerDraft']);
        Route::post('/{ticketId}/mandays/pic-draft', [MandaysController::class, 'saveCustomerDraft']);
        Route::post('/{ticketId}/mandays/pic-draft/submit', [MandaysController::class, 'submitCustomerDraft']);

        // Customer Mandays — Helpdesk
        Route::get('/{ticketId}/mandays/hd-draft', [MandaysController::class, 'getHelpdeskDraft']);
        Route::put('/{ticketId}/mandays/hd-draft', [MandaysController::class, 'saveHelpdeskDraft']);
        Route::post('/{ticketId}/mandays/hd-draft/submit-chat', [MandaysController::class, 'submitToChat']);
        Route::post('/{ticketId}/mandays/hd-draft/approve', [MandaysController::class, 'approveCustomerMandays']);
        Route::post('/{ticketId}/mandays/hd-draft/cancel', [MandaysController::class, 'cancelCustomerMandays']);

        // Internal Mandays — PIC + Head of Support
        Route::get('/{ticketId}/mandays/internal', [MandaysController::class, 'getInternalProposal']);
        Route::post('/{ticketId}/mandays/internal', [MandaysController::class, 'saveInternalProposal']);
        Route::post('/{ticketId}/mandays/internal/submit', [MandaysController::class, 'submitInternalProposal']);
        Route::post('/{ticketId}/mandays/internal/approve', [MandaysController::class, 'approveInternalProposal']);
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

    // ==================== TIMESHEET ROUTES ====================
    Route::prefix('timesheets')->group(function () {
        Route::get('/', [TimesheetController::class, 'index']);
        Route::get('/statistics', [TimesheetController::class, 'statistics']);
        Route::get('/submitted-for-approval', [TimesheetController::class, 'submittedForApproval']); // For heads to review
        Route::get('/my-projects', [TimesheetController::class, 'myProjects']);
        Route::get('/my-activities/all', [TimesheetController::class, 'allMyActivities']); // Get ALL assigned activities
        Route::get('/my-activities/{projectId}', [TimesheetController::class, 'myActivities']); // Get activities for specific project
        Route::get('/{id}', [TimesheetController::class, 'show']);
        Route::post('/', [TimesheetController::class, 'store']);
        Route::put('/{id}', [TimesheetController::class, 'update']);
        Route::delete('/{id}', [TimesheetController::class, 'destroy']);
        Route::post('/{id}/submit', [TimesheetController::class, 'submit']);
        Route::post('/{id}/approve', [TimesheetController::class, 'approve']);
        Route::post('/{id}/reject', [TimesheetController::class, 'reject']);
    });

    // ==================== NOTIFICATION ROUTES ====================
    Route::prefix('notifications')->group(function () {
        Route::get('/',              [NotificationController::class, 'apiIndex']);
        Route::get('/unread-count',  [NotificationController::class, 'unreadCount']);
        Route::put('/read-all',      [NotificationController::class, 'markAllRead']);
        Route::put('/{id}/read',     [NotificationController::class, 'markRead']);
        Route::delete('/bulk-delete', [NotificationController::class, 'bulkDelete']);
    });

    // ==================== EMAIL ROUTES ====================
    Route::prefix('email')->group(function () {
        Route::get('/inbox', [EmailController::class, 'inbox']);
        Route::post('/process-inbox', [EmailController::class, 'processInbox']);
        Route::post('/send', [EmailController::class, 'send']);
        Route::post('/reply', [EmailController::class, 'reply']);
        Route::post('/messages/{messageId}/reprocess-attachments', [EmailController::class, 'reprocessAttachments']);
    });
});

// ==================== MOBILE AUTH — CUSTOMER ====================
// Route::prefix('mobile')->group(function () {
//     Route::post('/auth/login', [\App\Http\Controllers\Mobile\AuthController::class, 'login']);
//     Route::post('/auth/refresh', [\App\Http\Controllers\Mobile\AuthController::class, 'refresh']);

//     Route::middleware(['mobile.customer'])->group(function () {
//         Route::post('/auth/logout', [\App\Http\Controllers\Mobile\AuthController::class, 'logout']);
//         Route::get('/auth/me', [\App\Http\Controllers\Mobile\AuthController::class, 'me']);
//     });
// });

// ==================== MOBILE AUTH — EMPLOYEE ====================
Route::prefix('mobile/employee')->group(function () {
    Route::post('/auth/login', [\App\Http\Controllers\Mobile\EmployeeAuthController::class, 'login']);
    Route::post('/auth/refresh', [\App\Http\Controllers\Mobile\EmployeeAuthController::class, 'refresh']);

    Route::middleware(['mobile.employee'])->group(function () {
        Route::post('/auth/logout', [\App\Http\Controllers\Mobile\EmployeeAuthController::class, 'logout']);
        Route::get('/auth/me', [\App\Http\Controllers\Mobile\EmployeeAuthController::class, 'me']);

        // Dashboard
        Route::get('/dashboard', [\App\Http\Controllers\Mobile\DashboardController::class, 'index']);

        // ==================== TICKET ROUTES (MOBILE) ====================
        Route::prefix('tickets')->group(function () {
            Route::get('/',                            [\App\Http\Controllers\Mobile\TicketController::class, 'index']);
            Route::post('/',                           [\App\Http\Controllers\Mobile\TicketController::class, 'store']);
            Route::get('/stats',                       [\App\Http\Controllers\Mobile\TicketController::class, 'stats']);
            Route::get('/{id}',                        [\App\Http\Controllers\Mobile\TicketController::class, 'show']);
            Route::put('/{id}/status',                 [\App\Http\Controllers\Mobile\TicketController::class, 'updateStatus']);
            Route::get('/{id}/messages',               [\App\Http\Controllers\Mobile\TicketController::class, 'getMessages']);
            Route::post('/{id}/messages',              [\App\Http\Controllers\Mobile\TicketController::class, 'sendMessage']);
            Route::post('/{id}/ownership',             [\App\Http\Controllers\Mobile\TicketController::class, 'takeOwnership']);
            Route::put('/{id}/mandays',                [\App\Http\Controllers\Mobile\TicketController::class, 'updateMandays']);
            Route::post('/{id}/send-to-customer',      [\App\Http\Controllers\Mobile\TicketController::class, 'sendToCustomer']);
        });

        // ==================== SUPPORT TICKET ROUTES (MOBILE) ====================
        Route::prefix('support-tickets')->group(function () {
            Route::get('/',      [\App\Http\Controllers\Mobile\SupportTicketController::class, 'index']);
            Route::get('/{id}',  [\App\Http\Controllers\Mobile\SupportTicketController::class, 'show']);
        });

        // ==================== PROJECT ROUTES (MOBILE) ====================
        Route::prefix('projects')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Mobile\ProjectController::class, 'index']);
            Route::get('/{id}',           [\App\Http\Controllers\Mobile\ProjectController::class, 'show']);
            Route::post('/{id}/updates',  [\App\Http\Controllers\Mobile\ProjectController::class, 'storeUpdate']);
        });
    });
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

    // --- Customer Mandays (Jarvies customer-side) ---
    Route::get('/tickets/{ticketId}/mandays', [MandaysController::class, 'customerMandaysForJarvies']);
    Route::post('/tickets/{ticketId}/mandays/approve', [MandaysController::class, 'customerApproveMandays']);
    Route::post('/tickets/{ticketId}/mandays/reject', [MandaysController::class, 'customerRejectMandays']);
});

// ==================== EXTERNAL TICKET API ====================
Route::get('/external/tickets', [TicketController::class, 'externalIndex']);
Route::get('/external/tickets/create', [TicketController::class, 'storeExternalQuery']);
Route::get('/external/tickets/{data}', [TicketController::class, 'storeExternal']);

// ==================== TEST ROUTE ====================
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working!',
        'timestamp' => now()
    ]);
});
