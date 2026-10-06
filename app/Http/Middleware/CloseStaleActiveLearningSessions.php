<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ActiveLearning\SessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends active-learning sessions past their time limit, at most once a minute,
 * before the request runs. This keeps auto-close working where no cron drives
 * the scheduler; `active-learning:close-stale` does the same on a schedule.
 */
class CloseStaleActiveLearningSessions
{
    public function __construct(private readonly SessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('lectura.active_learning.auto_close_hours') > 0 && Cache::add('active-learning:sweep', true, 60)) {
            try {
                $this->sessions->closeStaleSessions();
            } catch (\Throwable $e) {
                // Never let housekeeping break the page someone asked for.
                report($e);
            }
        }

        return $next($request);
    }
}
