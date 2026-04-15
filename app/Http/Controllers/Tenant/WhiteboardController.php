<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Events\WhiteboardSceneUpdated;
use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\ActiveLearningGroup;
use App\Models\Course;
use App\Models\SectionStudent;
use App\Models\Whiteboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WhiteboardController extends Controller
{
    use AuthorizesCourseAccess;

    /**
     * List boards for a course (course-scope + accessible group boards).
     */
    public function index(string $tenantSlug, Course $course): View
    {
        $this->ensureCourseAccess($course);

        $user = auth()->user();
        $isLecturer = $this->canManageCourse($course);

        $courseBoards = Whiteboard::where('course_id', $course->id)
            ->where('scope', Whiteboard::SCOPE_COURSE)
            ->with(['creator:id,name', 'lastEditor:id,name'])
            ->latest('updated_at')
            ->get();

        $groupBoardsQuery = Whiteboard::where('course_id', $course->id)
            ->where('scope', Whiteboard::SCOPE_GROUP)
            ->with(['creator:id,name', 'lastEditor:id,name', 'group']);

        if (! $isLecturer) {
            // Students see only group boards for groups they belong to
            $myGroupIds = DB::table('active_learning_group_members')
                ->where('user_id', $user->id)
                ->pluck('active_learning_group_id');

            $groupBoardsQuery->whereIn('active_learning_group_id', $myGroupIds);
        }

        $groupBoards = $groupBoardsQuery->latest('updated_at')->get();

        // Groups available to create a board for
        $availableGroups = $this->groupsAvailableForCreation($course, $isLecturer);

        return view('tenant.whiteboards.index', compact(
            'course', 'courseBoards', 'groupBoards', 'availableGroups', 'isLecturer'
        ));
    }

    /**
     * Create a new whiteboard.
     */
    public function store(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->ensureCourseAccess($course);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'scope' => ['required', 'in:course,group'],
            'active_learning_group_id' => ['nullable', 'required_if:scope,group', 'exists:active_learning_groups,id'],
        ]);

        $isLecturer = $this->canManageCourse($course);

        if ($validated['scope'] === Whiteboard::SCOPE_COURSE && ! $isLecturer) {
            abort(403, 'Only lecturers can create course-wide boards.');
        }

        $groupId = null;
        if ($validated['scope'] === Whiteboard::SCOPE_GROUP) {
            $group = ActiveLearningGroup::with('activity.plan')->findOrFail($validated['active_learning_group_id']);
            if ($group->activity->plan->course_id !== $course->id) {
                abort(404);
            }
            if (! $isLecturer && ! $this->isGroupMember($group)) {
                abort(403);
            }
            $groupId = $group->id;
        }

        $board = Whiteboard::create([
            'course_id' => $course->id,
            'active_learning_group_id' => $groupId,
            'scope' => $validated['scope'],
            'title' => $validated['title'],
            'scene_data' => null,
            'version' => 0,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('tenant.whiteboards.show', [$tenantSlug, $board])
            ->with('success', 'Whiteboard created.');
    }

    /**
     * Show a whiteboard (mounts the React app).
     */
    public function show(string $tenantSlug, Whiteboard $whiteboard): View
    {
        $this->ensureBoardAccess($whiteboard);

        $whiteboard->load(['course', 'group', 'creator:id,name']);

        return view('tenant.whiteboards.show', compact('whiteboard'));
    }

    /**
     * Persist scene & broadcast to other collaborators.
     */
    public function updateScene(Request $request, string $tenantSlug, Whiteboard $whiteboard): JsonResponse
    {
        $this->ensureBoardAccess($whiteboard);

        $validated = $request->validate([
            'elements' => ['present', 'array'],
            'appState' => ['nullable', 'array'],
            'sourceId' => ['required', 'string', 'max:64'],
        ]);

        $whiteboard->update([
            'scene_data' => [
                'elements' => $validated['elements'],
                'appState' => $validated['appState'] ?? null,
            ],
            'version' => $whiteboard->version + 1,
            'last_updated_by' => auth()->id(),
        ]);

        broadcast(new WhiteboardSceneUpdated(
            whiteboardId: $whiteboard->id,
            elements: $validated['elements'],
            appState: $validated['appState'] ?? null,
            version: $whiteboard->version,
            sourceId: $validated['sourceId'],
            updatedBy: auth()->id(),
        ))->toOthers();

        return response()->json([
            'version' => $whiteboard->version,
            'updated_at' => $whiteboard->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Delete a whiteboard. Allowed for managers or the creator.
     */
    public function destroy(string $tenantSlug, Whiteboard $whiteboard): RedirectResponse
    {
        $this->ensureBoardAccess($whiteboard);

        $isManager = $this->canManageCourse($whiteboard->course);
        if (! $isManager && $whiteboard->created_by !== auth()->id()) {
            abort(403);
        }

        $courseId = $whiteboard->course_id;
        $whiteboard->delete();

        return redirect()->route('tenant.whiteboards.index', [$tenantSlug, $courseId])
            ->with('success', 'Whiteboard deleted.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Allow access to a course for both lecturers and enrolled students.
     */
    protected function ensureCourseAccess(Course $course): void
    {
        $tenant = app('current_tenant');

        if ($course->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($this->canManageCourse($course)) {
            return;
        }

        if ($this->isEnrolledStudent($course)) {
            return;
        }

        abort(403);
    }

    /**
     * Authorize access to a specific board.
     */
    public function ensureBoardAccess(Whiteboard $whiteboard): void
    {
        $whiteboard->loadMissing('course', 'group');

        if (! $whiteboard->course) {
            abort(404);
        }

        // Lecturers/admins for the course always have access.
        if ($this->canManageCourse($whiteboard->course)) {
            return;
        }

        // Students must be enrolled in the course
        if (! $this->isEnrolledStudent($whiteboard->course)) {
            abort(403);
        }

        // Course-scope: any enrolled student may join
        if ($whiteboard->isCourseScope()) {
            return;
        }

        // Group-scope: must be a member of the group
        if ($whiteboard->group && $this->isGroupMember($whiteboard->group)) {
            return;
        }

        abort(403);
    }

    protected function canManageCourse(Course $course): bool
    {
        $user = auth()->user();
        $tenant = app('current_tenant');

        if ($user->hasRoleInTenant($tenant->id, ['admin'])) {
            return true;
        }

        if ($course->lecturer_id === $user->id) {
            return true;
        }

        return DB::table('section_lecturers')
            ->join('sections', 'sections.id', '=', 'section_lecturers.section_id')
            ->where('sections.course_id', $course->id)
            ->where('section_lecturers.user_id', $user->id)
            ->exists();
    }

    protected function isEnrolledStudent(Course $course): bool
    {
        return SectionStudent::whereHas('section', fn ($q) => $q->where('course_id', $course->id))
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->exists();
    }

    protected function isGroupMember(ActiveLearningGroup $group): bool
    {
        return DB::table('active_learning_group_members')
            ->where('active_learning_group_id', $group->id)
            ->where('user_id', auth()->id())
            ->exists();
    }

    /**
     * Groups for which the current user can create a whiteboard.
     */
    protected function groupsAvailableForCreation(Course $course, bool $isLecturer): \Illuminate\Support\Collection
    {
        $query = ActiveLearningGroup::query()
            ->select('active_learning_groups.id', 'active_learning_groups.name', 'active_learning_groups.color_tag')
            ->join('active_learning_activities', 'active_learning_activities.id', '=', 'active_learning_groups.active_learning_activity_id')
            ->join('active_learning_plans', 'active_learning_plans.id', '=', 'active_learning_activities.active_learning_plan_id')
            ->where('active_learning_plans.course_id', $course->id)
            ->orderBy('active_learning_groups.name');

        if (! $isLecturer) {
            $query->join('active_learning_group_members', 'active_learning_group_members.active_learning_group_id', '=', 'active_learning_groups.id')
                ->where('active_learning_group_members.user_id', auth()->id());
        }

        return $query->get()->unique('id')->values();
    }
}
