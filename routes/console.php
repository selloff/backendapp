<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('selloff:update-currency-rates')->hourly();
Schedule::command('selloff:notify-expired-memberships')->dailyAt('08:00');
Schedule::command('selloff:deactivate-expired-memberships')->dailyAt('08:15');
Schedule::command('selloff:expire-membership-top-boosts')->dailyAt('08:30');
Schedule::command('selloff:membership-auto-bump')->hourly();
Schedule::command('selloff:escrow-process-releases')->hourly();
