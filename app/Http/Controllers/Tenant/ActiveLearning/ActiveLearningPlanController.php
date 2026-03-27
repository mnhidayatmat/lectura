<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\ActiveLearning;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActiveLearning\StorePlanRequest;
use App\Http\Requests\ActiveLearning\UpdatePlanRequest;
use App\Jobs\GenerateActiveLearningPlan;
use App\Models\ActiveLearningPlan;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\CourseFile;
use App\Services\ActiveLearning\ActiveLearningPlanService;
use App\Services\ActiveLearning\TierGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActiveLearningPlanController extends Controller
{
    public function __construct(
        protected ActiveLearningPlanService $planService,
        protected TierGateService $tierGate,
    ) {}

    public function all(): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        $courses = Course::where('lecturer_id', $user->id)->get();

        $plans = ActiveLearningPlan::whereIn('course_id', $courses->pluck('id'))
            ->withCount('activities')
            ->with(['course', 'topic'])
            ->latest()
            ->get();

        return view('tenant.active-learning.all', compact('tenant', 'courses', 'plans'));
    }

    public function index(string $tenantSlug, Course $course): View
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $plans = ActiveLearningPlan::forCourse($course->id)
            ->withCount('activities')
            ->with('topic')
            ->latest()
            ->get();

        $tenant = app('current_tenant');

        return view('tenant.active-learning.index', compact('course', 'plans', 'tenant'));
    }

    public function create(string $tenantSlug, Course $course): View
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $course->load(['topics', 'learningOutcomes']);
        $tenant = app('current_tenant');

        return view('tenant.active-learning.create', compact('course', 'tenant'));
    }

    public function store(StorePlanRequest $request, string $tenantSlug, Course $course): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $plan = $this->planService->createPlan($course, $request->user(), $request->validated());

        return redirect()->route('tenant.active-learning.edit', [
            'tenant' => $tenant->slug,
            'course' => $course->id,
            'plan' => $plan->id,
        ])->with('success', __('active_learning.plan_created'));
    }

    public function show(string $tenantSlug, Course $course, ActiveLearningPlan $plan): View
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $this->assertPlanBelongsToCourse($plan, $course);

        $plan->load(['activities.groups.students', 'topic', 'creator']);
        $course->load('learningOutcomes');
        $tenant = app('current_tenant');

        return view('tenant.active-learning.show', compact('course', 'plan', 'tenant'));
    }

    public function edit(string $tenantSlug, Course $course, ActiveLearningPlan $plan): View
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $this->assertPlanBelongsToCourse($plan, $course);

        $plan->load(['activities.groups.students', 'topic']);
        $course->load(['topics', 'learningOutcomes', 'sections']);
        $tenant = app('current_tenant');

        // Get attendance sessions for the course's sections
        $sectionIds = $course->sections->pluck('id');
        $attendanceSessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'ended')
            ->with('section')
            ->latest('started_at')
            ->get();

        // Get course files for material linking
        $courseFiles = CourseFile::where('course_id', $course->id)->get();

        return view('tenant.active-learning.edit', compact(
            'course', 'plan', 'tenant', 'attendanceSessions', 'courseFiles'
        ));
    }

    public function update(UpdatePlanRequest $request, string $tenantSlug, Course $course, ActiveLearningPlan $plan): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $this->assertPlanBelongsToCourse($plan, $course);
        $this->planService->updatePlan($plan, $request->validated());

        $tenant = app('current_tenant');

        return redirect()->route('tenant.active-learning.edit', [
            'tenant' => $tenant->slug,
            'course' => $course->id,
            'plan' => $plan->id,
        ])->with('success', __('active_learning.plan_updated'));
    }

    public function destroy(string $tenantSlug, Course $course, ActiveLearningPlan $plan): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $this->assertPlanBelongsToCourse($plan, $course);
        $this->planService->deletePlan($plan);

        $tenant = app('current_tenant');

        return redirect()->route('tenant.active-learning.index', [
            'tenant' => $tenant->slug,
            'course' => $course->id,
        ])->with('success', __('active_learning.plan_deleted'));
    }

    public function publish(string $tenantSlug, Course $course, ActiveLearningPlan $plan): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $this->assertPlanBelongsToCourse($plan, $course);
        $this->planService->publishPlan($plan);

        return back()->with('success', __('active_learning.plan_published'));
    }

    public function generateAi(Request $request, string $tenantSlug, Course $course, ActiveLearningPlan $plan): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $this->tierGate->assertProFeature(auth()->user(), __('active_learning.ai_generation'));

        $this->assertPlanBelongsToCourse($plan, $course);

        if ($plan->isAiProcessing()) {
            return back()->with('warning', __('active_learning.ai_already_processing'));
        }

        $request->validate([
            'lecture_notes' => ['nullable', 'string', 'max:50000'],
            'lecture_notes_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        // Extract text from uploaded PDF
        $lectureNotes = $request->input('lecture_notes', '');

        if ($request->hasFile('lecture_notes_file')) {
            $pdfText = $this->extractPdfText($request->file('lecture_notes_file'));
            $lectureNotes = trim($lectureNotes . "\n\n" . $pdfText);
        }

        // Count enrolled students
        $studentCount = \App\Models\SectionStudent::whereHas(
            'section',
            fn ($q) => $q->where('course_id', $course->id)
        )->where('is_active', true)->distinct('user_id')->count('user_id');

        $plan->update([
            'source' => 'ai_generated',
            'ai_generation_status' => 'pending',
        ]);

        GenerateActiveLearningPlan::dispatch($plan, $lectureNotes ?: null, $studentCount);

        return back()->with('success', __('active_learning.ai_generation_started'));
    }

    protected function extractPdfText(\Illuminate\Http\UploadedFile $file): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($file->getRealPath());
            $text = $pdf->getText();

            // Limit to 50K chars to avoid prompt overflow
            return mb_substr($text, 0, 50000);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PDF text extraction failed', [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    public function generationStatus(string $tenantSlug, Course $course, ActiveLearningPlan $plan): JsonResponse
    {
        $this->assertPlanBelongsToCourse($plan, $course);

        return response()->json([
            'status' => $plan->fresh()->ai_generation_status,
        ]);
    }

    protected function assertPlanBelongsToCourse(ActiveLearningPlan $plan, Course $course): void
    {
        if ($plan->course_id !== $course->id) {
            abort(404);
        }
    }
}
