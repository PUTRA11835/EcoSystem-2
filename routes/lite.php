<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Lite\LiteAuthController;
use App\Http\Controllers\Lite\LiteDashboardController;
use App\Http\Controllers\Lite\LiteTicketController;
use App\Http\Controllers\Lite\LiteProfileController;
use App\Http\Controllers\Lite\LiteNotificationController;
use App\Http\Controllers\Lite\LitePushSubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EcoSystem Lite API Routes
|--------------------------------------------------------------------------
|
| Rute-rute ini digunakan oleh aplikasi web versi lite (EcoSystem Lite).
| Prefix: /api/lite
|
| Autentikasi:
|   - Public: POST /api/lite/auth/login
|   - Protected: middleware 'lite.auth' — session cookie ATAU Bearer token
|
*/

// ── Public: login ─────────────────────────────────────────────────────────
Route::middleware(['web'])->prefix('lite')->group(function () {

    Route::post('/auth/login', [LiteAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('lite.auth.login');

    // ── Protected routes (session atau Bearer token) ──────────────────────
    Route::middleware(['lite.auth'])->group(function () {

        // Auth
        Route::post('/auth/logout', [LiteAuthController::class, 'logout'])->name('lite.auth.logout');
        Route::get('/auth/me',      [LiteAuthController::class, 'me'])->name('lite.auth.me');

        // Dashboard
        Route::get('/dashboard', [LiteDashboardController::class, 'index'])->name('lite.dashboard');

        // Tickets
        Route::prefix('tickets')->name('lite.tickets.')->group(function () {
            Route::get('/statistics',             [LiteTicketController::class, 'statistics'])->name('statistics');
            Route::get('/my',                      [LiteTicketController::class, 'myTickets'])->name('my');
            Route::get('/',                        [LiteTicketController::class, 'index'])->name('index');
            Route::get('/{id}',                    [LiteTicketController::class, 'show'])->name('show');
            Route::patch('/{id}/status',           [LiteTicketController::class, 'updateStatus'])->name('update-status');
            Route::get('/{ticketId}/messages',     [LiteTicketController::class, 'getMessages'])->name('messages');
            Route::post('/{ticketId}/messages',    [LiteTicketController::class, 'addMessage'])->name('add-message');
            Route::post('/{ticketId}/messages/{messageId}/internal-note',        [LiteTicketController::class, 'updateInternalNote'])->name('update-internal-note');
            Route::delete('/{ticketId}/messages/{messageId}/internal-note',      [LiteTicketController::class, 'destroyInternalNote'])->name('delete-internal-note');
            Route::post('/{ticketId}/messages/{messageId}/internal-note/delete', [LiteTicketController::class, 'destroyInternalNote'])->name('delete-internal-note-post');
        });

        // Attachments (dipakai untuk menampilkan gambar/lampiran di bubble chat)
        Route::get('/attachments/{id}', [AttachmentController::class, 'show'])
            ->where('id', '[0-9]+')
            ->name('lite.attachments.show');

        // Profile
        Route::prefix('profile')->name('lite.profile.')->group(function () {
            Route::get('/',                 [LiteProfileController::class, 'show'])->name('show');
            Route::patch('/change-password', [LiteProfileController::class, 'changePassword'])->name('change-password');
        });

        // Notifications
        Route::prefix('notifications')->name('lite.notifications.')->group(function () {
            Route::get('/',                [LiteNotificationController::class, 'index'])->name('index');
            Route::get('/unread-count',    [LiteNotificationController::class, 'unreadCount'])->name('unread-count');
            Route::put('/read-all',        [LiteNotificationController::class, 'markAllRead'])->name('read-all');
            Route::put('/{id}/read',       [LiteNotificationController::class, 'markRead'])->name('read');
            Route::delete('/bulk-delete',  [LiteNotificationController::class, 'bulkDelete'])->name('bulk-delete');
            Route::delete('/{id}',         [LiteNotificationController::class, 'deleteOne'])->name('delete');
        });

        // Web Push subscriptions (untuk notifikasi saat PWA tertutup/layar terkunci)
        Route::post('/push-subscriptions', [LitePushSubscriptionController::class, 'subscribe'])->name('lite.push-subscriptions.store');
    });
});
