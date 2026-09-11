<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Live;

use App\Http\Controllers\Api\V1\Live\Concerns\InteractsWithLiveQuizzes;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Live\LiveQuizResource;
use App\Http\Resources\Api\V1\Live\QuizQuestionResource;
use App\Models\QuizParticipant;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizPlayController extends Controller
{
    use InteractsWithLiveQuizzes;

    private const STATE_CACHE_SECONDS = 2;

    public function show(Request $request, QuizSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeQuiz($session, $user);

        $playable = $this->isPlayable($session);
        $participant = $playable
            ? $this->registerParticipant($session, $user)
            : $this->findParticipant($session, $user);

        $session->load(['section.course', 'sessionQuestions.question.options']);

        $answeredCount = $participant
            ? QuizResponse::where('quiz_participant_id', $participant->id)->count()
            : 0;
        $participant?->setAttribute('responses_count', $answeredCount);

        $total = $session->sessionQuestions->count();
        $submitted = $total > 0 && $answeredCount >= $total;

        $questions = $session->isOffline() && $playable && ! $submitted
            ? $session->sessionQuestions->values()
                ->map(fn (QuizSessionQuestion $sq, int $i) => new QuizQuestionResource($sq, $i + 1, $total))
                ->all()
            : [];

        return response()->json([
            'data' => array_merge((new LiveQuizResource($session, $participant))->resolve($request), [
                'total_points' => $this->totalPoints($session),
                'playable' => $playable,
                'can_view_result' => $participant !== null && $this->resultAvailable($session, $answeredCount),
                'questions' => $questions,
                'server_time' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function state(Request $request, QuizSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeQuiz($session, $user);

        if ($session->isOffline()) {
            abort(422, 'This quiz is not a live quiz.');
        }

        $shared = Cache::remember(
            "api_quiz_session_{$session->id}_state",
            self::STATE_CACHE_SECONDS,
            fn () => $this->sharedState($session),
        );

        $participant = $this->findParticipant($session, $user);
        $phase = $shared['phase'];
        $question = $shared['question'];

        if ($question && $phase === 'answering' && $question['opened_at']) {
            $elapsed = now()->getTimestamp() - strtotime($question['opened_at']);
            $question['remaining_seconds'] = max(0, $question['time_limit'] - $elapsed);
        } elseif ($question) {
            $question['remaining_seconds'] = null;
        }

        $me = [
            'joined' => $participant !== null,
            'display_name' => $participant?->display_name,
            'score' => (float) ($participant?->total_score ?? 0),
            'answered' => false,
            'selected_option_id' => null,
            'answer_text' => null,
            'is_correct' => null,
            'points_earned' => null,
            'rank' => null,
        ];

        if ($participant && $question && in_array($phase, ['answering', 'reveal'], true)) {
            $response = QuizResponse::where('quiz_session_question_id', $question['session_question_id'])
                ->where('quiz_participant_id', $participant->id)
                ->first();

            $me['answered'] = $response !== null;
            $me['selected_option_id'] = $response?->selected_option_id;
            $me['answer_text'] = $response?->answer_text;

            if ($phase === 'reveal') {
                $me['is_correct'] = $response ? (bool) $response->is_correct : null;
                $me['points_earned'] = $response ? (float) $response->points_earned : null;
            }
        }

        if ($participant && in_array($phase, ['reveal', 'finished'], true)) {
            $me['rank'] = QuizParticipant::where('quiz_session_id', $session->id)
                ->where('total_score', '>', $participant->total_score)
                ->count() + 1;
        }

        $leaderboard = $shared['leaderboard'] === null ? null : array_map(function (array $entry) use ($participant) {
            $entry['is_me'] = $participant !== null && $entry['participant_id'] === $participant->id;
            unset($entry['participant_id']);

            return $entry;
        }, $shared['leaderboard']);

        return response()->json([
            'data' => [
                'server_time' => now()->toIso8601String(),
                'status' => $shared['status'],
                'phase' => $phase,
                'participant_count' => $shared['participant_count'],
                'question_total' => $shared['question_total'],
                'question' => $question,
                'me' => $me,
                'leaderboard' => $leaderboard,
            ],
        ]);
    }

    public function respond(Request $request, QuizSession $session): JsonResponse
    {
        $request->validate([
            'session_question_id' => ['required', 'integer'],
            'selected_option_id' => ['nullable', 'integer'],
            'answer_text' => ['nullable', 'string', 'max:2000'],
            'response_time_ms' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        $this->authorizeQuiz($session, $user);

        if ($session->isOffline()) {
            abort(422, 'This quiz is not a live quiz.');
        }

        $participant = $this->findParticipant($session, $user);

        if (! $participant) {
            abort(403, 'You have not joined this quiz.');
        }

        $sq = QuizSessionQuestion::where('id', $request->session_question_id)
            ->where('quiz_session_id', $session->id)
            ->where('status', 'active')
            ->with('question.options')
            ->first();

        if (! $sq) {
            abort(422, 'Question is not active.');
        }

        $existing = QuizResponse::where('quiz_session_question_id', $sq->id)
            ->where('quiz_participant_id', $participant->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Already answered.', 'data' => $this->answerData($existing)]);
        }

        $question = $sq->question;
        $option = null;
        $answerText = trim((string) $request->answer_text);

        if ($request->filled('selected_option_id')) {
            $option = $question->options->firstWhere('id', (int) $request->selected_option_id);

            if (! $option) {
                throw ValidationException::withMessages(['selected_option_id' => 'The selected option is invalid.']);
            }
        } elseif ($question->question_type === 'short_answer') {
            if ($answerText === '') {
                throw ValidationException::withMessages(['answer_text' => 'Please type your answer.']);
            }
        } else {
            throw ValidationException::withMessages(['selected_option_id' => 'Please choose an answer.']);
        }

        $isCorrect = $option !== null && $option->is_correct;
        $points = $isCorrect ? (float) $question->points : 0;

        try {
            $response = QuizResponse::create([
                'quiz_session_question_id' => $sq->id,
                'quiz_participant_id' => $participant->id,
                'selected_option_id' => $option?->id,
                'answer_text' => $answerText !== '' ? $answerText : null,
                'is_correct' => $isCorrect,
                'points_earned' => $points,
                'response_time_ms' => $request->response_time_ms,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = QuizResponse::where('quiz_session_question_id', $sq->id)
                ->where('quiz_participant_id', $participant->id)
                ->firstOrFail();

            return response()->json(['message' => 'Already answered.', 'data' => $this->answerData($existing)]);
        }

        if ($points > 0) {
            $participant->increment('total_score', $points);
        }

        return response()->json(['message' => 'Answer submitted.', 'data' => $this->answerData($response)]);
    }

    public function submitOffline(Request $request, QuizSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeQuiz($session, $user);

        if (! $session->isOffline() || ! $session->isOfflineOpen()) {
            abort(422, 'This quiz is not currently available.');
        }

        $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'integer'],
        ]);

        $participant = $this->findParticipant($session, $user);

        if (! $participant) {
            abort(403, 'You have not joined this quiz.');
        }

        if (QuizResponse::where('quiz_participant_id', $participant->id)->exists()) {
            abort(422, 'You have already submitted this quiz.');
        }

        $session->load('sessionQuestions.question.options');

        DB::transaction(function () use ($request, $session, $participant) {
            foreach ($session->sessionQuestions as $sq) {
                $selectedOptionId = $request->input("answers.{$sq->id}");
                $option = $selectedOptionId ? $sq->question->options->firstWhere('id', (int) $selectedOptionId) : null;
                $isCorrect = $option !== null && $option->is_correct;

                QuizResponse::create([
                    'quiz_session_question_id' => $sq->id,
                    'quiz_participant_id' => $participant->id,
                    'selected_option_id' => $option?->id,
                    'answer_text' => null,
                    'is_correct' => $isCorrect,
                    'points_earned' => $isCorrect ? (float) $sq->question->points : 0,
                    'response_time_ms' => null,
                ]);
            }

            $participant->update([
                'total_score' => QuizResponse::where('quiz_participant_id', $participant->id)->sum('points_earned'),
            ]);
        });

        return response()->json([
            'message' => 'Quiz submitted successfully!',
            'data' => $this->resultData($session, $participant->fresh()),
        ], 201);
    }

    public function result(Request $request, QuizSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeQuiz($session, $user);

        $participant = $this->findParticipant($session, $user);

        if (! $participant) {
            abort(404, 'You have not joined this quiz.');
        }

        $answeredCount = QuizResponse::where('quiz_participant_id', $participant->id)->count();

        if (! $this->resultAvailable($session, $answeredCount)) {
            abort(422, $session->isOffline()
                ? 'Submit the quiz to see your result.'
                : 'Results are available when the quiz ends.');
        }

        return response()->json(['data' => $this->resultData($session, $participant)]);
    }

    /**
     * Session-wide state shared by every student (cached briefly).
     */
    private function sharedState(QuizSession $session): array
    {
        $questions = $session->sessionQuestions()->with('question.options')->get();
        $active = $questions->firstWhere('status', 'active');
        $closed = null;

        $phase = match (true) {
            $session->status === 'waiting' => 'lobby',
            in_array($session->status, ['reviewing', 'ended'], true) => 'finished',
            $active !== null => 'answering',
            default => 'reveal',
        };

        if ($phase === 'reveal') {
            $closed = $questions->where('status', 'closed')->sortByDesc('closed_at')->first();

            if (! $closed) {
                $phase = 'lobby';
            }
        }

        $current = $phase === 'answering' ? $active : ($phase === 'reveal' ? $closed : null);
        $question = null;

        if ($current) {
            $position = $questions->search(fn (QuizSessionQuestion $q) => $q->id === $current->id) + 1;
            $question = (new QuizQuestionResource($current, $position, $questions->count(), $phase === 'reveal'))->resolve();
        }

        $leaderboard = null;

        if (in_array($phase, ['reveal', 'finished'], true)) {
            $leaderboard = QuizParticipant::where('quiz_session_id', $session->id)
                ->with('user:id,name')
                ->orderByDesc('total_score')
                ->take(10)
                ->get()
                ->values()
                ->map(fn (QuizParticipant $p, int $i) => [
                    'rank' => $i + 1,
                    'participant_id' => $p->id,
                    'name' => $session->is_anonymous ? $p->display_name : ($p->user?->name ?? $p->display_name),
                    'score' => (float) $p->total_score,
                ])
                ->all();
        }

        return [
            'status' => $session->status,
            'phase' => $phase,
            'participant_count' => QuizParticipant::where('quiz_session_id', $session->id)->count(),
            'question_total' => $questions->count(),
            'question' => $question,
            'leaderboard' => $leaderboard,
        ];
    }

    private function resultAvailable(QuizSession $session, int $answeredCount): bool
    {
        if ($session->isOffline()) {
            return $answeredCount > 0 || ! $session->isOfflineOpen();
        }

        return $session->status === 'ended'
            || ($session->status === 'reviewing' && ! $session->activeQuestion());
    }

    private function resultData(QuizSession $session, QuizParticipant $participant): array
    {
        $session->loadMissing(['section.course', 'sessionQuestions.question.options']);

        $responses = QuizResponse::where('quiz_participant_id', $participant->id)
            ->get()
            ->keyBy('quiz_session_question_id');
        $participant->setAttribute('responses_count', $responses->count());

        $total = $session->sessionQuestions->count();
        $correctCount = $responses->where('is_correct', true)->count();

        $questions = $session->sessionQuestions->values()->map(function (QuizSessionQuestion $sq, int $i) use ($responses, $total) {
            $response = $responses->get($sq->id);

            return array_merge((new QuizQuestionResource($sq, $i + 1, $total, true))->resolve(), [
                'answered' => $response !== null && ($response->selected_option_id !== null || $response->answer_text !== null),
                'selected_option_id' => $response?->selected_option_id,
                'answer_text' => $response?->answer_text,
                'is_correct' => (bool) $response?->is_correct,
                'points_earned' => (float) ($response?->points_earned ?? 0),
            ]);
        })->all();

        return [
            'quiz' => (new LiveQuizResource($session, $participant))->resolve(),
            'score' => (float) $participant->total_score,
            'max_score' => $this->totalPoints($session),
            'correct_count' => $correctCount,
            'question_count' => $total,
            'accuracy' => $total > 0 ? (int) round($correctCount / $total * 100) : 0,
            'rank' => QuizParticipant::where('quiz_session_id', $session->id)
                ->where('total_score', '>', $participant->total_score)
                ->count() + 1,
            'participant_count' => QuizParticipant::where('quiz_session_id', $session->id)->count(),
            'questions' => $questions,
        ];
    }

    private function answerData(QuizResponse $response): array
    {
        return [
            'session_question_id' => $response->quiz_session_question_id,
            'answered' => true,
            'selected_option_id' => $response->selected_option_id,
            'answer_text' => $response->answer_text,
        ];
    }

    private function totalPoints(QuizSession $session): float
    {
        $session->loadMissing('sessionQuestions.question');

        return (float) $session->sessionQuestions->sum(fn (QuizSessionQuestion $sq) => (float) $sq->question->points);
    }
}
