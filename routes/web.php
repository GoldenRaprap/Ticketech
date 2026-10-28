<?php

use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminModerationController;
use App\Http\Controllers\AdminSlaSettingsController;
use App\Http\Controllers\AdminSystemSettingsController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KnowledgeArticleController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\StaffNotificationController;
use App\Http\Controllers\StaffProfileController;
use App\Http\Controllers\StaffRegistrationController;
use App\Http\Controllers\StaffReportsController;
use App\Http\Controllers\StaffTicketController;
use App\Http\Controllers\TicketAttachmentPreviewController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('redirect.workspace-users')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/Helpdesk', HomeController::class)->name('helpdesk');
    Route::get('/knowledge-base', [KnowledgeArticleController::class, 'publicIndex'])->name('knowledge-base');
    Route::get('/knowledge-base/{article:slug}', [KnowledgeArticleController::class, 'publicShow'])->name('knowledge-base.show');
    Route::post('/knowledge-base/{article:slug}/reviews', [KnowledgeArticleController::class, 'storeReview'])
        ->middleware('throttle:5,1')
        ->name('knowledge-base.reviews.store');
});

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
Route::post('/login/preview/{role}', [AuthenticatedSessionController::class, 'preview'])->where('role', 'staff|admin')->middleware('throttle:10,1')->name('login.preview');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware('guest')->group(function () {
    Route::get('/register', [StaffRegistrationController::class, 'create'])->name('register');
    Route::post('/register', [StaffRegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
});
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/staff/approval-pending', [StaffRegistrationController::class, 'approvalPending'])
        ->name('staff.approval.pending');
    Route::get('/staff/access-denied', [StaffRegistrationController::class, 'applicationDenied'])
        ->name('staff.application.denied');
});
Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
Route::get('/create-ticket', [TicketController::class, 'create'])->name('ticket.create');
Route::post('/create-ticket', [TicketController::class, 'store'])->middleware('throttle:10,1')->name('ticket.store');
Route::get('/ticket-submitted', [TicketController::class, 'submitted'])->name('ticket.submitted');
Route::get('/check-ticket', [TicketController::class, 'lookup'])->name('ticket.check');
Route::post('/check-ticket', [TicketController::class, 'show'])->middleware('throttle:10,1')->name('ticket.lookup');
Route::post('/check-ticket/download', [TicketController::class, 'downloadTicket'])->middleware('throttle:10,1')->name('ticket.download');
Route::post('/check-ticket/reply', [TicketController::class, 'reply'])->middleware('throttle:10,1')->name('ticket.reply');
Route::post('/check-ticket/close', [TicketController::class, 'closeView'])->name('ticket.view.close');
Route::post('/ticket-attachments/{attachment}/download', [TicketController::class, 'download'])->middleware('throttle:15,1')->name('ticket.attachments.download');
Route::get('/ticket-attachments/{attachment}/preview', [TicketAttachmentPreviewController::class, 'requester'])->middleware('throttle:30,1')->name('ticket.attachments.preview');
Route::prefix('staff')->name('staff.')->group(function () {
    Route::middleware(['audit.activity', 'staff'])->group(function () {
        Route::get('/dashboard', StaffDashboardController::class)->name('dashboard');
        Route::get('/tickets', [StaffTicketController::class, 'index'])->name('tickets');
        Route::get('/tickets/{ticket:ticket_number}', [StaffTicketController::class, 'show'])->name('ticket-detail');
        Route::get('/tickets/{ticket:ticket_number}/updates', [StaffTicketController::class, 'updates'])->name('ticket-updates');
        Route::put('/tickets/{ticket:ticket_number}', [StaffTicketController::class, 'update'])->name('ticket-update');
        Route::post('/tickets/{ticket:ticket_number}/comments', [StaffTicketController::class, 'comment'])->name('ticket-comments.store');
        Route::post('/tickets/{ticket:ticket_number}/misuse-reports', [StaffTicketController::class, 'reportClient'])->name('ticket-misuse-reports.store');
        Route::get('/tickets/{ticket:ticket_number}/attachments/{attachment}', [StaffTicketController::class, 'downloadAttachment'])->name('ticket-attachments.download');
        Route::get('/tickets/{ticket:ticket_number}/attachments/{attachment}/preview', [TicketAttachmentPreviewController::class, 'staff'])->middleware('throttle:30,1')->name('ticket-attachments.preview');

        Route::get('/reports', StaffReportsController::class)->name('reports');
        Route::get('/reports/export', [StaffReportsController::class, 'export'])->name('reports.export');
        Route::get('/notifications', [StaffNotificationController::class, 'index'])->name('notifications');
        Route::patch('/notifications/read-all', [StaffNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::patch('/notifications/{notification}/read', [StaffNotificationController::class, 'markRead'])->name('notifications.read');
        Route::get('/profile', [StaffProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [StaffProfileController::class, 'update'])->name('profile.update');
    });
});

Route::prefix('admin')->name('admin.')->middleware(['audit.activity', 'admin'])->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
    Route::get('/moderation', [AdminModerationController::class, 'index'])->name('moderation');
    Route::patch('/moderation/reports/{report}/review', [AdminModerationController::class, 'review'])->name('moderation.review');
    Route::patch('/moderation/clients/{clientModeration}/clear', [AdminModerationController::class, 'clear'])->name('moderation.clear');
    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::post('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
    Route::post('/users/{user}/deny', [AdminUserController::class, 'deny'])->name('users.deny');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::get('/knowledge-base', [KnowledgeArticleController::class, 'index'])->name('knowledge-base');
    Route::put('/knowledge-base/review-settings', [KnowledgeArticleController::class, 'updateReviewSettings'])->name('knowledge-base.review-settings');
    Route::get('/knowledge-base/create', [KnowledgeArticleController::class, 'create'])->name('knowledge-base.create');
    Route::post('/knowledge-base', [KnowledgeArticleController::class, 'store'])->name('knowledge-base.store');
    Route::get('/knowledge-base/{article}/edit', [KnowledgeArticleController::class, 'edit'])->name('knowledge-base.edit');
    Route::put('/knowledge-base/{article}', [KnowledgeArticleController::class, 'update'])->name('knowledge-base.update');
    Route::delete('/knowledge-base/{article}', [KnowledgeArticleController::class, 'destroy'])->name('knowledge-base.destroy');
    Route::get('/sla-settings', [AdminSlaSettingsController::class, 'index'])->name('sla-settings');
    Route::put('/sla-settings', [AdminSlaSettingsController::class, 'update'])->name('sla-settings.update');
    Route::get('/audit-logs', AdminAuditLogController::class)->name('audit-logs');
    Route::get('/system-settings', [AdminSystemSettingsController::class, 'index'])->name('system-settings');
    Route::put('/system-settings', [AdminSystemSettingsController::class, 'update'])->name('system-settings.update');
});
