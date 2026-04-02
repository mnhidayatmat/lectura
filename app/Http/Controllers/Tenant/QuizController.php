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
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class QuizController extends Controller
{
    /**
     * Quiz list for lecturer — grouped by course.
     */
    public function index(): View
    {
        $user = auth()->user();
        $courseIds = Course::where('lecturer_id', $user->id)->pluck('id');
        $sectionIds = Section::whereIn('course_id', $courseIds)->pluck('id');

        $sessions = QuizSession::whereIn('section_id', $sectionIds)
            ->with(['section.course', 'participants'])
            ->latest()
            ->limit(100)
            ->get();

        $courses = Course::whereIn('id', $courseIds)
            ->whereHas('sections', fn ($q) => $q->where('is_active', true))
            ->orderBy('code')
            ->get();

        // Group sessions by course
        $sessionsByCourse = $sessions->groupBy(fn ($s) => $s->section->course_id);

        return view('tenant.quizzes.index', compact('courses', 'sessionsByCourse'));
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
            'category' => ['required', 'in:live,offline'],
            'mode' => ['required', 'in:formative,participation,graded'],
            'is_anonymous' => ['nullable', 'boolean'],
            'available_from' => ['required_if:category,offline', 'nullable', 'date'],
            'available_until' => ['required_if:category,offline', 'nullable', 'date', 'after:available_from'],
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
        $isOffline = $request->category === 'offline';

        $session = QuizSession::create([
            'tenant_id' => $tenant->id,
            'section_id' => $request->section_id,
            'lecturer_id' => auth()->id(),
            'title' => $request->title,
            'category' => $request->category,
            'mode' => $request->mode,
            'is_anonymous' => (bool) $request->is_anonymous,
            'status' => $isOffline ? 'active' : 'waiting',
            'available_from' => $isOffline ? $request->available_from : null,
            'available_until' => $isOffline ? $request->available_until : null,
            'started_at' => $isOffline ? now() : null,
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
                'status' => $isOffline ? 'active' : 'pending',
                'opened_at' => $isOffline ? now() : null,
            ]);
        }

        if ($isOffline) {
            return redirect()->route('tenant.quizzes.index', $tenant->slug)
                ->with('success', 'Offline quiz created. Students can access it from '.$session->available_from->format('d M Y H:i').'.');
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

        $existingQuestions = $session->sessionQuestions->map(function ($sq) {
            return [
                'type' => $sq->question->question_type,
                'text' => $sq->question->text,
                'time_limit' => $sq->question->time_limit_seconds,
                'points' => $sq->question->points,
                'explanation' => $sq->question->explanation ?? '',
                'options' => $sq->question->options->map(function ($o) {
                    return [
                        'label' => $o->label,
                        'text' => $o->text,
                        'is_correct' => (bool) $o->is_correct,
                    ];
                })->toArray(),
            ];
        })->toArray();

        return view('tenant.quizzes.edit', compact('session', 'sections', 'existingQuestions'));
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
            'category' => ['required', 'in:live,offline'],
            'mode' => ['required', 'in:formative,participation,graded'],
            'is_anonymous' => ['nullable', 'boolean'],
            'available_from' => ['required_if:category,offline', 'nullable', 'date'],
            'available_until' => ['required_if:category,offline', 'nullable', 'date', 'after:available_from'],
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
        $isOffline = $request->category === 'offline';

        $session->update([
            'title' => $request->title,
            'section_id' => $request->section_id,
            'category' => $request->category,
            'mode' => $request->mode,
            'is_anonymous' => (bool) $request->is_anonymous,
            'available_from' => $isOffline ? $request->available_from : null,
            'available_until' => $isOffline ? $request->available_until : null,
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
                'status' => $isOffline ? 'active' : 'pending',
                'opened_at' => $isOffline ? now() : null,
            ]);
        }

        if ($isOffline) {
            return redirect()->route('tenant.quizzes.index', $tenant->slug)
                ->with('success', 'Offline quiz updated successfully.');
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

        Cache::forget("quiz_session_{$session->id}_state");

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

        Cache::forget("quiz_session_{$session->id}_state");

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

        Cache::forget("quiz_session_{$session->id}_state");

        return redirect()->route('tenant.quizzes.results', [
            'tenant' => app('current_tenant')->slug,
            'session' => $session->id,
        ])->with('success', 'Quiz ended.');
    }

    /**
     * Replay a quiz — create a new session with the same questions.
     */
    public function replay(string $tenantSlug, QuizSession $session): RedirectResponse
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $session->load('sessionQuestions.question.options');

        $newSession = QuizSession::create([
            'tenant_id' => $tenant->id,
            'section_id' => $session->section_id,
            'lecturer_id' => auth()->id(),
            'title' => $session->title,
            'category' => $session->category,
            'mode' => $session->mode,
            'is_anonymous' => $session->is_anonymous,
            'status' => $session->isOffline() ? 'active' : 'waiting',
            'available_from' => $session->available_from,
            'available_until' => $session->available_until,
            'started_at' => $session->isOffline() ? now() : null,
        ]);

        foreach ($session->sessionQuestions as $sq) {
            $oldQ = $sq->question;

            $newQ = Question::create([
                'tenant_id' => $tenant->id,
                'created_by' => auth()->id(),
                'question_type' => $oldQ->question_type,
                'text' => $oldQ->text,
                'explanation' => $oldQ->explanation,
                'time_limit_seconds' => $oldQ->time_limit_seconds,
                'points' => $oldQ->points,
                'is_bank' => true,
            ]);

            foreach ($oldQ->options as $opt) {
                QuestionOption::create([
                    'question_id' => $newQ->id,
                    'label' => $opt->label,
                    'text' => $opt->text,
                    'is_correct' => $opt->is_correct,
                    'sort_order' => $opt->sort_order,
                ]);
            }

            QuizSessionQuestion::create([
                'quiz_session_id' => $newSession->id,
                'question_id' => $newQ->id,
                'sort_order' => $sq->sort_order,
                'status' => $session->isOffline() ? 'active' : 'pending',
                'opened_at' => $session->isOffline() ? now() : null,
            ]);
        }

        if ($newSession->isOffline()) {
            return redirect()->route('tenant.quizzes.index', $tenant->slug)
                ->with('success', 'Offline quiz replayed — new session created.');
        }

        return redirect()->route('tenant.quizzes.control', [
            'tenant' => $tenant->slug,
            'session' => $newSession->id,
        ])->with('success', 'Quiz replayed — new session created with the same questions.');
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
        $activeQ = $session->activeQuestion();

        $responseCount = 0;
        $distribution = [];

        if ($activeQ) {
            $responseCount = QuizResponse::where('quiz_session_question_id', $activeQ->id)->count();

            // SQL GROUP BY instead of loading all rows into memory
            $countsByOption = QuizResponse::where('quiz_session_question_id', $activeQ->id)
                ->whereNotNull('selected_option_id')
                ->selectRaw('selected_option_id, COUNT(*) as cnt')
                ->groupBy('selected_option_id')
                ->pluck('cnt', 'selected_option_id');

            $question = $activeQ->question()->with('options')->first();
            foreach ($question->options as $opt) {
                $distribution[$opt->label] = $countsByOption[$opt->id] ?? 0;
            }
        }

        $participantCount = QuizParticipant::where('quiz_session_id', $session->id)->count();

        return response()->json([
            'status' => $session->status,
            'participants' => $participantCount,
            'responses' => $responseCount,
            'distribution' => $distribution,
            'active_question_id' => $activeQ?->id,
        ]);
    }

    // ── Student Endpoints ──

    /**
     * Student join page (for live quizzes).
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
     * Student play — answer questions (live or offline).
     */
    public function play(string $tenantSlug, QuizSession $session): View|RedirectResponse
    {
        $user = auth()->user();

        // Offline quiz — check availability window and section enrollment
        if ($session->isOffline()) {
            if (! $session->isOfflineOpen()) {
                return redirect()->route('tenant.quizzes.index', $tenantSlug)
                    ->with('error', 'This quiz is not currently available.');
            }

            // Verify student is enrolled in the section
            $enrolled = SectionStudent::where('section_id', $session->section_id)
                ->where('student_id', $user->id)
                ->exists();

            if (! $enrolled && $session->lecturer_id !== $user->id) {
                return redirect()->route('tenant.quizzes.index', $tenantSlug)
                    ->with('error', 'You are not enrolled in this section.');
            }

            // Auto-join as participant
            $participant = QuizParticipant::firstOrCreate(
                ['quiz_session_id' => $session->id, 'user_id' => $user->id],
                [
                    'display_name' => $session->is_anonymous ? 'Player '.rand(100, 999) : $user->name,
                    'joined_at' => now(),
                ]
            );

            // Check if already submitted
            $answeredCount = QuizResponse::where('quiz_participant_id', $participant->id)->count();
            $totalQuestions = $session->sessionQuestions()->count();
            if ($answeredCount >= $totalQuestions && $totalQuestions > 0) {
                return redirect()->route('tenant.quizzes.offlineResult', [
                    'tenant' => $tenantSlug,
                    'session' => $session->id,
                ])->with('info', 'You have already completed this quiz.');
            }

            $session->load(['sessionQuestions.question.options']);

            return view('tenant.quizzes.play-offline', compact('session', 'participant'));
        }

        // Live quiz
        if (! $session->isLive()) {
            return redirect()->route('tenant.quizzes.index', $tenantSlug)
                ->with('error', 'This quiz has ended.');
        }

        // Auto-join as participant
        $participant = QuizParticipant::firstOrCreate(
            ['quiz_session_id' => $session->id, 'user_id' => $user->id],
            [
                'display_name' => $session->is_anonymous ? 'Player '.rand(100, 999) : $user->name,
                'joined_at' => now(),
            ]
        );

        $session->load(['sessionQuestions.question.options']);

        return view('tenant.quizzes.play', compact('session', 'participant'));
    }

    /**
     * API: Student submits answer (live quiz — single question).
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
            return response()->json(['message' => 'Already answered.']);
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

        // Increment score in-place — avoids a SUM recalculation across all responses
        if ($points > 0) {
            $participant->increment('total_score', $points);
        }

        return response()->json([
            'message' => 'Answer submitted.',
        ]);
    }

    /**
     * Submit all answers for an offline quiz.
     */
    public function submitOffline(Request $request, string $tenantSlug, QuizSession $session): RedirectResponse
    {
        if (! $session->isOffline() || ! $session->isOfflineOpen()) {
            return redirect()->route('tenant.quizzes.index', $tenantSlug)
                ->with('error', 'This quiz is not currently available.');
        }

        $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'integer'],
        ]);

        $user = auth()->user();

        $participant = QuizParticipant::where('quiz_session_id', $session->id)
            ->where('user_id', $user->id)->first();

        if (! $participant) {
            return redirect()->back()->with('error', 'You have not joined this quiz.');
        }

        // Check if already submitted
        $existingCount = QuizResponse::where('quiz_participant_id', $participant->id)->count();
        if ($existingCount > 0) {
            return redirect()->route('tenant.quizzes.offlineResult', [
                'tenant' => $tenantSlug,
                'session' => $session->id,
            ])->with('info', 'You have already submitted this quiz.');
        }

        $session->load('sessionQuestions.question');

        foreach ($session->sessionQuestions as $sq) {
            $selectedOptionId = $request->input("answers.{$sq->id}");

            $isCorrect = false;
            $points = 0;

            if ($selectedOptionId) {
                $option = QuestionOption::find($selectedOptionId);
                $isCorrect = $option && $option->is_correct;
                $points = $isCorrect ? (float) $sq->question->points : 0;
            }

            QuizResponse::create([
                'quiz_session_question_id' => $sq->id,
                'quiz_participant_id' => $participant->id,
                'selected_option_id' => $selectedOptionId,
                'answer_text' => null,
                'is_correct' => $isCorrect,
                'points_earned' => $points,
                'response_time_ms' => null,
            ]);
        }

        // Update participant total
        $participant->update([
            'total_score' => QuizResponse::where('quiz_participant_id', $participant->id)->sum('points_earned'),
        ]);

        return redirect()->route('tenant.quizzes.offlineResult', [
            'tenant' => $tenantSlug,
            'session' => $session->id,
        ])->with('success', 'Quiz submitted successfully!');
    }

    /**
     * Show student's offline quiz result.
     */
    public function offlineResult(string $tenantSlug, QuizSession $session): View
    {
        $user = auth()->user();

        $participant = QuizParticipant::where('quiz_session_id', $session->id)
            ->where('user_id', $user->id)->firstOrFail();

        $session->load(['sessionQuestions.question.options', 'section.course']);

        $responses = QuizResponse::where('quiz_participant_id', $participant->id)
            ->get()
            ->keyBy('quiz_session_question_id');

        $maxScore = $session->sessionQuestions->sum(fn ($sq) => (float) $sq->question->points);

        return view('tenant.quizzes.offline-result', compact('session', 'participant', 'responses', 'maxScore'));
    }

    /**
     * API: Get current question state for student (polling — live quiz).
     *
     * The active question and session status are cached for 3 seconds and shared
     * across all students in the session. Only per-student data (answered/score)
     * is queried individually, keeping DB load flat regardless of participant count.
     */
    public function studentState(string $tenantSlug, QuizSession $session): JsonResponse
    {
        $user = auth()->user();

        // Shared cache: same active question for every student — busted by start/next/end
        $shared = Cache::remember("quiz_session_{$session->id}_state", 3, function () use ($session) {
            $activeQ = $session->activeQuestion();

            if (! $activeQ) {
                return ['status' => $session->status, 'active_q_id' => null, 'question' => null];
            }

            $q = $activeQ->question()->with('options')->first();

            return [
                'status' => $session->status,
                'active_q_id' => $activeQ->id,
                'question' => [
                    'session_question_id' => $activeQ->id,
                    'text' => $q->text,
                    'type' => $q->question_type,
                    'time_limit' => $q->time_limit_seconds,
                    'points' => $q->points,
                    'options' => $q->options->map(fn ($o) => [
                        'id' => $o->id,
                        'label' => $o->label,
                        'text' => $o->text,
                    ])->toArray(),
                ],
            ];
        });

        // Per-student queries (fast: uses unique index on quiz_participants + quiz_responses)
        $participant = QuizParticipant::where('quiz_session_id', $session->id)
            ->where('user_id', $user->id)
            ->select(['id', 'total_score'])
            ->first();

        $answered = false;
        if ($shared['active_q_id'] && $participant) {
            $answered = QuizResponse::where('quiz_session_question_id', $shared['active_q_id'])
                ->where('quiz_participant_id', $participant->id)
                ->exists();
        }

        return response()->json([
            'status' => $shared['status'],
            'question' => $shared['question'],
            'answered' => $answered,
            'score' => $participant?->total_score ?? 0,
        ]);
    }
}
