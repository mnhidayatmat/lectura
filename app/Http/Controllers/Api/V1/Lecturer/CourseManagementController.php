<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseLearningOutcome;
use App\Models\CourseTopic;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Creating and editing courses, CLOs and weekly topics — the web's CourseController,
 * CloController and TopicController, with the ownership checks those leave out.
 */
class CourseManagementController extends Controller
{
    use AuthorizesLecturerAccess;

    private const TEACHING_MODES = ['face_to_face', 'online', 'hybrid'];

    private const FORMATS = ['lecture', 'tutorial', 'lab'];

    /**
     * Picker lists for the course and section forms.
     */
    public function options(): JsonResponse
    {
        $this->ensureLecturer();

        $colleagues = TenantUser::where('tenant_id', app('current_tenant')->id)
            ->whereIn('role', ['lecturer', 'admin', 'coordinator'])
            ->where('is_active', true)
            ->with('user:id,name,email')
            ->get()
            ->unique('user_id')
            ->filter(fn (TenantUser $membership) => $membership->user !== null)
            ->sortBy(fn (TenantUser $membership) => mb_strtolower($membership->user->name))
            ->map(fn (TenantUser $membership) => ['id' => $membership->user->id, 'name' => $membership->user->name, 'email' => $membership->user->email])
            ->values();

        return response()->json([
            'data' => [
                'academic_terms' => AcademicTerm::orderByDesc('start_date')->get()->map(fn (AcademicTerm $term) => [
                    'id' => $term->id,
                    'name' => $term->name,
                    'is_default' => (bool) $term->is_default,
                ])->values(),
                'faculties' => Faculty::orderBy('name')->get(['id', 'name', 'code']),
                'programmes' => Programme::orderBy('name')->get(['id', 'name', 'code', 'faculty_id']),
                'lecturers' => $colleagues,
                'teaching_modes' => self::TEACHING_MODES,
                'formats' => self::FORMATS,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureLecturer();

        if (! $request->user()->hasRoleInTenant(app('current_tenant')->id, ['lecturer', 'admin'])) {
            abort(403, 'Only lecturers can create courses.');
        }

        $validated = $request->validate(array_merge($this->courseRules(), [
            'clos' => ['nullable', 'array', 'max:20'],
            'clos.*.code' => ['required', 'string', 'max:20'],
            'clos.*.description' => ['required', 'string', 'max:1000'],
            'topics' => ['nullable', 'array', 'max:52'],
            'topics.*.week_number' => ['required', 'integer', 'min:1'],
            'topics.*.title' => ['required', 'string', 'max:255'],
        ]));

        $course = Course::create(array_merge($this->courseAttributes($validated), [
            'tenant_id' => app('current_tenant')->id,
            'lecturer_id' => $request->user()->id,
            'status' => 'active',
        ]));

        foreach (array_values($validated['clos'] ?? []) as $index => $clo) {
            $course->learningOutcomes()->create(['code' => $clo['code'], 'description' => $clo['description'], 'sort_order' => $index]);
        }

        foreach ($validated['topics'] ?? [] as $topic) {
            $course->topics()->create(['week_number' => $topic['week_number'], 'title' => $topic['title'], 'sort_order' => $topic['week_number']]);
        }

        return $this->detail($course, 'Course created.', 201);
    }

    /**
     * Owner or admin. Unlike the web, `status` is honoured, and fields left out stay as they are.
     */
    public function update(Request $request, Course $course): JsonResponse
    {
        $this->authorizeOwner($course);

        $rules = collect($this->courseRules())
            ->map(fn (array $rule) => array_map(fn ($r) => $r === 'required' ? 'sometimes' : $r, $rule))
            ->all();
        $rules['status'] = ['sometimes', 'in:draft,active,inactive,archived'];

        $validated = $request->validate($rules);

        $course->update(array_merge(
            $this->courseAttributes($validated),
            isset($validated['status']) ? ['status' => $validated['status']] : [],
        ));

        return $this->detail($course, 'Course updated.');
    }

    /**
     * Soft delete, as on the web — but only for the owner or an admin, not any section lecturer.
     */
    public function destroy(Course $course): JsonResponse
    {
        $this->authorizeOwner($course);

        $course->delete();

        return response()->json(['message' => "{$course->code} deleted.", 'data' => ['id' => $course->id]]);
    }

    public function storeClo(Request $request, Course $course): JsonResponse
    {
        $this->authorizeOwner($course);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $clo = $course->learningOutcomes()->create($validated + [
            'sort_order' => (int) $course->learningOutcomes()->max('sort_order') + 1,
        ]);

        return response()->json([
            'message' => 'Learning outcome added.',
            'data' => ['id' => $clo->id, 'code' => $clo->code, 'description' => $clo->description],
        ], 201);
    }

    public function destroyClo(Course $course, CourseLearningOutcome $clo): JsonResponse
    {
        $this->authorizeOwner($course);

        if ($clo->course_id !== $course->id) {
            abort(404, 'Learning outcome not found.');
        }

        $clo->delete();

        return response()->json(['message' => 'Learning outcome removed.', 'data' => ['id' => $clo->id]]);
    }

    public function storeTopic(Request $request, Course $course): JsonResponse
    {
        $this->authorizeOwner($course);

        $validated = $request->validate([
            'week_number' => ['required', 'integer', 'min:1', 'max:'.max(1, (int) $course->num_weeks)],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $topic = $course->topics()->create($validated + ['sort_order' => $validated['week_number']]);

        return response()->json([
            'message' => 'Topic added.',
            'data' => ['id' => $topic->id, 'week_number' => $topic->week_number, 'title' => $topic->title],
        ], 201);
    }

    public function destroyTopic(Course $course, CourseTopic $topic): JsonResponse
    {
        $this->authorizeOwner($course);

        if ($topic->course_id !== $course->id) {
            abort(404, 'Topic not found.');
        }

        $topic->delete();

        return response()->json(['message' => 'Topic removed.', 'data' => ['id' => $topic->id]]);
    }

    private function authorizeOwner(Course $course): void
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        if (! $this->isCourseOwner($course)) {
            abort(403, 'Only the course owner can change this course.');
        }
    }

    private function courseRules(): array
    {
        $tenantId = app('current_tenant')->id;

        return [
            'code' => ['required', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'credit_hours' => ['nullable', 'integer', 'min:1', 'max:20'],
            'num_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'teaching_mode' => ['required', 'in:'.implode(',', self::TEACHING_MODES)],
            'format' => ['nullable', 'array'],
            'format.*' => ['string', 'in:'.implode(',', self::FORMATS)],
            'faculty_id' => ['nullable', 'integer', Rule::exists('faculties', 'id')->where('tenant_id', $tenantId)],
            'programme_id' => ['nullable', 'integer', Rule::exists('programmes', 'id')->where('tenant_id', $tenantId)],
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    /**
     * `format` arrives as a list of keys (the shape the detail endpoint returns) and is
     * stored the way the web form stores ticked boxes.
     */
    private function courseAttributes(array $validated): array
    {
        $attributes = collect($validated)->only([
            'code', 'title', 'description', 'credit_hours', 'num_weeks', 'teaching_mode',
            'faculty_id', 'programme_id', 'academic_term_id',
        ])->all();

        if (array_key_exists('format', $validated)) {
            $attributes['format'] = collect($validated['format'] ?? [])->unique()->mapWithKeys(fn ($key) => [$key => '1'])->all();
        }

        return $attributes;
    }

    private function detail(Course $course, string $message, int $status = 200): JsonResponse
    {
        $resource = app(CourseController::class)->show($course->refresh());

        return response()->json(['message' => $message, 'data' => $resource->resolve()], $status);
    }
}
