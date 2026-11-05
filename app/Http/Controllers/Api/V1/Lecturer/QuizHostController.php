<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Models\QuizSession;
use App\Services\Quiz\LiveQuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Run a live quiz from the phone: the same game as the web board (Tenant\QuizController),
 * so the phone can drive a class on its own or act as a remote while the board is projected.
 * Quizzes are still written on the web.
 */
class QuizHostController extends Controller
{
    use AuthorizesLecturerAccess;

    public function __construct(private LiveQuizService $game) {}

    /**
     * Live quizzes in my sections, running ones first.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureLecturer();

        $request->validate(['course_id' => ['nullable', 'integer']]);

        $quizzes = QuizSession::where('category', 'live')
            ->where(fn ($q) => $q
                ->whereIn('section_id', $this->allAccessibleSectionIds())
                ->orWhere('lecturer_id', auth()->id()))
            ->when($request->filled('course_id'), fn ($q) => $q->whereHas(
                'section',
                fn ($s) => $s->where('course_id', $request->integer('course_id'))
            ))
            ->with('section.course')
            ->withCount(['participants', 'sessionQuestions'])
            ->get()
            ->sortBy(fn (QuizSession $quiz) => [
                $quiz->isLive() ? 0 : 1,
                $quiz->section?->course?->code ?? '',
                $quiz->sort_order ?? PHP_INT_MAX,
                -$quiz->id,
            ])
            ->values();

        return response()->json([
            'data' => $quizzes->map(fn (QuizSession $quiz) => $this->summary($quiz))->values(),
        ]);
    }

    /**
     * The quiz and its board state (polled while the host screen is open).
     */
    public function show(QuizSession $quiz): JsonResponse
    {
        $this->authorizeView($quiz);

        return response()->json(['data' => $this->payload($quiz)]);
    }

    public function start(QuizSession $quiz): JsonResponse
    {
        $this->authorizeRun($quiz);

        if ($quiz->status !== 'waiting') {
            abort(422, 'This quiz has already started.');
        }

        $this->game->start($quiz);

        return $this->action($quiz, 'Quiz started.');
    }

    /**
     * Close the open question. With `session_question_id` only that question is closed, so a
     * close sent late (the board already moved on) never closes the next one.
     */
    public function close(Request $request, QuizSession $quiz): JsonResponse
    {
        $this->authorizeRun($quiz);

        $request->validate(['session_question_id' => ['nullable', 'integer']]);

        $active = $quiz->activeQuestion();
        $expected = $request->input('session_question_id');

        if ($active && ($expected === null || (int) $expected === $active->id)) {
            $this->game->closeActive($quiz);
        }

        return $this->action($quiz, 'Question closed.');
    }

    /**
     * Open the next question, or move to the final standings after the last one.
     */
    public function next(QuizSession $quiz): JsonResponse
    {
        $this->authorizeRun($quiz);

        if ($quiz->status !== 'active') {
            abort(422, 'This quiz is not running.');
        }

        if ($quiz->activeQuestion()) {
            abort(422, 'Close the open question first.');
        }

        $opened = $this->game->advance($quiz);

        return $this->action($quiz, $opened ? 'Next question opened.' : 'Final standings.');
    }

    public function end(QuizSession $quiz): JsonResponse
    {
        $this->authorizeRun($quiz);

        if ($quiz->status !== 'ended') {
            $this->game->finish($quiz);
        }

        return $this->action($quiz, 'Quiz ended and saved.');
    }

    /**
     * A fresh run with the same questions, ready in the lobby. The original keeps its results.
     */
    public function replay(QuizSession $quiz): JsonResponse
    {
        $this->authorizeOwner($quiz);
        $this->ensureLiveCategory($quiz);

        $copy = $quiz->copyForNewRun(auth()->user());

        return response()->json([
            'message' => 'A new run is ready. Students join with code '.$copy->join_code.'.',
            'data' => $this->payload($copy),
        ], 201);
    }

    // ── Helpers ──

    protected function action(QuizSession $quiz, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => $this->payload($quiz)]);
    }

    protected function payload(QuizSession $quiz): array
    {
        $quiz->loadMissing('section.course')->loadCount(['participants', 'sessionQuestions']);

        return array_merge($this->summary($quiz), ['board' => $this->game->boardState($quiz)]);
    }

    protected function summary(QuizSession $quiz): array
    {
        $slug = app('current_tenant')->slug;
        $section = $quiz->section;
        $course = $section?->course;
        $isOwner = $quiz->lecturer_id === auth()->id();

        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'status' => $quiz->status,
            'join_code' => $quiz->join_code,
            'is_anonymous' => (bool) $quiz->is_anonymous,
            'question_count' => (int) $quiz->session_questions_count,
            'participant_count' => (int) $quiz->participants_count,
            'never_run' => $quiz->hasNeverRun(),
            'is_owner' => $isOwner,
            'can_run' => $isOwner && $quiz->isLive(),
            'course' => $course ? ['id' => $course->id, 'code' => $course->code, 'title' => $course->title] : null,
            'section' => $section ? ['id' => $section->id, 'name' => $section->name] : null,
            'started_at' => $quiz->started_at?->toIso8601String(),
            'ended_at' => $quiz->ended_at?->toIso8601String(),
            'created_at' => $quiz->created_at?->toIso8601String(),
            'join_url' => route('tenant.quizzes.join', [$slug, 'code' => $quiz->join_code]),
            'board_url' => route('tenant.quizzes.control', [$slug, $quiz->id]),
            'results_url' => route('tenant.quizzes.results', [$slug, $quiz->id]),
        ];
    }

    /**
     * Same as the web's results/state access: the quiz's lecturer, or anyone with access to its course.
     */
    protected function authorizeView(QuizSession $quiz): void
    {
        $this->ensureLecturer();
        $this->ensureLiveCategory($quiz);

        if ($quiz->lecturer_id === auth()->id()) {
            return;
        }

        $course = $quiz->section?->course;
        if (! $course) {
            abort(403, 'You do not have access to this quiz.');
        }

        $this->authorizeCourse($course);
    }

    /**
     * Running a quiz is owner-only, as on the web.
     */
    protected function authorizeRun(QuizSession $quiz): void
    {
        $this->authorizeOwner($quiz);
        $this->ensureLiveCategory($quiz);

        if ($quiz->status === 'ended') {
            abort(422, 'This quiz has ended. Run it again to start a new round.');
        }
    }

    protected function authorizeOwner(QuizSession $quiz): void
    {
        $this->ensureLecturer();

        if ($quiz->lecturer_id !== auth()->id()) {
            abort(403, 'Only the lecturer who created this quiz can run it.');
        }
    }

    protected function ensureLiveCategory(QuizSession $quiz): void
    {
        if ($quiz->isOffline()) {
            abort(422, 'Offline quizzes run on their own. Open them on the web.');
        }
    }
}
