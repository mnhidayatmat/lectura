<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Live;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActiveLearning\SubmitResponseRequest;
use App\Http\Resources\Api\V1\Live\ActivityResource;
use App\Http\Resources\Api\V1\Live\LiveSessionResource;
use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningGroup;
use App\Models\ActiveLearningResponse;
use App\Models\ActiveLearningSession;
use App\Models\SectionStudent;
use App\Models\User;
use App\Services\ActiveLearning\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ActiveSessionController extends Controller
{
    public function __construct(
        protected SessionService $sessionService,
    ) {}

    public function show(Request $request, ActiveLearningSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSession($session, $user);

        if ($session->isNotStarted()) {
            abort(404);
        }

        if ($session->isActive()) {
            $this->sessionService->joinSession($session, $user);
        }

        $session->load(['plan.course']);

        return response()->json([
            'data' => array_merge((new LiveSessionResource($session))->resolve($request), [
                'description' => $session->plan->description,
                'prerequisites' => $session->plan->prerequisites,
                'has_review' => $session->isCompleted(),
                'server_time' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function state(Request $request, ActiveLearningSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSession($session, $user);

        if ($session->isNotStarted()) {
            abort(404);
        }

        $state = $this->sessionService->getSessionState($session);
        $activity = $session->currentActivity;

        $state['current_activity'] = $activity && $state['current_activity']
            ? $this->currentActivity($session, $activity, $user, $state)
            : null;
        $state['started_at'] = $session->started_at?->toIso8601String();
        $state['ended_at'] = $session->ended_at?->toIso8601String();
        $state['responses_open'] = $session->isActive() && $activity !== null && ($activity->response_type ?? 'none') !== 'none';
        $state['server_time'] = now()->toIso8601String();

        return response()->json(['data' => $state]);
    }

    public function respond(SubmitResponseRequest $request, ActiveLearningSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSession($session, $user);

        if (! $session->isActive()) {
            abort(422, 'Session is not active.');
        }

        $activity = ActiveLearningActivity::findOrFail($request->validated('activity_id'));

        if ($session->current_activity_id !== $activity->id) {
            abort(422, 'This activity is not currently active.');
        }

        $data = $this->responseData($request, $activity, $user);

        $this->sessionService->joinSession($session, $user);
        $response = $this->sessionService->submitResponse($session, $activity, $user, $data);

        return response()->json([
            'message' => __('active_learning.response_submitted'),
            'data' => array_merge(['activity_id' => $activity->id], $this->responsePayload($response)),
        ]);
    }

    public function review(Request $request, ActiveLearningSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSession($session, $user);

        if ($session->isActive()) {
            abort(422, 'This session is still in progress.');
        }

        if (! $session->isCompleted()) {
            abort(404);
        }

        $session->load(['plan.course', 'plan.activities.pollOptions']);

        $responses = $session->responses()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('activity_id');

        $activities = $session->plan->activities->values()
            ->map(fn (ActiveLearningActivity $activity, int $i) => array_merge(
                (new ActivityResource($activity, $i + 1))->resolve($request),
                ['my_response' => ($response = $responses->get($activity->id)) ? $this->responsePayload($response) : null],
            ))
            ->all();

        return response()->json([
            'data' => array_merge((new LiveSessionResource($session))->resolve($request), [
                'responded_count' => $responses->count(),
                'activities' => $activities,
                'server_time' => now()->toIso8601String(),
            ]),
        ]);
    }

    private function authorizeSession(ActiveLearningSession $session, User $user): void
    {
        $courseId = $session->plan?->course_id;

        if (! $courseId) {
            abort(404);
        }

        $enrolled = SectionStudent::whereHas('section', fn ($q) => $q->where('course_id', $courseId))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $enrolled) {
            abort(403, 'You are not enrolled in this course.');
        }
    }

    private function currentActivity(ActiveLearningSession $session, ActiveLearningActivity $activity, User $user, array $state): array
    {
        $group = ActiveLearningGroup::where('active_learning_activity_id', $activity->id)
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->with('students')
            ->first();

        $myResponse = $session->responses()
            ->where('activity_id', $activity->id)
            ->where('user_id', $user->id)
            ->first();

        $groupResponse = null;

        if ($group && $activity->response_mode === 'group') {
            $groupResponse = $session->responses()
                ->where('activity_id', $activity->id)
                ->where('group_id', $group->id)
                ->where('user_id', '!=', $user->id)
                ->with('user:id,name')
                ->latest('submitted_at')
                ->first();
        }

        $startedAt = $session->updated_at;
        $remaining = $activity->duration_minutes && $startedAt
            ? max(0, (int) $activity->duration_minutes * 60 - (now()->getTimestamp() - $startedAt->getTimestamp()))
            : null;

        return array_merge((new ActivityResource($activity, $state['current_index'] ?: null))->resolve(), [
            'response_count' => $state['current_activity']['response_count'],
            'activity_started_at' => $startedAt?->toIso8601String(),
            'time_remaining_seconds' => $remaining,
            'my_group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'members' => $group->students
                    ->map(fn (User $member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'role' => $member->pivot->role,
                        'is_me' => $member->id === $user->id,
                    ])
                    ->values()
                    ->all(),
            ] : null,
            'my_response' => $myResponse ? $this->responsePayload($myResponse) : null,
            'group_response' => $groupResponse
                ? array_merge(['submitted_by' => $groupResponse->user?->name], $this->responsePayload($groupResponse))
                : null,
        ]);
    }

    private function responseData(SubmitResponseRequest $request, ActiveLearningActivity $activity, User $user): array
    {
        $type = $activity->response_type ?? 'none';

        if ($type === 'none') {
            abort(422, 'This activity does not accept responses.');
        }

        $groupId = $request->validated('group_id');

        if ($groupId) {
            $isMember = ActiveLearningGroup::where('id', $groupId)
                ->where('active_learning_activity_id', $activity->id)
                ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
                ->exists();

            if (! $isMember) {
                throw ValidationException::withMessages(['group_id' => 'You are not a member of this group.']);
            }
        }

        if ($type === 'mcq') {
            $selected = array_values(array_unique(array_map('intval', $request->validated('response_data.selected_options') ?? [])));

            if ($selected === []) {
                throw ValidationException::withMessages(['response_data.selected_options' => 'Please select an option.']);
            }

            if (array_diff($selected, $activity->pollOptions()->pluck('id')->all()) !== []) {
                throw ValidationException::withMessages(['response_data.selected_options' => 'The selected option is invalid.']);
            }

            if (count($selected) > 1 && ! ($activity->poll_config['multi_select'] ?? false)) {
                throw ValidationException::withMessages(['response_data.selected_options' => 'Please select only one option.']);
            }

            return ['response_data' => ['selected_options' => $selected], 'group_id' => $groupId];
        }

        $text = trim((string) $request->validated('response_data.text'));
        $maxLength = $type === 'reflection' ? 500 : 2000;

        if ($text === '') {
            throw ValidationException::withMessages(['response_data.text' => 'Please enter your response.']);
        }

        if (mb_strlen($text) > $maxLength) {
            throw ValidationException::withMessages(['response_data.text' => "Your response may not be longer than {$maxLength} characters."]);
        }

        return ['response_data' => ['text' => $text], 'group_id' => $groupId];
    }

    private function responsePayload(ActiveLearningResponse $response): array
    {
        return [
            'response_id' => $response->id,
            'response_type' => $response->response_type,
            'text' => $response->getTextContent(),
            'selected_option_ids' => array_map('intval', $response->getSelectedOptions()),
            'submitted_at' => $response->submitted_at?->toIso8601String(),
        ];
    }
}
