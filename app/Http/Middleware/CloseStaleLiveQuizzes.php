<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Quiz\LiveQuizService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends live quizzes left open with no activity, at most once a minute, before the
 * request runs. This keeps auto-close working where no cron drives the scheduler;
 * `quizzes:close-stale` does the same on a schedule.
 */
class CloseStaleLiveQuizzes
{
    public function __construct(private readonly LiveQuizService $quizzes) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('lectura.quiz.auto_close_hours') > 0 && Cache::add('live-quiz:sweep', true, 60)) {
            try {
                $this->quizzes->closeStale();
            } catch (\Throwable $e) {
                // Never let housekeeping break the page someone asked for.
                report($e);
            }
        }

        return $next($request);
    }
}
