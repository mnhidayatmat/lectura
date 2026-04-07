<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\GroupSleepingPartnerReport;
use App\Models\GroupSwapRequest;
use App\Models\Section;
use App\Models\StudentGroup;
use App\Models\StudentGroupMember;
use App\Models\StudentGroupSet;
use App\Models\User;
use App\Services\StudentGroupingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentGroupController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(
        protected StudentGroupingService $groupingService,
    ) {}

    public function myGroups(): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();
        $role = $user->roleInTenant($tenant->id);

        if (in_array($role, ['lecturer', 'admin', 'coordinator'])) {
            $ownedCourseIds = Course::where('lecturer_id', $user->id)->pluck('id');
            $sectionCourseIds = Section::where('lecturer_id', $user->id)->pluck('course_id');
            $allCourseIds = $ownedCourseIds->merge($sectionCourseIds)->unique();

            $courses = Course::whereIn('id', $allCourseIds)
                ->with(['studentGroupSets' => fn ($q) => $q->withCount('groups')->latest(), 'academicTerm'])
                ->get();

            return view('tenant.student-groups.lecturer-index', compact('tenant', 'courses'));
        }

        // Student view — filter out memberships with missing relationships
        $memberships = StudentGroupMember::where('user_id', $user->id)
            ->with('group.groupSet.course')
            ->get()
            ->filter(fn ($m) => $m->group && $m->group->groupSet && $m->group->groupSet->course)
            ->groupBy(fn ($m) => $m->group->groupSet->course_id);

        return view('tenant.student-groups.student-index', compact('tenant', 'memberships'));
    }

    // ── Lecturer ──

    public function index(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourseAccess($course);

        $tenant = app('current_tenant');
        $mySectionIds = $this->lecturerSectionIds($course);

        $sets = $course->studentGroupSets()
            ->whereIn('section_id', $mySectionIds)
            ->withCount('groups')
            ->with(['creator', 'section'])
            ->latest()
            ->get();

        return view('tenant.student-groups.index', compact('tenant', 'course', 'sets'));
    }

    public function create(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourseAccess($course);

        $tenant = app('current_tenant');
        $sections = $this->lecturerSections($course)->withCount(['activeStudents'])->get();

        return view('tenant.student-groups.create', compact('tenant', 'course', 'sections'));
    }

    public function store(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'type' => ['required', 'string', 'in:lecture,lab,tutorial'],
            'description' => ['nullable', 'string', 'max:1000'],
            'creation_method' => ['required', 'string', 'in:manual,random'],
            'group_size' => ['required_if:creation_method,random', 'nullable', 'integer', 'min:2', 'max:20'],
        ]);

        $tenant = app('current_tenant');

        $set = StudentGroupSet::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'section_id' => $request->integer('section_id'),
            'academic_term_id' => $course->academic_term_id,
            'type' => $request->input('type'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'creation_method' => $request->input('creation_method'),
            'created_by' => auth()->id(),
        ]);

        if ($request->input('creation_method') === 'random' && $request->filled('group_size')) {
            $this->groupingService->arrangeRandom($set, $request->integer('group_size'));
        }

        return redirect()->route('tenant.student-groups.show', [$tenant->slug, $course, $set])
            ->with('success', 'Group set created.');
    }

    public function show(string $tenantSlug, Course $course, StudentGroupSet $set): View
    {
        $this->authorizeCourseAccess($course);
        if ($set->course_id !== $course->id) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $set->load(['groups.members.user', 'section']);

        $unassigned = $this->groupingService->getUnassignedStudents($set);
        $enrolledCount = $this->groupingService->getEnrolledStudents($course, $set->section)->count();

        $groupIds = $set->groups()->pluck('id');

        $pendingSwaps = GroupSwapRequest::whereIn('from_group_id', $groupIds)
            ->where('status', GroupSwapRequest::STATUS_PENDING_LECTURER)
            ->with(['requester', 'targetUser', 'fromGroup', 'toGroup'])
            ->get();

        $sleepingReports = GroupSleepingPartnerReport::whereIn('student_group_id', $groupIds)
            ->where('is_reviewed', false)
            ->with(['group', 'reportedUser'])
            ->get();

        return view('tenant.student-groups.show', compact('tenant', 'course', 'set', 'unassigned', 'enrolledCount', 'pendingSwaps', 'sleepingReports'));
    }

    public function storeGroup(Request $request, string $tenantSlug, Course $course, StudentGroupSet $set): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($set->course_id !== $course->id) {
            abort(403);
        }

        $request->validate(['name' => ['required', 'string', 'max:100']]);

        StudentGroup::create([
            'student_group_set_id' => $set->id,
            'name' => $request->input('name'),
            'sort_order' => $set->groups()->count(),
        ]);

        return back()->with('success', 'Group added.');
    }

    public function destroyGroup(string $tenantSlug, Course $course, StudentGroupSet $set, StudentGroup $group): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($set->course_id !== $course->id || $group->student_group_set_id !== $set->id) {
            abort(403);
        }

        $group->delete();

        return back()->with('success', 'Group deleted.');
    }

    public function addMember(Request $request, string $tenantSlug, Course $course, StudentGroupSet $set, StudentGroup $group): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($group->student_group_set_id !== $set->id) {
            abort(403);
        }

        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', 'in:member,leader'],
        ]);

        $student = User::findOrFail($request->integer('user_id'));
        $this->groupingService->addMember($group, $student, $request->input('role', 'member'));

        return back()->with('success', $student->name . ' added to ' . $group->name . '.');
    }

    public function removeMember(string $tenantSlug, Course $course, StudentGroupSet $set, StudentGroup $group, User $user): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($group->student_group_set_id !== $set->id) {
            abort(403);
        }

        $this->groupingService->removeMember($group, $user);

        return back()->with('success', $user->name . ' removed from ' . $group->name . '.');
    }

    public function arrangeRandom(Request $request, string $tenantSlug, Course $course, StudentGroupSet $set): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($set->course_id !== $course->id) {
            abort(403);
        }

        $request->validate(['group_size' => ['required', 'integer', 'min:2', 'max:20']]);

        $this->groupingService->arrangeRandom($set, $request->integer('group_size'));

        return back()->with('success', 'Students randomly arranged into groups.');
    }

    public function destroy(string $tenantSlug, Course $course, StudentGroupSet $set): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($set->course_id !== $course->id) {
            abort(403);
        }

        $set->delete();

        return redirect()->route('tenant.student-groups.index', [app('current_tenant')->slug, $course])
            ->with('success', 'Group set deleted.');
    }

    // ── Student (handled by myGroups() above) ──
}
