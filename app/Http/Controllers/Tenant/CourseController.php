<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Course\StoreCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseLearningOutcome;
use App\Models\CourseTopic;
use App\Models\Faculty;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(): View
    {
        $courses = Course::where('lecturer_id', auth()->id())
            ->withCount('sections')
            ->with(['academicTerm', 'faculty'])
            ->latest()
            ->get();

        return view('tenant.courses.index', compact('courses'));
    }

    public function create(): View
    {
        $faculties = Faculty::orderBy('name')->get();
        $terms = AcademicTerm::orderByDesc('start_date')->get();

        return view('tenant.courses.create', compact('faculties', 'terms'));
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $tenant = app('current_tenant');

        $course = Course::create([
            'tenant_id' => $tenant->id,
            'lecturer_id' => auth()->id(),
            'code' => $request->code,
            'title' => $request->title,
            'description' => $request->description,
            'credit_hours' => $request->credit_hours,
            'num_weeks' => $request->num_weeks,
            'teaching_mode' => $request->teaching_mode,
            'format' => $request->format,
            'faculty_id' => $request->faculty_id,
            'programme_id' => $request->programme_id,
            'academic_term_id' => $request->academic_term_id,
            'status' => 'active',
        ]);

        // Create CLOs
        if ($request->clos) {
            foreach ($request->clos as $i => $clo) {
                if (! empty($clo['code']) && ! empty($clo['description'])) {
                    CourseLearningOutcome::create([
                        'course_id' => $course->id,
                        'code' => $clo['code'],
                        'description' => $clo['description'],
                        'sort_order' => $i,
                    ]);
                }
            }
        }

        // Create topics
        if ($request->topics) {
            foreach ($request->topics as $i => $topic) {
                if (! empty($topic['title'])) {
                    CourseTopic::create([
                        'course_id' => $course->id,
                        'week_number' => $topic['week_number'] ?? ($i + 1),
                        'title' => $topic['title'],
                        'sort_order' => $i,
                    ]);
                }
            }
        }

        return redirect()->route('tenant.courses.show', [
            'tenant' => $tenant->slug,
            'course' => $course->id,
        ])->with('success', 'Course created successfully.');
    }

    public function show(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourse($course);

        $course->load([
            'learningOutcomes',
            'topics',
            'sections.activeStudents',
            'sections.academicTerm',
            'activeLearningPlans',
            'studentGroupSets',
            'faculty',
            'programme',
            'academicTerm',
        ]);

        $terms = AcademicTerm::orderByDesc('start_date')->get();

        return view('tenant.courses.show', compact('course', 'terms'));
    }

    public function edit(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourse($course);

        $course->load(['learningOutcomes', 'topics']);
        $faculties = Faculty::orderBy('name')->get();
        $terms = AcademicTerm::orderByDesc('start_date')->get();

        return view('tenant.courses.edit', compact('course', 'faculties', 'terms'));
    }

    public function update(UpdateCourseRequest $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $tenant = app('current_tenant');

        $course->update(array_merge($request->only([
            'code', 'title', 'description', 'credit_hours', 'num_weeks',
            'teaching_mode', 'format', 'faculty_id', 'programme_id',
            'academic_term_id',
        ]), ['status' => 'active']));

        return redirect()->route('tenant.courses.show', [
            'tenant' => $tenant->slug,
            'course' => $course->id,
        ])->with('success', 'Course updated successfully.');
    }

    public function destroy(string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);
        $tenant = app('current_tenant');

        $course->delete();

        return redirect()->route('tenant.courses.index', $tenant->slug)
            ->with('success', 'Course deleted.');
    }

    protected function authorizeCourse(Course $course): void
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        if ($course->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($user->id !== $course->lecturer_id && ! $user->hasRoleInTenant($tenant->id, ['admin'])) {
            abort(403);
        }
    }
}
