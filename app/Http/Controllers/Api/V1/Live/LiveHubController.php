<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Live;

use App\Http\Controllers\Api\V1\Live\Concerns\InteractsWithLiveQuizzes;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Live\LiveQuizResource;
use App\Http\Resources\Api\V1\Live\LiveSessionResource;
use App\Models\ActiveLearningSession;
use App\Models\QuizSession;
use App\Models\SectionStudent;
use App\Services\ActiveLearning\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LiveHubController extends Controller
{
    use InteractsWithLiveQuizzes;

    public function __construct(
        protected SessionService $sessionService,
    ) {}

    public function hub(Request $request): JsonResponse
    {
        $user = $request->user();

        $sectionIds = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('section_id');

        $sessions = fn () => ActiveLearningSession::whereHas('plan', fn ($q) => $q->whereHas('course', fn ($cq) => $cq->whereHas('sections', fn ($sq) => $sq->whereIn('sections.id', $sectionIds))))
            ->with(['plan' => fn ($q) => $q->withCount('activities'), 'plan.course'])
            ->withExists(['participants as joined' => fn ($q) => $q->where('user_id', $user->id)]);

        $quizzes = fn () => QuizSession::whereIn('section_id', $sectionIds)
            ->with([
                'section.course',
                'participants' => fn ($q) => $q->where('user_id', $user->id)->withCount('responses'),
            ])
            ->withCount(['sessionQuestions as question_count' => fn ($q) => $q->reorder()]);

        $activeSessions = $sessions()
            ->where('status', ActiveLearningSession::STATUS_ACTIVE)
            ->latest('started_at')
            ->get();

        $recentSessions = $sessions()
            ->where('status', ActiveLearningSession::STATUS_COMPLETED)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->latest('ended_at')
            ->limit(10)
            ->get();

        $liveQuizzes = $quizzes()
            ->where('category', 'live')
            ->whereIn('status', ['waiting', 'active', 'reviewing'])
            ->latest()
            ->get();

        $offlineQuizzes = $quizzes()
            ->where('category', 'offline')
            ->where('status', '!=', 'ended')
            ->whereNotNull('available_from')
            ->whereNotNull('available_until')
            ->where('available_from', '<=', now())
            ->where('available_until', '>=', now())
            ->latest()
            ->get();

        $recentQuizzes = $quizzes()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->where(fn ($q) => $q->where('status', 'ended')
                ->orWhere(fn ($oq) => $oq->where('category', 'offline')->where('available_until', '<', now())))
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $quizCards = fn ($collection) => $collection
            ->map(fn (QuizSession $quiz) => new LiveQuizResource($quiz, $quiz->participants->first()))
            ->values();

        $sessionCards = fn ($collection) => $collection
            ->map(fn (ActiveLearningSession $session) => new LiveSessionResource($session))
            ->values();

        return response()->json([
            'data' => [
                'active_sessions' => $sessionCards($activeSessions),
                'live_quizzes' => $quizCards($liveQuizzes),
                'offline_quizzes' => $quizCards($offlineQuizzes),
                'recent_sessions' => $sessionCards($recentSessions),
                'recent_quizzes' => $quizCards($recentQuizzes),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    public function join(Request $request): JsonResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $code = $request->validate(['code' => ['required', 'string', 'size:6']])['code'];

        $user = $request->user();

        if ($session = $this->sessionService->findByJoinCode($code)) {
            $this->sessionService->joinSession($session, $user);

            return response()->json([
                'message' => 'You joined the session.',
                'data' => [
                    'type' => 'session',
                    'id' => $session->id,
                    'title' => $session->plan?->title,
                    'category' => null,
                ],
            ]);
        }

        $quiz = QuizSession::where('join_code', $code)->first();

        if ($quiz && $quiz->isLive()) {
            $this->authorizeQuiz($quiz, $user);

            if ($quiz->isOffline() && ! $quiz->isOfflineOpen()) {
                abort(422, 'This quiz is not currently available.');
            }

            $this->registerParticipant($quiz, $user);

            return response()->json([
                'message' => 'You joined the quiz.',
                'data' => [
                    'type' => 'quiz',
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'category' => $quiz->category,
                ],
            ]);
        }

        throw ValidationException::withMessages([
            'code' => __('active_learning.invalid_join_code'),
        ]);
    }
}
