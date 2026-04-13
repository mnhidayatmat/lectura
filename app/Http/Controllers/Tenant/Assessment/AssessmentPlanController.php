<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Assessment;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentPlanController extends Controller
{
    use AuthorizesCourseAccess;
    public function overview(): View
    {
        $user = auth()->user();
        $tenant = app('current_tenant');

        $courses = Course::whereIn('id', $this->accessibleCourseIds())
            ->with(['assessments', 'learningOutcomes'])
            ->withCount('assessments')
            ->get()
            ->map(function ($course) {
                $totalWeightage = $course->assessments->whereNull('parent_id')->sum('weightage');
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
        $this->authorizeCourseAccess($course);

        $tenant = app('current_tenant');
        $course->load(['learningOutcomes']);

        // Load only top-level assessments with their children
        $assessments = $course->assessments()->topLevel()
            ->with(['children.clos', 'children.submissions', 'clos', 'items.assessable'])
            ->get();

        // Only top-level assessments contribute to the course total; children are sub-divisions of their parent.
        $totalWeightage = $assessments->sum('weightage');
        $coveredCloIds = $assessments->flatMap(fn ($a) => $a->clos->pluck('id'))
            ->merge($assessments->flatMap(fn ($a) => $a->children->flatMap(fn ($c) => $c->clos->pluck('id'))))
            ->unique();

        $course->setRelation('assessments', $assessments);

        return view('tenant.assessments.index', compact('tenant', 'course', 'totalWeightage', 'coveredCloIds'));
    }

    public function create(string $tenantSlug, Course $course, Assessment $parent = null): View
    {
        $this->authorizeCourseAccess($course);

        $tenant = app('current_tenant');
        $course->load('learningOutcomes');

        return view('tenant.assessments.create', compact('tenant', 'course', 'parent'));
    }

    public function store(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $request->validate([
            'parent_id'        => ['nullable', 'exists:assessments,id'],
            'title'            => ['required', 'string', 'max:255'],
            'type'             => ['required', 'string', 'in:' . implode(',', Assessment::TYPES)],
            'method'           => ['nullable', 'string', 'in:' . implode(',', Assessment::METHODS)],
            'weightage'        => ['required', 'numeric', 'min:0', 'max:100'],
            'total_marks'      => ['required', 'numeric', 'min:1'],
            'bloom_level'      => ['nullable', 'string', 'in:' . implode(',', Assessment::BLOOM_LEVELS)],
            'description'      => ['nullable', 'string', 'max:2000'],
            'clo_ids'          => ['nullable', 'array'],
            'clo_ids.*'        => ['integer', 'exists:course_learning_outcomes,id'],
            'requires_submission' => ['nullable', 'boolean'],
            'due_date'         => ['nullable', 'date'],
            'instruction_file' => ['nullable', 'file', 'max:25600', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip'],
        ]);

        $tenant = app('current_tenant');

        // Validate parent if provided
        if ($request->filled('parent_id')) {
            $parent = Assessment::find($request->parent_id);
            if (!$parent || $parent->course_id !== $course->id) {
                return back()->withErrors(['parent_id' => 'Invalid parent assessment.'])->withInput();
            }
        }

        $assessment = Assessment::create([
            'tenant_id'    => $tenant->id,
            'course_id'    => $course->id,
            'parent_id'    => $request->parent_id,
            'title'        => $request->title,
            'type'         => $request->type,
            'method'       => $request->method,
            'weightage'    => $request->weightage,
            'total_marks'  => $request->total_marks,
            'bloom_level'  => $request->bloom_level,
            'description'  => $request->description,
            'requires_submission' => $request->boolean('requires_submission'),
            'due_date'     => $request->due_date,
            'sort_order'   => $course->assessments()->count(),
            'status'       => 'draft',
        ]);

        if ($request->hasFile('instruction_file')) {
            $file = $request->file('instruction_file');
            $assessment->update([
                'instruction_file_path' => $file->store('assessment-instructions', 'local'),
                'instruction_file_name' => $file->getClientOriginalName(),
            ]);
        }

        if ($request->clo_ids) {
            $assessment->clos()->attach($request->clo_ids);
        }

        // Redirect child assessments back to the parent's edit page, not the index.
        if ($request->filled('parent_id') && isset($parent)) {
            return redirect()->route('tenant.assessments.edit', [$tenant->slug, $course, $parent])
                ->with('success', 'Child assessment added.');
        }

        // For submission-based assessments, land on the submissions page so the lecturer
        // can see who has submitted (and share the assessment with students right away).
        if ($assessment->requires_submission) {
            return redirect()->route('tenant.assessments.submissions.index', [$tenant->slug, $course, $assessment])
                ->with('success', 'Assessment created. Students can now submit their work.');
        }

        return redirect()->route('tenant.assessments.index', [$tenant->slug, $course])
            ->with('success', 'Assessment added to course plan.');
    }

    public function edit(string $tenantSlug, Course $course, Assessment $assessment): View
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $course->load('learningOutcomes');
        $assessment->load(['clos', 'children', 'parent']);

        $assignments = $course->hasMany(\App\Models\Assignment::class)->get(['id', 'title']);
        $quizzes = \App\Models\QuizSession::where('lecturer_id', auth()->id())
            ->whereIn('section_id', $this->lecturerSectionIds($course))
            ->get(['id', 'title']);

        return view('tenant.assessments.edit', compact('tenant', 'course', 'assessment', 'assignments', 'quizzes'));
    }

    public function update(Request $request, string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'type'             => ['required', 'string', 'in:' . implode(',', Assessment::TYPES)],
            'method'           => ['nullable', 'string', 'in:' . implode(',', Assessment::METHODS)],
            'weightage'        => ['required', 'numeric', 'min:0', 'max:100'],
            'total_marks'      => ['required', 'numeric', 'min:1'],
            'bloom_level'      => ['nullable', 'string', 'in:' . implode(',', Assessment::BLOOM_LEVELS)],
            'description'      => ['nullable', 'string', 'max:2000'],
            'status'           => ['nullable', 'string', 'in:' . implode(',', Assessment::STATUSES)],
            'clo_ids'          => ['nullable', 'array'],
            'clo_ids.*'        => ['integer', 'exists:course_learning_outcomes,id'],
            'requires_submission' => ['nullable', 'boolean'],
            'due_date'         => ['nullable', 'date'],
            'instruction_file' => ['nullable', 'file', 'max:25600', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip'],
        ]);

        $assessment->update(array_merge(
            $request->only([
                'title', 'type', 'method', 'weightage', 'total_marks',
                'bloom_level', 'description', 'status', 'due_date',
            ]),
            ['requires_submission' => $request->boolean('requires_submission')]
        ));

        // Handle instruction file: new upload replaces existing; remove_instruction deletes without replacing.
        if ($request->hasFile('instruction_file')) {
            if ($assessment->instruction_file_path) {
                Storage::disk('local')->delete($assessment->instruction_file_path);
            }
            $file = $request->file('instruction_file');
            $assessment->update([
                'instruction_file_path' => $file->store('assessment-instructions', 'local'),
                'instruction_file_name' => $file->getClientOriginalName(),
            ]);
        } elseif ($request->boolean('remove_instruction')) {
            if ($assessment->instruction_file_path) {
                Storage::disk('local')->delete($assessment->instruction_file_path);
            }
            $assessment->update(['instruction_file_path' => null, 'instruction_file_name' => null]);
        }

        $assessment->clos()->sync($request->clo_ids ?? []);

        return redirect()->route('tenant.assessments.index', [app('current_tenant')->slug, $course])
            ->with('success', 'Assessment updated.');
    }

    public function destroy(string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        // Delete instruction file if present
        if ($assessment->instruction_file_path) {
            Storage::disk('local')->delete($assessment->instruction_file_path);
        }

        // If this is a parent assessment, cascade delete children (and their files)
        if ($assessment->isParent()) {
            foreach ($assessment->children as $child) {
                if ($child->instruction_file_path) {
                    Storage::disk('local')->delete($child->instruction_file_path);
                }
                $child->delete();
            }
        }

        $assessment->delete();

        return back()->with('success', 'Assessment removed.');
    }

    public function downloadInstruction(string $tenantSlug, Course $course, Assessment $assessment): StreamedResponse
    {
        if ($assessment->course_id !== $course->id || ! $assessment->instruction_file_path) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($assessment->instruction_file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $assessment->instruction_file_path,
            $assessment->instruction_file_name ?? 'instruction'
        );
    }

    public function viewInstruction(string $tenantSlug, Course $course, Assessment $assessment): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if ($assessment->course_id !== $course->id || ! $assessment->instruction_file_path) {
            abort(404);
        }

        $absolutePath = Storage::disk('local')->path($assessment->instruction_file_path);

        if (! file_exists($absolutePath)) {
            abort(404);
        }

        // response()->file() sets Content-Disposition: inline so the browser renders it.
        return response()->file($absolutePath);
    }
}
