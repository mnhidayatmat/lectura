<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mobile API tokens expire after 30 days (config/sanctum.php); drop the dead rows.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
