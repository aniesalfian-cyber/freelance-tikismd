<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tugas pasti harian digenerate setiap pagi 06:00 WIB.
Schedule::command('tasks:generate-daily')->dailyAt('06:00')->withoutOverlapping();
// Backup database otomatis 01:00 WIB.
Schedule::command('backup:run')->dailyAt('01:00')->withoutOverlapping();
// Evaluasi KPI harian 23:59 WIB.
Schedule::command('kpi:calculate-daily')->dailyAt('23:59')->withoutOverlapping();
