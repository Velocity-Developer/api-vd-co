<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// rilis GitHub baru masuk ApiCo paling lambat ±1 jam tanpa webhook per repo; hasil di storage/logs/sync-github.log
Schedule::command('projects:sync-github')
    ->hourlyAt(17)
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/sync-github.log'));
