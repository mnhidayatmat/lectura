<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\ActiveLearning;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActiveLearning\StoreGroupRequest;
use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningGroup;
use App\Models\ActiveLearningPlan;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\User;
use App\Services\ActiveLearning\GroupingService;
use App\Services\ActiveLearning\TierGateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveLearningGroupController extends Controller
{
    public function __construct(
        protected GroupingService $groupingService,
        protected TierGateService $tierGate,
    ) {}

    public function store(
        StoreGroupRequest $request,
        string $tenantSlug,
        Course $course,
        ActiveLearningPlan $plan,
        ActiveLearningActivity $activity,
    ): RedirectResponse {
        $this->authorizeAndValidate($course, $plan, $activity);

        $this->groupingService->createGroup(
            $activity,
            $request->validated('name'),
            $request->validated('attendance_session_id'),
        );

        return back()->with('success', __('active_learning.group_created'));
    }

    public function destroy(
        string $tenantSlug,
        Course $course,
        ActiveLearningPlan $plan,
        ActiveLearningActivity $activity,
        ActiveLearningGroup $group,
    ): RedirectResponse {
        $this->authorizeAndValidate($course, $plan, $activity);

        if ($group->active_learning_activity_id !== $activity->id) {
            abort(404);
        }

        $group->delete();

        return back()->with('success', __('active_learning.group_deleted'));
    }

    public function addMember(
        Request $request,
        string $tenantSlug,
        Course $course,
        ActiveLearningPlan $plan,
        ActiveLearningActivity $activity,
        ActiveLearningGroup $group,
    ): RedirectResponse {
        $this->authorizeAndValidate($course, $plan, $activity);

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['nullable', 'in:member,facilitator,reporter,scribe'],
        ]);

        $student = User::findOrFail($request->input('user_id'));
        $this->groupingService->addMemberToGroup($group, $student, $request->input('role', 'member'));

        return back()->with('success', __('active_learning.member_added'));
    }

    public function removeMember(
        string $tenantSlug,
        Course $course,
        ActiveLearningPlan $plan,
        ActiveLearningActivity $activity,
        ActiveLearningGroup $group,
        User $user,
    ): RedirectResponse {
        $this->authorizeAndValidate($course, $plan, $activity);

        $this->groupingService->removeMemberFromGroup($group, $user);

        return back()->with('success', __('active_learning.member_removed'));
    }

    public function arrangeFromAttendance(
        Request $request,
        string $tenantSlug,
        Course $course,
        ActiveLearningPlan $plan,
        ActiveLearningActivity $activity,
    ): RedirectResponse {
        $this->authorizeAndValidate($course, $plan, $activity);

        $request->validate([
            'attendance_session_id' => ['required', 'exists:attendance_sessions,id'],
            'group_size' => ['required', 'integer', 'min:2', 'max:50'],
        ]);

        $session = AttendanceSession::findOrFail($request->input('attendance_session_id'));

        // Validate session belongs to a section of this course
        $courseSectionIds = $course->sections()->pluck('id');
        if (! $courseSectionIds->contains($session->section_id)) {
            abort(403);
        }

        $this->groupingService->arrangeGroupsFromAttendance(
            $activity,
            $session,
            $request->integer('group_size'),
        );

        return back()->with('success', __('active_learning.groups_arranged'));
    }

    public function arrangeWithAi(
        Request $request,
        string $tenantSlug,
        Course $course,
        ActiveLearningPlan $plan,
        ActiveLearningActivity $activity,
    ): RedirectResponse {
        $this->authorizeAndValidate($course, $plan, $activity);

        $tenant = app('current_tenant');
        $this->tierGate->assertProFeature(auth()->user(), __('active_learning.ai_grouping'));

        $request->validate([
            'attendance_session_id' => ['required', 'exists:attendance_sessions,id'],
            'group_size' => ['required', 'integer', 'min:2', 'max:50'],
        ]);

        $session = AttendanceSession::findOrFail($request->input('attendance_session_id'));

        $courseSectionIds = $course->sections()->pluck('id');
        if (! $courseSectionIds->contains($session->section_id)) {
            abort(403);
        }

        // For now, use random arrangement; AI grouping will be dispatched as a job when real AI is connected
        $this->groupingService->arrangeGroupsFromAttendance(
            $activity,
            $session,
            $request->integer('group_size'),
        );

        return back()->with('success', __('active_learning.groups_ai_arranged'));
    }

    protected function authorizeAndValidate(Course $course, ActiveLearningPlan $plan, ActiveLearningActivity $activity): void
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        if ($plan->course_id !== $course->id) {
            abort(404);
        }

        if ($activity->active_learning_plan_id !== $plan->id) {
            abort(404);
        }
    }
}
