<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mobile API tokens expire after 30 days (config/sanctum.php); drop the dead rows.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Watch: notify students when a scheduled episode's release time arrives.
Artisan::command('episodes:announce', function (\App\Services\Episodes\EpisodeAnnouncer $announcer) {
    $this->info($announcer->announceDue().' episode(s) announced.');
})->purpose('Notify enrolled students about newly released episodes');

Schedule::command('episodes:announce')->everyFiveMinutes()->withoutOverlapping();

// Active learning: end live sessions left open past ACTIVE_LEARNING_AUTO_CLOSE_HOURS.
Artisan::command('active-learning:close-stale', function (\App\Services\ActiveLearning\SessionService $sessions) {
    $this->info($sessions->closeStaleSessions().' session(s) closed.');
})->purpose('End active-learning sessions left open past the time limit');

Schedule::command('active-learning:close-stale')->everyTenMinutes()->withoutOverlapping();

// Live quizzes: end lobbies and runs left idle past QUIZ_AUTO_CLOSE_HOURS.
Artisan::command('quizzes:close-stale', function (\App\Services\Quiz\LiveQuizService $quizzes) {
    $this->info($quizzes->closeStale().' quiz(zes) closed.');
})->purpose('End live quizzes left open with no activity past the time limit');

Schedule::command('quizzes:close-stale')->everyTenMinutes()->withoutOverlapping();
