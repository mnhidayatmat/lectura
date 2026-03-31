<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\ActiveLearning;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActiveLearning\SubmitResponseRequest;
use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningSession;
use App\Models\QuizSession;
use App\Models\SectionStudent;
use App\Services\ActiveLearning\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudentSessionController extends Controller
{
    public function __construct(
        protected SessionService $sessionService,
    ) {}

    /**
     * Live hub — shows all active sessions (AL + quizzes) for the student.
     */
    public function hub(): View
    {
        $user = auth()->user();
        $tenant = app('current_tenant');

        // Get student's enrolled section IDs
        $sectionIds = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('section_id');

        // Active learning sessions that are currently live
        $activeSessions = ActiveLearningSession::where('status', ActiveLearningSession::STATUS_ACTIVE)
            ->whereHas('plan', fn ($q) => $q->whereHas('course', fn ($cq) => $cq->whereHas('sections', fn ($sq) => $sq->whereIn('sections.id', $sectionIds))))
            ->with(['plan.course'])
            ->get();

        // Live quiz sessions (waiting/active/reviewing)
        $liveQuizzes = QuizSession::whereIn('section_id', $sectionIds)
            ->where('category', 'live')
            ->whereIn('status', ['waiting', 'active', 'reviewing'])
            ->with(['section.course'])
            ->latest()
            ->get();

        // Offline quizzes that are currently open
        $offlineQuizzes = QuizSession::whereIn('section_id', $sectionIds)
            ->where('category', 'offline')
            ->where('status', '!=', 'ended')
            ->whereNotNull('available_from')
            ->whereNotNull('available_until')
            ->where('available_from', '<=', now())
            ->where('available_until', '>=', now())
            ->with(['section.course'])
            ->latest()
            ->get();

        return view('tenant.live-hub', compact(
            'tenant', 'activeSessions', 'liveQuizzes', 'offlineQuizzes'
        ));
    }

    /**
     * Show join-by-code form.
     */
    public function joinForm(): View
    {
        $tenant = app('current_tenant');

        return view('tenant.active-learning.sessions.join', compact('tenant'));
    }

    /**
     * Process a join code.
     */
    public function joinByCode(): RedirectResponse
    {
        $code = request()->validate(['code' => 'required|string|size:6'])['code'];

        $session = $this->sessionService->findByJoinCode($code);

        if (! $session) {
            return back()->withErrors(['code' => __('active_learning.invalid_join_code')]);
        }

        $this->sessionService->joinSession($session, auth()->user());

        return redirect()->route('tenant.session.live', [
            app('current_tenant')->slug, $session,
        ]);
    }

    /**
     * Student live session view.
     */
    public function live(string $tenantSlug, ActiveLearningSession $session): View|RedirectResponse
    {
        if (! $session->isActive()) {
            if ($session->isCompleted()) {
                return redirect()->route('tenant.session.review', [$tenantSlug, $session]);
            }
            abort(404);
        }

        // Auto-join if not already a participant
        $this->sessionService->joinSession($session, auth()->user());

        $session->load(['plan.activities.pollOptions', 'currentActivity.pollOptions']);
        $tenant = app('current_tenant');

        // Get user's existing response for the current activity
        $existingResponse = null;
        if ($session->current_activity_id) {
            $existingResponse = $session->responses()
                ->where('activity_id', $session->current_activity_id)
                ->where('user_id', auth()->id())
                ->first();
        }

        return view('tenant.active-learning.sessions.live', compact(
            'tenant', 'session', 'existingResponse'
        ));
    }

    /**
     * Submit a response to the current activity.
     */
    public function respond(SubmitResponseRequest $request, string $tenantSlug, ActiveLearningSession $session): JsonResponse
    {
        $activity = ActiveLearningActivity::findOrFail($request->validated('activity_id'));

        $response = $this->sessionService->submitResponse(
            $session,
            $activity,
            auth()->user(),
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'response_id' => $response->id,
            'submitted_at' => $response->submitted_at->toISOString(),
        ]);
    }

    /**
     * JSON state endpoint for student polling fallback.
     */
    public function state(string $tenantSlug, ActiveLearningSession $session): JsonResponse
    {
        $state = $this->sessionService->getSessionState($session);

        // Add user's response status for the current activity
        if ($state['current_activity']) {
            $userResponse = $session->responses()
                ->where('activity_id', $state['current_activity']['id'])
                ->where('user_id', auth()->id())
                ->first();

            $state['current_activity']['user_responded'] = (bool) $userResponse;
            $state['current_activity']['user_response'] = $userResponse ? [
                'response_data' => $userResponse->response_data,
                'submitted_at' => $userResponse->submitted_at->toISOString(),
            ] : null;
        }

        return response()->json($state);
    }

    /**
     * Post-session review (read-only).
     */
    public function review(string $tenantSlug, ActiveLearningSession $session): View
    {
        if (! $session->isCompleted()) {
            if ($session->isActive()) {
                return redirect()->route('tenant.session.live', [$tenantSlug, $session]);
            }
            abort(404);
        }

        $session->load(['plan.activities.pollOptions']);
        $tenant = app('current_tenant');

        $userResponses = $session->responses()
            ->where('user_id', auth()->id())
            ->get()
            ->keyBy('activity_id');

        $activities = $session->plan->activities()->orderBy('sort_order')->get();

        return view('tenant.active-learning.sessions.review', compact(
            'tenant', 'session', 'activities', 'userResponses'
        ));
    }
}
