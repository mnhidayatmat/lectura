<?php

declare(strict_types=1);

namespace App\Services\ActiveLearning;

use App\Events\ActiveLearning\ActivityAdvanced;
use App\Events\ActiveLearning\ResponseSubmitted;
use App\Events\ActiveLearning\SessionEnded;
use App\Events\ActiveLearning\SessionStarted;
use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPlan;
use App\Models\ActiveLearningResponse;
use App\Models\ActiveLearningSession;
use App\Models\ActiveLearningSessionParticipant;
use App\Models\SectionStudent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SessionService
{
    /**
     * Start a new live session from a published plan.
     */
    public function startSession(ActiveLearningPlan $plan, User $lecturer): ActiveLearningSession
    {
        // Only published plans can be started
        if (! $plan->isPublished()) {
            abort(422, 'Plan must be published before starting a session.');
        }

        // Only one active session per plan
        if ($plan->activeSession()) {
            abort(422, 'This plan already has an active session.');
        }

        $firstActivity = $plan->activities()->orderBy('sort_order')->first();

        $session = ActiveLearningSession::create([
            'tenant_id' => $plan->tenant_id,
            'plan_id' => $plan->id,
            'status' => ActiveLearningSession::STATUS_ACTIVE,
            'current_activity_id' => $firstActivity?->id,
            'started_at' => now(),
            'created_by' => $lecturer->id,
        ]);

        broadcast(new SessionStarted($session))->toOthers();

        return $session;
    }

    /**
     * Advance to the next activity in the session.
     */
    public function advanceActivity(ActiveLearningSession $session): ActiveLearningSession
    {
        if (! $session->isActive()) {
            abort(422, 'Session is not active.');
        }

        $activities = $session->plan->activities()->orderBy('sort_order')->get();
        $currentIndex = $activities->search(fn ($a) => $a->id === $session->current_activity_id);

        $nextActivity = $activities->get($currentIndex + 1);

        if (! $nextActivity) {
            // No more activities — end the session
            return $this->endSession($session);
        }

        $session->update(['current_activity_id' => $nextActivity->id]);
        $session->refresh();

        broadcast(new ActivityAdvanced($session, $nextActivity))->toOthers();

        return $session;
    }

    /**
     * End the session and calculate summary.
     */
    public function endSession(ActiveLearningSession $session): ActiveLearningSession
    {
        if (! $session->isActive()) {
            abort(422, 'Session is not active.');
        }

        $summary = $this->calculateSummary($session);

        $session->update([
            'status' => ActiveLearningSession::STATUS_COMPLETED,
            'ended_at' => now(),
            'current_activity_id' => null,
            'summary_data' => $summary,
        ]);

        $session->refresh();

        broadcast(new SessionEnded($session))->toOthers();

        return $session;
    }

    /**
     * Join a student to the session.
     */
    public function joinSession(ActiveLearningSession $session, User $student): ActiveLearningSessionParticipant
    {
        if (! $session->isActive()) {
            abort(422, 'Session is not active.');
        }

        // Verify student is enrolled in the course
        if (! $this->isEnrolledInCourse($student, $session->plan->course_id)) {
            abort(403, 'You are not enrolled in this course.');
        }

        return ActiveLearningSessionParticipant::firstOrCreate(
            ['session_id' => $session->id, 'user_id' => $student->id],
            ['joined_at' => now()]
        );
    }

    /**
     * Submit or update a student's response for the current activity.
     */
    public function submitResponse(
        ActiveLearningSession $session,
        ActiveLearningActivity $activity,
        User $student,
        array $data,
    ): ActiveLearningResponse {
        if (! $session->isActive()) {
            abort(422, 'Session is not active.');
        }

        // Only allow responses to the current activity
        if ($session->current_activity_id !== $activity->id) {
            abort(422, 'This activity is not currently active.');
        }

        // Check if group response and already submitted by another member
        $groupId = $data['group_id'] ?? null;
        if ($activity->response_mode === 'group' && $groupId) {
            $existingGroupResponse = ActiveLearningResponse::where('session_id', $session->id)
                ->where('activity_id', $activity->id)
                ->where('group_id', $groupId)
                ->where('user_id', '!=', $student->id)
                ->exists();

            if ($existingGroupResponse) {
                abort(422, 'A group member has already submitted a response.');
            }
        }

        $response = ActiveLearningResponse::updateOrCreate(
            [
                'session_id' => $session->id,
                'activity_id' => $activity->id,
                'user_id' => $student->id,
            ],
            [
                'tenant_id' => $session->tenant_id,
                'group_id' => $groupId,
                'response_type' => $activity->response_type ?? 'text',
                'response_data' => $data['response_data'],
                'submitted_at' => now(),
            ]
        );

        // Broadcast updated count to lecturer
        $responseCount = $session->responseCountForActivity($activity->id);
        $participantCount = $session->participantCount();

        broadcast(new ResponseSubmitted($session, $activity->id, $responseCount, $participantCount))->toOthers();

        return $response;
    }

    /**
     * Get the current state of the session (for polling fallback).
     */
    public function getSessionState(ActiveLearningSession $session): array
    {
        $activity = $session->currentActivity;
        $activities = $session->plan->activities()->orderBy('sort_order')->get();
        $currentIndex = $activity ? $activities->search(fn ($a) => $a->id === $activity->id) : -1;

        return [
            'session_id' => $session->id,
            'status' => $session->status,
            'participant_count' => $session->participantCount(),
            'current_activity' => $activity ? [
                'id' => $activity->id,
                'title' => $activity->title,
                'type' => $activity->type,
                'instructions' => $activity->instructions,
                'description' => $activity->description,
                'duration_minutes' => $activity->duration_minutes,
                'response_mode' => $activity->response_mode,
                'response_type' => $activity->response_type,
                'poll_options' => $activity->response_type === 'mcq'
                    ? $activity->pollOptions->map(fn ($o) => ['id' => $o->id, 'label' => $o->label])->values()
                    : [],
                'response_count' => $session->responseCountForActivity($activity->id),
                'sort_order' => $activity->sort_order,
            ] : null,
            'total_activities' => $activities->count(),
            'current_index' => $currentIndex !== false ? $currentIndex + 1 : 0,
            'started_at' => $session->started_at?->toISOString(),
            'ended_at' => $session->ended_at?->toISOString(),
        ];
    }

    /**
     * Get the detailed state for the lecturer dashboard (includes response data).
     */
    public function getLecturerState(ActiveLearningSession $session): array
    {
        $state = $this->getSessionState($session);

        if ($state['current_activity'] && $session->currentActivity) {
            $activity = $session->currentActivity;

            // For MCQ, include response distribution
            if ($activity->response_type === 'mcq') {
                $responses = $session->responses()->where('activity_id', $activity->id)->get();
                $distribution = [];
                foreach ($activity->pollOptions as $option) {
                    $count = $responses->filter(fn ($r) => in_array($option->id, $r->getSelectedOptions()))->count();
                    $distribution[] = ['option_id' => $option->id, 'label' => $option->label, 'count' => $count];
                }
                $state['current_activity']['poll_distribution'] = $distribution;
            }

            // For text, include recent responses
            if (in_array($activity->response_type, ['text', 'reflection'])) {
                $state['current_activity']['recent_responses'] = $session->responses()
                    ->where('activity_id', $activity->id)
                    ->with('user:id,name')
                    ->latest('submitted_at')
                    ->limit(20)
                    ->get()
                    ->map(fn ($r) => [
                        'user_name' => $r->user->name,
                        'text' => $r->getTextContent(),
                        'submitted_at' => $r->submitted_at->toISOString(),
                    ]);
            }
        }

        return $state;
    }

    /**
     * Calculate session summary statistics.
     */
    protected function calculateSummary(ActiveLearningSession $session): array
    {
        $participants = $session->participants()->count();
        $activities = $session->plan->activities()->get();
        $allResponses = $session->responses()->get();

        $perActivity = [];
        foreach ($activities as $activity) {
            $activityResponses = $allResponses->where('activity_id', $activity->id);
            $perActivity[] = [
                'activity_id' => $activity->id,
                'title' => $activity->title,
                'type' => $activity->type,
                'response_type' => $activity->response_type,
                'response_count' => $activityResponses->count(),
                'response_rate' => $participants > 0
                    ? round($activityResponses->count() / $participants * 100)
                    : 0,
            ];
        }

        return [
            'participant_count' => $participants,
            'total_activities' => $activities->count(),
            'total_responses' => $allResponses->count(),
            'overall_response_rate' => $activities->count() > 0 && $participants > 0
                ? round($allResponses->count() / ($activities->count() * $participants) * 100)
                : 0,
            'duration_minutes' => $session->started_at && $session->ended_at
                ? (int) $session->started_at->diffInMinutes($session->ended_at)
                : 0,
            'per_activity' => $perActivity,
        ];
    }

    /**
     * Check if a student is enrolled in a course via section_students.
     */
    protected function isEnrolledInCourse(User $student, int $courseId): bool
    {
        return SectionStudent::whereHas('section', fn ($q) => $q->where('course_id', $courseId))
            ->where('user_id', $student->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Find an active session by join code.
     */
    public function findByJoinCode(string $code): ?ActiveLearningSession
    {
        return ActiveLearningSession::where('join_code', strtoupper($code))
            ->where('status', ActiveLearningSession::STATUS_ACTIVE)
            ->first();
    }
}
