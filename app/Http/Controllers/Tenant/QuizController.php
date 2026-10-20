<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Concerns\RedirectsToCourseContext;
use App\Http\Controllers\Controller;
use App\Models\ActiveLearningActivity;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuizFolder;
use App\Models\QuizParticipant;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Services\Quiz\LiveQuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\View\View;

class QuizController extends Controller
{
    use AuthorizesCourseAccess;
    use RedirectsToCourseContext;

    public function __construct(private LiveQuizService $game) {}

    /**
     * Quiz list for lecturer — grouped by course, then by folder.
     */
    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->redirectToCourseContext('tenant.quizzes.course')) {
            return $redirect;
        }

        $courseIds = $this->accessibleCourseIds();
        $sectionIds = $this->allAccessibleSectionIds();

        $sessionsByCourse = QuizSession::whereIn('section_id', $sectionIds)
            ->with('section:id,course_id')
            ->get()
            ->groupBy(fn ($s) => $s->section?->course_id);

        $courses = Course::whereIn('id', $courseIds)
            ->whereHas('sections', fn ($q) => $q->where('is_active', true))
            ->with('academicTerm')
            ->withCount(['sections' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('code')
            ->get()
            ->each(function (Course $course) use ($sessionsByCourse) {
                $quizzes = $sessionsByCourse->get($course->id, collect());
                $course->quiz_stats = [
                    'quizzes' => $quizzes->count(),
                    'live' => $quizzes->filter(fn ($s) => $s->category === 'live' && $s->isLive())->count(),
                    'open' => $quizzes->filter(fn ($s) => $s->category === 'offline' && $s->status !== 'ended')->count(),
                    'last' => $quizzes->max('created_at'),
                ];
            });

        return view('tenant.quizzes.index', compact('courses'));
    }

    /**
     * Quizzes for a single course.
     */
    public function course(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourseAccess($course);

        $sectionIds = Section::where('course_id', $course->id)
            ->whereIn('id', $this->allAccessibleSectionIds())
            ->pluck('id');

        $sessions = QuizSession::whereIn('section_id', $sectionIds)
            ->with(['section', 'folder'])
            ->withCount(['participants', 'sessionQuestions'])
            ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('tenant.quizzes.course', compact('course', 'sessions'));
    }

    /**
     * Save the manual order of a course's quizzes; ids from other courses are ignored.
     */
    public function reorder(Request $request, string $tenantSlug, Course $course): JsonResponse
    {
        $this->authorizeCourseAccess($course);

        $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        $sectionIds = Section::where('course_id', $course->id)
            ->whereIn('id', $this->allAccessibleSectionIds())
            ->pluck('id');

        foreach (array_values($request->input('ordered_ids')) as $index => $sessionId) {
            QuizSession::where('id', $sessionId)
                ->whereIn('section_id', $sectionIds)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    /** Store a new folder */
    public function storeFolder(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'in:indigo,emerald,amber,rose,teal,purple,slate'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant = app('current_tenant');
        QuizFolder::create([
            'tenant_id' => $tenant->id,
            'lecturer_id' => auth()->id(),
            'name' => $request->name,
            'color' => $request->color,
            'description' => $request->description,
        ]);

        return back()->with('success', 'Folder "'.$request->name.'" created.');
    }

    /** Update folder name/color */
    public function updateFolder(Request $request, string $tenantSlug, QuizFolder $folder): RedirectResponse
    {
        abort_if($folder->lecturer_id !== auth()->id(), 403);

        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'in:indigo,emerald,amber,rose,teal,purple,slate'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $folder->update($request->only('name', 'color', 'description'));

        return back()->with('success', 'Folder updated.');
    }

    /** Delete folder (quizzes become unfoldered) */
    public function destroyFolder(string $tenantSlug, QuizFolder $folder): RedirectResponse
    {
        abort_if($folder->lecturer_id !== auth()->id(), 403);
        $name = $folder->name;
        $folder->delete();

        return back()->with('success', 'Folder "'.$name.'" deleted. Quizzes moved to uncategorized.');
    }

    /** Move a quiz session into (or out of) a folder */
    public function moveToFolder(Request $request, string $tenantSlug, QuizSession $session): RedirectResponse
    {
        abort_if($session->lecturer_id !== auth()->id(), 403);

        $request->validate([
            'quiz_folder_id' => ['nullable', $this->ownFolderRule()],
        ]);

        $session->update(['quiz_folder_id' => $request->quiz_folder_id ?: null]);

        return back()->with('success', 'Quiz moved.');
    }

    /** A folder id must belong to the signed-in lecturer. */
    protected function ownFolderRule(): Exists
    {
        return Rule::exists('quiz_folders', 'id')->where('lecturer_id', auth()->id());
    }

    /**
     * Create quiz session with questions.
     */
    public function create(Request $request): View
    {
        $user = auth()->user();
        $sectionIds = $this->allAccessibleSectionIds();
        $sections = Section::whereIn('id', $sectionIds)
            ->with('course')
            ->where('is_active', true)
            ->get();

        $folders = QuizFolder::where('lecturer_id', $user->id)->orderBy('name')->get();

        // Load question bank for this lecturer
        $bankQuestions = Question::where('created_by', $user->id)
            ->where('is_bank', true)
            ->with('options')
            ->latest()
            ->limit(100)
            ->get();

        $selectedSectionId = $sections->firstWhere('course_id', (int) $request->query('course'))?->id;

        return view('tenant.quizzes.create', compact('sections', 'bankQuestions', 'folders', 'selectedSectionId'));
    }

    /**
     * Store quiz session + questions.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'section_id' => ['required', 'integer', Rule::in($this->allAccessibleSectionIds()->all())],
            'quiz_folder_id' => ['nullable', $this->ownFolderRule()],
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
            'quiz_folder_id' => $request->quiz_folder_id ?: null,
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
            return redirect()->route('tenant.quizzes.course', [$tenant->slug, $session->section->course_id])
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
        $sectionIds = $this->allAccessibleSectionIds();
        $sections = Section::whereIn('id', $sectionIds)
            ->with('course')
            ->where('is_active', true)
            ->get();
        $folders = QuizFolder::where('lecturer_id', $user->id)->orderBy('name')->get();

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

        return view('tenant.quizzes.edit', compact('session', 'sections', 'existingQuestions', 'folders'));
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
            'section_id' => ['required', 'integer', Rule::in($this->allAccessibleSectionIds()->all())],
            'quiz_folder_id' => ['nullable', $this->ownFolderRule()],
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
            'quiz_folder_id' => $request->quiz_folder_id ?: null,
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
            return redirect()->route('tenant.quizzes.course', [$tenant->slug, $session->section->course_id])
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

        $courseId = $session->section->course_id;
        $session->delete();

        return redirect()->route('tenant.quizzes.course', [$tenant->slug, $courseId])
            ->with('success', 'Quiz deleted successfully.');
    }

    /**
     * Lecturer's presenter board — run the quiz live (lobby, questions, standings, podium).
     */
    public function control(string $tenantSlug, QuizSession $session): View
    {
        $this->authorizeOwner($session);

        $session->load('section.course');
        $board = $this->game->boardState($session);
        $joinUrl = route('tenant.quizzes.join', [app('current_tenant')->slug, 'code' => $session->join_code]);

        return view('tenant.quizzes.control', compact('session', 'board', 'joinUrl'));
    }

    /**
     * Start the quiz: lobby closes and the first question opens.
     */
    public function start(Request $request, string $tenantSlug, QuizSession $session): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($session);
        $this->game->start($session);

        return $this->boardResponse($request, $session);
    }

    /**
     * Close the open question — the board shows the answers, then the standings.
     */
    public function closeQuestion(Request $request, string $tenantSlug, QuizSession $session): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($session);
        $this->game->closeActive($session);

        return $this->boardResponse($request, $session);
    }

    /**
     * Open the next question, or move to the final standings after the last one.
     */
    public function nextQuestion(Request $request, string $tenantSlug, QuizSession $session): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($session);
        $this->game->advance($session);

        return $this->boardResponse($request, $session);
    }

    /**
     * End quiz session.
     */
    public function end(Request $request, string $tenantSlug, QuizSession $session): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($session);
        $this->game->finish($session);

        $url = route('tenant.quizzes.results', ['tenant' => app('current_tenant')->slug, 'session' => $session->id]);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $url]);
        }

        return redirect($url)->with('success', 'Quiz ended.');
    }

    protected function authorizeOwner(QuizSession $session): void
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }
    }

    protected function boardResponse(Request $request, QuizSession $session): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json($this->game->boardState($session))
            : back();
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
        $newSession = $session->copyForNewRun(auth()->user());

        if ($newSession->isOffline()) {
            return redirect()->route('tenant.quizzes.course', [$tenant->slug, $newSession->section->course_id])
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
    /**
     * Authorize read-only access to a quiz session's results.
     *
     * The creating lecturer always has access. Additionally, tenant admins
     * and lecturers assigned to the session's course/section may view results,
     * so a quiz run under one account is not invisible to co-lecturers/admins.
     * Mutating actions (start, replay, edit, …) remain owner-only.
     */
    protected function authorizeSessionView(QuizSession $session): void
    {
        if ($session->lecturer_id === auth()->id()) {
            return;
        }

        $course = $session->section?->course;

        if ($course) {
            // Grants access to tenant admins + course/section lecturers,
            // aborts 403/404 otherwise.
            $this->authorizeCourseAccess($course);

            return;
        }

        abort(403);
    }

    public function results(string $tenantSlug, QuizSession $session): View
    {
        $this->authorizeSessionView($session);

        $session->load([
            'section.course',
            'sessionQuestions.question.options',
            'sessionQuestions.responses',
            'participants.user',
            'participants.responses',
        ]);
        $session->loadCount('participants');

        $questions = $session->sessionQuestions;
        $participants = $session->participants;
        $participantCount = $participants->count();
        $questionCount = $questions->count();
        $maxScore = (float) $questions->sum(fn ($sq) => (float) $sq->question->points);

        $questionStats = $questions->values()->map(function (QuizSessionQuestion $sq, int $i) use ($participantCount) {
            $responses = $sq->responses;
            $answered = $responses->count();
            $correct = $responses->where('is_correct', true)->count();

            $options = $sq->question->options->map(fn ($option) => [
                'label' => $option->label,
                'text' => $option->text,
                'is_correct' => (bool) $option->is_correct,
                'count' => $responses->where('selected_option_id', $option->id)->count(),
            ])->values();

            $avgMs = $responses->whereNotNull('response_time_ms')->avg('response_time_ms');

            return (object) [
                'number' => $i + 1,
                'question' => $sq->question,
                'answered' => $answered,
                'skipped' => max(0, $participantCount - $answered),
                'correct' => $correct,
                'correct_pct' => $answered > 0 ? (int) round($correct / $answered * 100) : null,
                'avg_seconds' => $avgMs !== null ? round($avgMs / 1000, 1) : null,
                'options' => $options,
                'top_wrong' => $options->where('is_correct', false)->where('count', '>', 0)->sortByDesc('count')->first(),
                'text_answers' => $sq->question->question_type === 'short_answer'
                    ? $responses->pluck('answer_text')->filter()->countBy()->sortDesc()->take(8)
                    : collect(),
            ];
        });

        $answeredQuestions = $questionStats->whereNotNull('correct_pct');
        $hardest = $answeredQuestions->sortBy('correct_pct')->first();
        $reteach = $answeredQuestions->where('correct_pct', '<', 60)->sortBy('correct_pct')->take(3)->values();

        // Live runs rank by game points (speed), like the board; offline quizzes by marks.
        $live = ! $session->isOffline();
        $rank = 0;
        $previousKey = null;
        $leaderboard = $participants
            ->sort(fn (QuizParticipant $a, QuizParticipant $b) => $live
                ? [$b->game_score, (float) $b->total_score, $a->id] <=> [$a->game_score, (float) $a->total_score, $b->id]
                : [(float) $b->total_score, $a->id] <=> [(float) $a->total_score, $b->id])
            ->values()
            ->map(function (QuizParticipant $p, int $i) use (&$rank, &$previousKey, $session, $questionCount, $live) {
                $score = (float) $p->total_score;
                $key = $live ? $p->game_score.'|'.$score : (string) $score;
                if ($key !== $previousKey) {
                    $rank = $i + 1;
                }
                $previousKey = $key;

                $answered = $p->responses->count();
                $correct = $p->responses->where('is_correct', true)->count();
                $avgMs = $p->responses->whereNotNull('response_time_ms')->avg('response_time_ms');
                $realName = $p->user?->name;

                return (object) [
                    'rank' => $rank,
                    'name' => $session->is_anonymous
                        ? ($p->display_name ?: 'Anonymous')
                        : ($realName ?? $p->display_name ?? 'Student'),
                    'nickname' => ! $session->is_anonymous && $p->display_name && $p->display_name !== $realName
                        ? $p->display_name : null,
                    'score' => $score,
                    'points' => $live ? (int) $p->game_score : null,
                    'correct' => $correct,
                    'answered' => $answered,
                    'unanswered' => max(0, $questionCount - $answered),
                    'accuracy' => $answered > 0 ? (int) round($correct / $answered * 100) : 0,
                    'avg_seconds' => $avgMs !== null ? round($avgMs / 1000, 1) : null,
                ];
            });

        $totalResponses = $questionStats->sum('answered');
        $summary = [
            'participants' => $participantCount,
            'questions' => $questionCount,
            'max_score' => $maxScore,
            'avg_pct' => $participantCount > 0 && $maxScore > 0
                ? (int) round($participants->avg('total_score') / $maxScore * 100) : null,
            'completion_pct' => $participantCount > 0 && $questionCount > 0
                ? (int) round($totalResponses / ($participantCount * $questionCount) * 100) : null,
            'hardest' => $hardest,
        ];

        // Active learning plans whose Quiz time card points at this run.
        $linkedPlans = ActiveLearningActivity::where('quiz_session_id', $session->id)
            ->with('plan:id,course_id,title,week_number')
            ->get()
            ->pluck('plan')
            ->filter()
            ->unique('id')
            ->values();

        $isOwner = $session->lecturer_id === auth()->id();
        $neverRun = $session->hasNeverRun();

        return view('tenant.quizzes.results', compact(
            'session', 'summary', 'questionStats', 'leaderboard', 'reteach', 'linkedPlans', 'isOwner', 'neverRun'
        ));
    }

    /**
     * API: board state for the lecturer's presenter screen (polled).
     */
    public function state(string $tenantSlug, QuizSession $session): JsonResponse
    {
        $this->authorizeSessionView($session);

        return response()->json($this->game->boardState($session));
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
                ->where('user_id', $user->id)
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

        if ($participant->wasRecentlyCreated) {
            Cache::forget("quiz_session_{$session->id}_lobby");
        }

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
            'answer_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $participant = QuizParticipant::where('quiz_session_id', $session->id)
            ->where('user_id', auth()->id())->first();

        if (! $participant) {
            return response()->json(['message' => 'Not joined.'], 403);
        }

        $sq = QuizSessionQuestion::where('id', $request->session_question_id)
            ->where('quiz_session_id', $session->id)
            ->where('status', 'active')
            ->with('question.options')
            ->first();

        if (! $sq) {
            return response()->json(['message' => 'Question is not active.'], 422);
        }

        $option = $request->filled('selected_option_id')
            ? $sq->question->options->firstWhere('id', (int) $request->selected_option_id)
            : null;

        if ($request->filled('selected_option_id') && ! $option) {
            return response()->json(['message' => 'The selected option is invalid.'], 422);
        }

        $answered = QuizResponse::where('quiz_session_question_id', $sq->id)
            ->where('quiz_participant_id', $participant->id)
            ->exists();

        if (! $answered && $this->game->isExpired($sq)) {
            return response()->json(['message' => 'Time is up for this question.'], 422);
        }

        [, $existing] = $this->game->answer($participant, $sq, $option, trim((string) $request->answer_text));

        return response()->json([
            'message' => $existing ? 'Already answered.' : 'Answer submitted.',
        ]);
    }

    /**
     * Student picks the name shown on the board and leaderboard.
     */
    public function nickname(Request $request, string $tenantSlug, QuizSession $session): JsonResponse
    {
        $request->validate([
            'nickname' => ['required', 'string', 'max:'.LiveQuizService::NICKNAME_MAX],
        ]);

        if ($session->isOffline() || ! $session->isLive() || $session->is_anonymous) {
            return response()->json(['message' => 'Names cannot be changed in this quiz.'], 422);
        }

        if ($this->game->cleanNickname($request->nickname) === '') {
            return response()->json(['message' => 'Please enter a name.', 'errors' => ['nickname' => ['Please enter a name.']]], 422);
        }

        $participant = QuizParticipant::where('quiz_session_id', $session->id)
            ->where('user_id', auth()->id())->first();

        if (! $participant) {
            return response()->json(['message' => 'Not joined.'], 403);
        }

        $this->game->rename($participant, $request->nickname);

        return response()->json(['message' => 'Name saved.', 'display_name' => $participant->fresh()->display_name]);
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
     * API: Lobby state — live participant count + list for the waiting room.
     * Polled by both the lecturer control page and the student play page while status === 'waiting'.
     */
    public function lobbyState(string $tenantSlug, QuizSession $session): JsonResponse
    {
        $data = Cache::remember("quiz_session_{$session->id}_lobby", 2, function () use ($session) {
            $participants = QuizParticipant::where('quiz_session_id', $session->id)
                ->with('user:id,name')
                ->orderBy('joined_at')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $this->game->displayName($p),
                    'joined_at' => optional($p->joined_at)->toIso8601String(),
                ])
                ->values();

            return [
                'status' => $session->fresh()->status,
                'count' => $participants->count(),
                'participants' => $participants,
            ];
        });

        return response()->json($data);
    }

    /**
     * API: live quiz state for the student's player (polled). Same payload as the mobile API.
     */
    public function studentState(string $tenantSlug, QuizSession $session): JsonResponse
    {
        $participant = QuizParticipant::where('quiz_session_id', $session->id)
            ->where('user_id', auth()->id())
            ->first();

        return response()->json($this->game->playerState($session, $participant));
    }
}
