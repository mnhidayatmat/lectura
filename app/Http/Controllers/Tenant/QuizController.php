<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuizParticipant;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use App\Models\Section;
use App\Models\SectionStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizController extends Controller
{
    /**
     * Quiz list for lecturer.
     */
    public function index(): View
    {
        $user = auth()->user();
        $courseIds = Course::where('lecturer_id', $user->id)->pluck('id');
        $sectionIds = Section::whereIn('course_id', $courseIds)->pluck('id');

        $sessions = QuizSession::whereIn('section_id', $sectionIds)
            ->with(['section.course', 'participants'])
            ->latest()
            ->limit(50)
            ->get();

        $sections = Section::whereIn('course_id', $courseIds)->with('course')->where('is_active', true)->get();

        $liveSessions = $sessions->filter(fn($s) => $s->isLive());
        $pastSessions = $sessions->where('status', 'ended');

        return view('tenant.quizzes.index', compact('liveSessions', 'pastSessions', 'sections'));
    }

    /**
     * Create quiz session with questions.
     */
    public function create(Request $request): View
    {
        $user = auth()->user();
        $courseIds = Course::where('lecturer_id', $user->id)->pluck('id');
        $sections = Section::whereIn('course_id', $courseIds)->with('course')->where('is_active', true)->get();

        // Load question bank for this lecturer
        $bankQuestions = Question::where('created_by', $user->id)
            ->where('is_bank', true)
            ->with('options')
            ->latest()
            ->limit(100)
            ->get();

        return view('tenant.quizzes.create', compact('sections', 'bankQuestions'));
    }

    /**
     * Store quiz session + questions.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'section_id' => ['required', 'exists:sections,id'],
            'mode' => ['required', 'in:formative,participation,graded'],
            'is_anonymous' => ['nullable', 'boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.text' => ['required', 'string'],
            'questions.*.type' => ['required', 'in:mcq,true_false,short_answer'],
            'questions.*.time_limit' => ['nullable', 'integer', 'min:5', 'max:300'],
            'questions.*.points' => ['nullable', 'numeric', 'min:0'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*.text' => ['required_with:questions.*.options', 'string'],
            'questions.*.explanation' => ['nullable', 'string', 'max:1000'],
            'questions.*.options.*.is_correct' => ['nullable', 'boolean'],
        ]);

        $tenant = app('current_tenant');

        $session = QuizSession::create([
            'tenant_id' => $tenant->id,
            'section_id' => $request->section_id,
            'lecturer_id' => auth()->id(),
            'title' => $request->title,
            'mode' => $request->mode,
            'is_anonymous' => (bool) $request->is_anonymous,
            'status' => 'waiting',
        ]);

        foreach ($request->questions as $i => $qData) {
            $question = Question::create([
                'tenant_id' => $tenant->id,
                'created_by' => auth()->id(),
                'question_type' => $qData['type'],
                'text' => $qData['text'],
                'explanation' => $qData['explanation'] ?? null,
                'time_limit_seconds' => $qData['time_limit'] ?? 30,
                'points' => $qData['points'] ?? 1,
                'is_bank' => true,
            ]);

            // Create options for MCQ/TF
            if (in_array($qData['type'], ['mcq', 'true_false']) && ! empty($qData['options'])) {
                $labels = ['A', 'B', 'C', 'D', 'E', 'F'];
                foreach ($qData['options'] as $j => $opt) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'label' => $labels[$j] ?? chr(65 + $j),
                        'text' => $opt['text'],
                        'is_correct' => (bool) ($opt['is_correct'] ?? false),
                        'sort_order' => $j,
                    ]);
                }
            }

            QuizSessionQuestion::create([
                'quiz_session_id' => $session->id,
                'question_id' => $question->id,
                'sort_order' => $i,
            ]);
        }

        return redirect()->route('tenant.quizzes.control', [
            'tenant' => $tenant->slug,
            'session' => $session->id,
        ]);
    }

    /**
     * Edit quiz session.
     */
    public function edit(string $tenantSlug, QuizSession $session): View
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $user = auth()->user();
        $courseIds = Course::where('lecturer_id', $user->id)->pluck('id');
        $sections = Section::whereIn('course_id', $courseIds)->with('course')->where('is_active', true)->get();

        $session->load(['sessionQuestions.question.options', 'section.course']);

        return view('tenant.quizzes.edit', compact('session', 'sections'));
    }

    /**
     * Update quiz session + questions.
     */
    public function update(Request $request, string $tenantSlug, QuizSession $session): RedirectResponse
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'section_id' => ['required', 'exists:sections,id'],
            'mode' => ['required', 'in:formative,participation,graded'],
            'is_anonymous' => ['nullable', 'boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.text' => ['required', 'string'],
            'questions.*.type' => ['required', 'in:mcq,true_false,short_answer'],
            'questions.*.time_limit' => ['nullable', 'integer', 'min:5', 'max:300'],
            'questions.*.points' => ['nullable', 'numeric', 'min:0'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*.text' => ['required_with:questions.*.options', 'string'],
            'questions.*.explanation' => ['nullable', 'string', 'max:1000'],
            'questions.*.options.*.is_correct' => ['nullable', 'boolean'],
        ]);

        $tenant = app('current_tenant');

        $session->update([
            'title' => $request->title,
            'section_id' => $request->section_id,
            'mode' => $request->mode,
            'is_anonymous' => (bool) $request->is_anonymous,
        ]);

        // Delete old questions and session-question links
        $oldQuestionIds = $session->sessionQuestions()->pluck('question_id');
        $session->sessionQuestions()->delete();
        Question::whereIn('id', $oldQuestionIds)->each(function ($q) {
            $q->options()->delete();
            $q->delete();
        });

        // Re-create questions
        foreach ($request->questions as $i => $qData) {
            $question = Question::create([
                'tenant_id' => $tenant->id,
                'created_by' => auth()->id(),
                'question_type' => $qData['type'],
                'text' => $qData['text'],
                'explanation' => $qData['explanation'] ?? null,
                'time_limit_seconds' => $qData['time_limit'] ?? 30,
                'points' => $qData['points'] ?? 1,
                'is_bank' => true,
            ]);

            if (in_array($qData['type'], ['mcq', 'true_false']) && ! empty($qData['options'])) {
                $labels = ['A', 'B', 'C', 'D', 'E', 'F'];
                foreach ($qData['options'] as $j => $opt) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'label' => $labels[$j] ?? chr(65 + $j),
                        'text' => $opt['text'],
                        'is_correct' => (bool) ($opt['is_correct'] ?? false),
                        'sort_order' => $j,
                    ]);
                }
            }

            QuizSessionQuestion::create([
                'quiz_session_id' => $session->id,
                'question_id' => $question->id,
                'sort_order' => $i,
            ]);
        }

        $route = $session->status === 'ended' ? 'tenant.quizzes.results' : 'tenant.quizzes.control';

        return redirect()->route($route, [
            'tenant' => $tenant->slug,
            'session' => $session->id,
        ])->with('success', 'Quiz updated successfully.');
    }

    /**
     * Delete quiz session and associated data.
     */
    public function destroy(string $tenantSlug, QuizSession $session): RedirectResponse
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $tenant = app('current_tenant');

        // Delete responses, participants, session questions, questions, and options
        foreach ($session->sessionQuestions as $sq) {
            $sq->responses()->delete();
        }
        $session->participants()->delete();

        $questionIds = $session->sessionQuestions()->pluck('question_id');
        $session->sessionQuestions()->delete();

        Question::whereIn('id', $questionIds)->each(function ($q) {
            $q->options()->delete();
            $q->delete();
        });

        $session->delete();

        return redirect()->route('tenant.quizzes.index', $tenant->slug)
            ->with('success', 'Quiz deleted successfully.');
    }

    /**
     * Lecturer control panel — run the quiz live.
     */
    public function control(string $tenantSlug, QuizSession $session): View
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $session->load([
            'section.course',
            'sessionQuestions.question.options',
            'sessionQuestions.responses.participant',
            'participants.user',
        ]);

        return view('tenant.quizzes.control', compact('session'));
    }

    /**
     * API: Start the quiz (move from waiting to active, open first question).
     */
    public function start(string $tenantSlug, QuizSession $session): RedirectResponse
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $session->update(['status' => 'active', 'started_at' => now()]);

        $firstQ = $session->sessionQuestions()->orderBy('sort_order')->first();
        if ($firstQ) {
            $firstQ->update(['status' => 'active', 'opened_at' => now()]);
        }

        return back();
    }

    /**
     * API: Advance to next question.
     */
    public function nextQuestion(string $tenantSlug, QuizSession $session): RedirectResponse
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

        // Close current active question
        $current = $session->activeQuestion();
        if ($current) {
            $current->update(['status' => 'closed', 'closed_at' => now()]);
        }

        // Open next pending question
        $next = $session->sessionQuestions()->where('status', 'pending')->orderBy('sort_order')->first();
        if ($next) {
            $next->update(['status' => 'active', 'opened_at' => now()]);
        } else {
            // No more questions — move to reviewing
            $session->update(['status' => 'reviewing']);
        }

        return back();
    }

    /**
     * End quiz session.
     */
    public function end(string $tenantSlug, QuizSession $session): RedirectResponse
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

        // Close any active question
        $session->sessionQuestions()->where('status', 'active')->update(['status' => 'closed', 'closed_at' => now()]);

        // Calculate scores
        foreach ($session->participants as $participant) {
            $total = QuizResponse::where('quiz_participant_id', $participant->id)->sum('points_earned');
            $participant->update(['total_score' => $total]);
        }

        $session->update(['status' => 'ended', 'ended_at' => now()]);

        return redirect()->route('tenant.quizzes.results', [
            'tenant' => app('current_tenant')->slug,
            'session' => $session->id,
        ])->with('success', 'Quiz ended.');
    }

    /**
     * Results page.
     */
    public function results(string $tenantSlug, QuizSession $session): View
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $session->load([
            'section.course',
            'sessionQuestions.question.options',
            'sessionQuestions.responses.participant.user',
            'participants.user',
        ]);

        $leaderboard = $session->participants->sortByDesc('total_score')->values();

        return view('tenant.quizzes.results', compact('session', 'leaderboard'));
    }

    /**
     * API: Get quiz state (polled by lecturer control panel).
     */
    public function state(string $tenantSlug, QuizSession $session): JsonResponse
    {
        $session->load(['sessionQuestions.responses', 'participants']);

        $activeQ = $session->activeQuestion();
        $responses = $activeQ ? $activeQ->responses()->with('selectedOption')->get() : collect();

        // Build distribution for MCQ
        $distribution = [];
        if ($activeQ) {
            $question = $activeQ->question()->with('options')->first();
            foreach ($question->options as $opt) {
                $distribution[$opt->label] = $responses->where('selected_option_id', $opt->id)->count();
            }
        }

        return response()->json([
            'status' => $session->status,
            'participants' => $session->participants->count(),
            'responses' => $responses->count(),
            'distribution' => $distribution,
            'active_question_id' => $activeQ?->id,
        ]);
    }

    // ── Student Endpoints ──

    /**
     * Student join page.
     */
    public function join(Request $request): View|RedirectResponse
    {
        $code = $request->query('code');

        if ($code) {
            $session = QuizSession::where('join_code', strtoupper($code))->first();
            if ($session && $session->isLive()) {
                return redirect()->route('tenant.quizzes.play', [
                    'tenant' => app('current_tenant')->slug,
                    'session' => $session->id,
                ]);
            }
        }

        return view('tenant.quizzes.join');
    }

    /**
     * Student play — answer questions.
     */
    public function play(string $tenantSlug, QuizSession $session): View
    {
        if (! $session->isLive()) {
            return redirect()->route('tenant.quizzes.index', $tenantSlug)
                ->with('error', 'This quiz has ended.');
        }

        $user = auth()->user();

        // Auto-join as participant
        $participant = QuizParticipant::firstOrCreate(
            ['quiz_session_id' => $session->id, 'user_id' => $user->id],
            [
                'display_name' => $session->is_anonymous ? 'Player ' . rand(100, 999) : $user->name,
                'joined_at' => now(),
            ]
        );

        $session->load(['sessionQuestions.question.options']);

        return view('tenant.quizzes.play', compact('session', 'participant'));
    }

    /**
     * API: Student submits answer.
     */
    public function respond(Request $request, string $tenantSlug, QuizSession $session): JsonResponse
    {
        $request->validate([
            'session_question_id' => ['required', 'integer'],
            'selected_option_id' => ['nullable', 'integer'],
            'answer_text' => ['nullable', 'string'],
            'response_time_ms' => ['nullable', 'integer'],
        ]);

        $user = auth()->user();
        $participant = QuizParticipant::where('quiz_session_id', $session->id)
            ->where('user_id', $user->id)->first();

        if (! $participant) {
            return response()->json(['error' => 'Not joined.'], 403);
        }

        $sq = QuizSessionQuestion::where('id', $request->session_question_id)
            ->where('quiz_session_id', $session->id)
            ->where('status', 'active')
            ->first();

        if (! $sq) {
            return response()->json(['error' => 'Question is not active.'], 422);
        }

        // Check duplicate
        $existing = QuizResponse::where('quiz_session_question_id', $sq->id)
            ->where('quiz_participant_id', $participant->id)->first();

        if ($existing) {
            return response()->json(['message' => 'Already answered.', 'is_correct' => $existing->is_correct]);
        }

        // Score
        $isCorrect = false;
        $points = 0;
        $question = $sq->question;

        if ($request->selected_option_id) {
            $option = QuestionOption::find($request->selected_option_id);
            $isCorrect = $option && $option->is_correct;
            $points = $isCorrect ? (float) $question->points : 0;
        }

        $response = QuizResponse::create([
            'quiz_session_question_id' => $sq->id,
            'quiz_participant_id' => $participant->id,
            'selected_option_id' => $request->selected_option_id,
            'answer_text' => $request->answer_text,
            'is_correct' => $isCorrect,
            'points_earned' => $points,
            'response_time_ms' => $request->response_time_ms,
        ]);

        // Update participant total
        $participant->update([
            'total_score' => QuizResponse::where('quiz_participant_id', $participant->id)->sum('points_earned'),
        ]);

        return response()->json([
            'message' => 'Answer submitted.',
            'is_correct' => $isCorrect,
            'points_earned' => $points,
            'explanation' => $question->explanation,
        ]);
    }

    /**
     * API: Get current question state for student (polling).
     */
    public function studentState(string $tenantSlug, QuizSession $session): JsonResponse
    {
        $user = auth()->user();
        $participant = QuizParticipant::where('quiz_session_id', $session->id)
            ->where('user_id', $user->id)->first();

        $activeQ = $session->activeQuestion();
        $answered = false;

        if ($activeQ && $participant) {
            $answered = QuizResponse::where('quiz_session_question_id', $activeQ->id)
                ->where('quiz_participant_id', $participant->id)->exists();
        }

        $questionData = null;
        if ($activeQ) {
            $q = $activeQ->question()->with('options')->first();
            $questionData = [
                'session_question_id' => $activeQ->id,
                'text' => $q->text,
                'type' => $q->question_type,
                'time_limit' => $q->time_limit_seconds,
                'points' => $q->points,
                'options' => $q->options->map(fn($o) => [
                    'id' => $o->id,
                    'label' => $o->label,
                    'text' => $o->text,
                ])->toArray(),
            ];
        }

        return response()->json([
            'status' => $session->fresh()->status,
            'question' => $questionData,
            'answered' => $answered,
            'score' => $participant?->total_score ?? 0,
        ]);
    }
}
