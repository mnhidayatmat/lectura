<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\ActiveLearning;

use App\Http\Controllers\Controller;
use App\Models\ActiveLearningPlan;
use App\Models\ActiveLearningSession;
use App\Models\Course;
use App\Services\ActiveLearning\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function __construct(
        protected SessionService $sessionService,
    ) {}

    /**
     * Start a live session from a published plan.
     */
    public function start(string $tenantSlug, Course $course, ActiveLearningPlan $plan): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $session = $this->sessionService->startSession($plan, auth()->user());

        return redirect()->route('tenant.active-learning.sessions.dashboard', [
            $tenantSlug, $course, $plan, $session,
        ]);
    }

    /**
     * Live dashboard for lecturer during active session.
     */
    public function dashboard(string $tenantSlug, Course $course, ActiveLearningPlan $plan, ActiveLearningSession $session): View
    {
        if ($session->created_by !== auth()->id()) {
            abort(403);
        }

        $session->load(['plan.activities.pollOptions', 'currentActivity.pollOptions']);
        $activities = $plan->activities()->orderBy('sort_order')->get();

        return view('tenant.active-learning.sessions.dashboard', compact(
            'course', 'plan', 'session', 'activities'
        ));
    }

    /**
     * Advance to the next activity.
     */
    public function advance(string $tenantSlug, Course $course, ActiveLearningPlan $plan, ActiveLearningSession $session): RedirectResponse|JsonResponse
    {
        if ($session->created_by !== auth()->id()) {
            abort(403);
        }

        $session = $this->sessionService->advanceActivity($session);

        if (request()->wantsJson()) {
            return response()->json($this->sessionService->getLecturerState($session));
        }

        if ($session->isCompleted()) {
            return redirect()->route('tenant.active-learning.sessions.summary', [
                $tenantSlug, $course, $plan, $session,
            ]);
        }

        return back();
    }

    /**
     * End the session.
     */
    public function end(string $tenantSlug, Course $course, ActiveLearningPlan $plan, ActiveLearningSession $session): RedirectResponse
    {
        if ($session->created_by !== auth()->id()) {
            abort(403);
        }

        $session = $this->sessionService->endSession($session);

        return redirect()->route('tenant.active-learning.sessions.summary', [
            $tenantSlug, $course, $plan, $session,
        ]);
    }

    /**
     * JSON state endpoint for polling fallback.
     */
    public function state(string $tenantSlug, Course $course, ActiveLearningPlan $plan, ActiveLearningSession $session): JsonResponse
    {
        if ($session->created_by !== auth()->id()) {
            abort(403);
        }

        return response()->json($this->sessionService->getLecturerState($session));
    }

    /**
     * Post-session summary view.
     */
    public function summary(string $tenantSlug, Course $course, ActiveLearningPlan $plan, ActiveLearningSession $session): View
    {
        if ($session->created_by !== auth()->id()) {
            abort(403);
        }

        $session->load(['plan.activities.pollOptions', 'participants.user', 'responses.user', 'responses.activity']);
        $activities = $plan->activities()->withCount('responses')->orderBy('sort_order')->get();

        return view('tenant.active-learning.sessions.summary', compact(
            'course', 'plan', 'session', 'activities'
        ));
    }
}
