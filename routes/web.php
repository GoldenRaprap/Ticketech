<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\StaffSessionController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
Route::get('/create-ticket', [TicketController::class, 'create'])->name('ticket.create');
Route::post('/create-ticket', [TicketController::class, 'store'])->middleware('throttle:10,1')->name('ticket.store');
Route::get('/ticket-submitted', [TicketController::class, 'submitted'])->name('ticket.submitted');
Route::get('/check-ticket', [TicketController::class, 'lookup'])->name('ticket.check');
Route::post('/check-ticket', [TicketController::class, 'show'])->middleware('throttle:10,1')->name('ticket.lookup');
Route::post('/ticket-attachments/{attachment}/download', [TicketController::class, 'download'])->middleware('throttle:15,1')->name('ticket.attachments.download');
Route::view('/knowledge-base', 'pages.knowledge-base')->name('knowledge-base');

Route::prefix('staff')->name('staff.')->group(function () {
    Route::get('/login', [StaffSessionController::class, 'create'])->name('login');
    Route::post('/login', [StaffSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

    Route::middleware('staff')->group(function () {
        Route::get('/dashboard', StaffDashboardController::class)->name('dashboard');
        Route::view('/tickets', 'staff.tickets')->name('tickets');
        Route::view('/tickets/ED-2026-00125', 'staff.ticket-detail')->name('ticket-detail');
        Route::view('/knowledge-base', 'staff.knowledge-base')->name('knowledge-base');
        Route::view('/reports', 'staff.reports')->name('reports');
        Route::view('/notifications', 'staff.notifications')->name('notifications');
        Route::view('/profile', 'staff.profile')->name('profile');
        Route::view('/users', 'staff.users')->name('users');
        Route::view('/categories', 'staff.categories')->name('categories');
        Route::view('/sla-settings', 'staff.sla-settings')->name('sla-settings');
        Route::view('/audit-logs', 'staff.audit-logs')->name('audit-logs');
        Route::view('/system-settings', 'staff.system-settings')->name('system-settings');
    });
});
