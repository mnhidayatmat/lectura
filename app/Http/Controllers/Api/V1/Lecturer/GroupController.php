<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Section;
use App\Models\StudentGroup;
use App\Models\StudentGroupMember;
use App\Models\StudentGroupSet;
use App\Models\Submission;
use App\Models\User;
use App\Services\StudentGroupingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Student group sets for a course — the web's StudentGroupController, plus the rename, move and
 * leader actions it lacks. Every change is checked against the course, the set's section and
 * the students actually enrolled in it.
 */
class GroupController extends Controller
{
    use AuthorizesLecturerAccess;

    private const COLORS = ['#6366f1', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'];

    public function __construct(protected StudentGroupingService $grouping) {}

    public function index(Course $course): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        $sectionIds = $this->lecturerSectionIds($course);

        $sets = StudentGroupSet::where('course_id', $course->id)
            ->where(fn ($q) => $q->whereIn('section_id', $sectionIds)->orWhereNull('section_id'))
            ->with('section')
            ->withCount('groups')
            ->latest()
            ->get();

        return response()->json([
            'data' => $sets->map(fn (StudentGroupSet $set) => $this->setSummary($set))->values(),
            'meta' => [
                'sections' => $this->lecturerSections($course)->get(['id', 'name'])->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->name])->values(),
            ],
        ]);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'section_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:lecture,lab,tutorial'],
            'description' => ['nullable', 'string', 'max:1000'],
            'creation_method' => ['required', 'string', 'in:manual,random'],
            'group_size' => ['required_if:creation_method,random', 'nullable', 'integer', 'min:2', 'max:20'],
        ]);

        if (! $this->lecturerSectionIds($course)->contains((int) $validated['section_id'])) {
            throw ValidationException::withMessages(['section_id' => 'Choose one of your sections of this course.']);
        }

        $set = StudentGroupSet::create([
            'tenant_id' => app('current_tenant')->id,
            'course_id' => $course->id,
            'section_id' => $validated['section_id'],
            'academic_term_id' => $course->academic_term_id,
            'type' => $validated['type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'creation_method' => $validated['creation_method'],
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        if ($validated['creation_method'] === 'random') {
            $this->grouping->arrangeRandom($set, (int) $validated['group_size']);
        }

        return $this->detail($course, $set, 'Group set created.', 201);
    }

    public function show(Course $course, StudentGroupSet $set): JsonResponse
    {
        $this->authorizeSet($course, $set);

        return $this->detail($course, $set);
    }

    /**
     * Name, description and whether it is offered for new group assessments (new on the phone).
     */
    public function update(Request $request, Course $course, StudentGroupSet $set): JsonResponse
    {
        $this->authorizeSet($course, $set);

        $set->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return $this->detail($course, $set, 'Group set updated.');
    }

    /**
     * Soft delete, as on the web: its groups stay attached to any bound assessments.
     */
    public function destroy(Course $course, StudentGroupSet $set): JsonResponse
    {
        $this->authorizeSet($course, $set);

        $set->delete();

        return response()->json(['message' => 'Group set deleted.', 'data' => ['id' => $set->id]]);
    }

    public function storeGroup(Request $request, Course $course, StudentGroupSet $set): JsonResponse
    {
        $this->authorizeSet($course, $set);

        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $count = $set->groups()->count();

        $set->groups()->create([
            'name' => $validated['name'],
            'color_tag' => self::COLORS[$count % count(self::COLORS)],
            'sort_order' => $count,
        ]);

        return $this->detail($course, $set, 'Group added.', 201);
    }

    public function updateGroup(Request $request, Course $course, StudentGroupSet $set, StudentGroup $group): JsonResponse
    {
        $this->authorizeGroup($course, $set, $group);

        $group->update($request->validate(['name' => ['required', 'string', 'max:100']]));

        return $this->detail($course, $set, 'Group renamed.');
    }

    /**
     * Deleting a group takes its workspace (chat, files, tasks) with it, so it needs `confirm`
     * once the group has members or work handed in.
     */
    public function destroyGroup(Request $request, Course $course, StudentGroupSet $set, StudentGroup $group): JsonResponse
    {
        $this->authorizeGroup($course, $set, $group);

        $hasContent = $group->members()->exists() || $this->hasGroupSubmissions([$group->id]);

        if ($hasContent && ! $request->boolean('confirm')) {
            abort(409, "{$group->name} has members or submitted work. Deleting it also removes its workspace.");
        }

        $group->delete();

        return $this->detail($course, $set, 'Group deleted.');
    }

    public function addMember(Request $request, Course $course, StudentGroupSet $set, StudentGroup $group): JsonResponse
    {
        $this->authorizeGroup($course, $set, $group);

        $validated = $request->validate(['user_id' => ['required', 'integer']]);
        $userId = (int) $validated['user_id'];

        if (! $this->eligibleIds($set)->contains($userId)) {
            throw ValidationException::withMessages(['user_id' => 'This student is not enrolled in the set\'s section.']);
        }

        if ($this->membershipIn($set, $userId)) {
            throw ValidationException::withMessages(['user_id' => 'This student is already in a group of this set. Move them instead.']);
        }

        $group->members()->create(['user_id' => $userId, 'role' => 'member', 'joined_at' => now()]);

        return $this->detail($course, $set, 'Student added.', 201);
    }

    public function removeMember(Course $course, StudentGroupSet $set, StudentGroup $group, User $user): JsonResponse
    {
        $this->authorizeGroup($course, $set, $group);

        if ($group->members()->where('user_id', $user->id)->delete() === 0) {
            abort(404, 'This student is not in this group.');
        }

        return $this->detail($course, $set, 'Student removed.');
    }

    /**
     * Moves a student to another group of the same set as a plain member.
     */
    public function moveMember(Request $request, Course $course, StudentGroupSet $set, User $user): JsonResponse
    {
        $this->authorizeSet($course, $set);

        $validated = $request->validate(['group_id' => ['required', 'integer']]);
        $target = $set->groups()->whereKey($validated['group_id'])->first();

        if (! $target) {
            throw ValidationException::withMessages(['group_id' => 'Choose a group of this set.']);
        }

        $membership = $this->membershipIn($set, $user->id);

        if (! $membership) {
            abort(404, 'This student is not in a group of this set.');
        }

        if ($membership->student_group_id !== $target->id) {
            $membership->update(['student_group_id' => $target->id, 'role' => 'member']);
        }

        return $this->detail($course, $set, "Moved to {$target->name}.");
    }

    public function setLeader(Request $request, Course $course, StudentGroupSet $set, StudentGroup $group): JsonResponse
    {
        $this->authorizeGroup($course, $set, $group);

        $validated = $request->validate(['user_id' => ['required', 'integer']]);

        if (! $group->members()->where('user_id', $validated['user_id'])->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Choose a member of this group.']);
        }

        DB::transaction(function () use ($group, $validated) {
            $group->members()->where('role', 'leader')->update(['role' => 'member']);
            $group->members()->where('user_id', $validated['user_id'])->update(['role' => 'leader']);
        });

        return $this->detail($course, $set, 'Leader updated.');
    }

    /**
     * Re-deals every enrolled student into groups of `group_size`. Existing groups (and their
     * workspaces) are replaced, so that needs `replace: true` — the web does it without asking.
     */
    public function arrangeRandom(Request $request, Course $course, StudentGroupSet $set): JsonResponse
    {
        $this->authorizeSet($course, $set);

        $validated = $request->validate([
            'group_size' => ['required', 'integer', 'min:2', 'max:20'],
            'replace' => ['boolean'],
        ]);

        if ($this->eligibleIds($set)->isEmpty()) {
            abort(422, 'There are no students enrolled in this section yet.');
        }

        if ($set->groups()->exists() && ! $request->boolean('replace')) {
            $bound = Assessment::where('student_group_set_id', $set->id)->count()
                + Assignment::where('student_group_set_id', $set->id)->count();

            abort(409, 'This set already has groups. Arranging again replaces them and their workspaces'
                .($bound > 0 ? ", and {$bound} assessment(s) or assignment(s) use this set." : '.'));
        }

        $this->grouping->arrangeRandom($set, (int) $validated['group_size']);

        return $this->detail($course, $set, 'Students arranged into groups.');
    }

    private function authorizeSet(Course $course, StudentGroupSet $set): void
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        if ($set->course_id !== $course->id) {
            abort(404, 'Group set not found.');
        }

        // Unlike the web, a section lecturer only manages the sets of their own sections.
        if ($set->section_id !== null && ! $this->lecturerSectionIds($course)->contains($set->section_id)) {
            abort(403, 'This group set belongs to a section you do not teach.');
        }
    }

    private function authorizeGroup(Course $course, StudentGroupSet $set, StudentGroup $group): void
    {
        $this->authorizeSet($course, $set);

        if ($group->student_group_set_id !== $set->id) {
            abort(404, 'Group not found.');
        }
    }

    private function eligibleIds(StudentGroupSet $set)
    {
        return $this->grouping->getEnrolledStudents($set->course, $set->section)->pluck('id');
    }

    private function membershipIn(StudentGroupSet $set, int $userId): ?StudentGroupMember
    {
        return StudentGroupMember::whereIn('student_group_id', $set->groups()->select('id'))
            ->where('user_id', $userId)
            ->first();
    }

    private function hasGroupSubmissions(array $groupIds): bool
    {
        return Submission::whereIn('student_group_id', $groupIds)->exists()
            || AssessmentSubmission::whereIn('student_group_id', $groupIds)->exists();
    }

    private function setSummary(StudentGroupSet $set): array
    {
        return [
            'id' => $set->id,
            'name' => $set->name,
            'type' => $set->type,
            'description' => $set->description,
            'creation_method' => $set->creation_method,
            'max_group_size' => $set->max_group_size !== null ? (int) $set->max_group_size : null,
            'is_active' => (bool) $set->is_active,
            'section' => $set->section ? ['id' => $set->section->id, 'name' => $set->section->name] : null,
            'groups_count' => (int) ($set->groups_count ?? $set->groups()->count()),
            'created_at' => $set->created_at?->toIso8601String(),
        ];
    }

    private function detail(Course $course, StudentGroupSet $set, ?string $message = null, int $status = 200): JsonResponse
    {
        $set->refresh()->load(['section', 'groups.members.user'])->loadCount('groups');

        $eligible = $this->grouping->getEnrolledStudents($course, $set->section);
        $assigned = $set->groups->flatMap(fn (StudentGroup $group) => $group->members->pluck('user_id'));
        $studentIds = $this->studentIdNumbers($eligible->pluck('id'));
        $student = fn (?User $user) => $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'student_id_number' => $studentIds[$user->id] ?? null,
        ] : null;

        $data = array_merge($this->setSummary($set), [
            'groups' => $set->groups->map(fn (StudentGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'color_tag' => $group->color_tag,
                'members' => $group->members
                    ->sortBy(fn (StudentGroupMember $m) => [$m->role === 'leader' ? 0 : 1, mb_strtolower((string) $m->user?->name)])
                    ->map(fn (StudentGroupMember $m) => array_merge($student($m->user) ?? ['id' => $m->user_id, 'name' => null, 'student_id_number' => null], [
                        'role' => $m->role,
                    ]))
                    ->values(),
            ])->values(),
            'unassigned' => $eligible->reject(fn (User $user) => $assigned->contains($user->id))->map($student)->values(),
            'bound_count' => Assessment::where('student_group_set_id', $set->id)->count()
                + Assignment::where('student_group_set_id', $set->id)->count(),
        ]);

        return response()->json(array_filter(['message' => $message, 'data' => $data], fn ($v) => $v !== null), $status);
    }
}
