<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Assessment;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessmentPlanController extends Controller
{
    public function overview(): View
    {
        $user = auth()->user();
        $tenant = app('current_tenant');

        $courses = Course::where('lecturer_id', $user->id)
            ->with(['assessments', 'learningOutcomes'])
            ->withCount('assessments')
            ->get()
            ->map(function ($course) {
                $totalWeightage = $course->assessments->sum('weightage');
                $closCovered = $course->assessments->flatMap(fn ($a) => $a->clos->pluck('id'))->unique()->count();
                $course->cap_weightage = $totalWeightage;
                $course->clos_covered = $closCovered;
                $course->clos_total = $course->learningOutcomes->count();

                return $course;
            });

        return view('tenant.assessments.overview', compact('tenant', 'courses'));
    }

    public function index(string $tenantSlug, Course $course): View
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $course->load(['learningOutcomes', 'assessments.clos', 'assessments.items.assessable']);

        $totalWeightage = $course->assessments->sum('weightage');
        $coveredCloIds = $course->assessments->flatMap(fn ($a) => $a->clos->pluck('id'))->unique();

        return view('tenant.assessments.index', compact('tenant', 'course', 'totalWeightage', 'coveredCloIds'));
    }

    public function create(string $tenantSlug, Course $course): View
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $course->load('learningOutcomes');

        return view('tenant.assessments.create', compact('tenant', 'course'));
    }

    public function store(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . implode(',', Assessment::TYPES)],
            'method' => ['nullable', 'string', 'in:' . implode(',', Assessment::METHODS)],
            'weightage' => ['required', 'numeric', 'min:0', 'max:100'],
            'total_marks' => ['required', 'numeric', 'min:1'],
            'bloom_level' => ['nullable', 'string', 'in:' . implode(',', Assessment::BLOOM_LEVELS)],
            'description' => ['nullable', 'string', 'max:2000'],
            'clo_ids' => ['nullable', 'array'],
            'clo_ids.*' => ['integer', 'exists:course_learning_outcomes,id'],
        ]);

        $tenant = app('current_tenant');

        $assessment = Assessment::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'title' => $request->title,
            'type' => $request->type,
            'method' => $request->method,
            'weightage' => $request->weightage,
            'total_marks' => $request->total_marks,
            'bloom_level' => $request->bloom_level,
            'description' => $request->description,
            'sort_order' => $course->assessments()->count(),
            'status' => 'draft',
        ]);

        if ($request->clo_ids) {
            $assessment->clos()->attach($request->clo_ids);
        }

        return redirect()->route('tenant.assessments.index', [$tenant->slug, $course])
            ->with('success', 'Assessment added to course plan.');
    }

    public function edit(string $tenantSlug, Course $course, Assessment $assessment): View
    {
        if ($course->lecturer_id !== auth()->id() || $assessment->course_id !== $course->id) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $course->load('learningOutcomes');
        $assessment->load('clos');

        $assignments = $course->hasMany(\App\Models\Assignment::class)->get(['id', 'title']);
        $quizzes = \App\Models\QuizSession::where('lecturer_id', auth()->id())
            ->whereIn('section_id', $course->sections()->pluck('id'))
            ->get(['id', 'title']);

        return view('tenant.assessments.edit', compact('tenant', 'course', 'assessment', 'assignments', 'quizzes'));
    }

    public function update(Request $request, string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id() || $assessment->course_id !== $course->id) {
            abort(403);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . implode(',', Assessment::TYPES)],
            'method' => ['nullable', 'string', 'in:' . implode(',', Assessment::METHODS)],
            'weightage' => ['required', 'numeric', 'min:0', 'max:100'],
            'total_marks' => ['required', 'numeric', 'min:1'],
            'bloom_level' => ['nullable', 'string', 'in:' . implode(',', Assessment::BLOOM_LEVELS)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:' . implode(',', Assessment::STATUSES)],
            'clo_ids' => ['nullable', 'array'],
            'clo_ids.*' => ['integer', 'exists:course_learning_outcomes,id'],
        ]);

        $assessment->update($request->only([
            'title', 'type', 'method', 'weightage', 'total_marks',
            'bloom_level', 'description', 'status',
        ]));

        $assessment->clos()->sync($request->clo_ids ?? []);

        return redirect()->route('tenant.assessments.index', [app('current_tenant')->slug, $course])
            ->with('success', 'Assessment updated.');
    }

    public function destroy(string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id() || $assessment->course_id !== $course->id) {
            abort(403);
        }

        $assessment->delete();

        return back()->with('success', 'Assessment removed.');
    }
}
