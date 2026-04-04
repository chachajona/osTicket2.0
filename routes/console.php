<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tickets:fetch-mail')->everyFiveMinutes();
Schedule::command('tickets:check-overdue')->everyFiveMinutes();
Schedule::command('system:purge-logs')->dailyAt('03:00');
Schedule::command('drafts:cleanup')->daily();
Schedule::command('files:cleanup')->weekly();
