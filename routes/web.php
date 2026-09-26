<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home')->name('home');
Route::view('/login', 'pages.login')->name('login');
Route::view('/forgot-password', 'pages.forgot-password')->name('password.request');
Route::view('/register', 'pages.register')->name('register');
Route::view('/create-ticket', 'pages.create-ticket')->name('ticket.create');
Route::view('/ticket-submitted', 'pages.ticket-submitted')->name('ticket.submitted');
Route::view('/check-ticket', 'pages.check-ticket')->name('ticket.check');
Route::view('/knowledge-base', 'pages.knowledge-base')->name('knowledge-base');

Route::prefix('staff')->name('staff.')->group(function () {
    Route::view('/login', 'staff.login')->name('login');
    Route::view('/dashboard', 'staff.dashboard')->name('dashboard');
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
