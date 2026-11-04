<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tickets:send-resolution-sla-alerts')
    ->everyFiveMinutes()
    ->withoutOverlapping(30);

Schedule::command('tickets:expire-pending-email-verifications')
    ->hourly()
    ->withoutOverlapping(30);
