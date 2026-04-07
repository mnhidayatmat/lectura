<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Workspace;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\GroupSwapRequest;
use App\Models\StudentGroup;
use App\Models\StudentGroupMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceSwapController extends Controller
{
    use AuthorizesCourseAccess;
    /**
     * Member A initiates a swap request with Member B in another group.
     */
    public function store(Request $request, string $tenantSlug, StudentGroup $group): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            abort(403);
        }

        // Block if user already has a pending request
        $hasPending = GroupSwapRequest::where(function ($q) use ($user) {
            $q->where('requester_id', $user->id)->orWhere('target_user_id', $user->id);
        })->whereIn('status', [GroupSwapRequest::STATUS_PENDING_MEMBER, GroupSwapRequest::STATUS_PENDING_LECTURER])
          ->exists();

        if ($hasPending) {
            return back()->with('error', 'You already have a pending swap request.')->with('_tab', 'members');
        }

        // Target group must be in same group set
        $groupSetId = $group->groupSet->id;
        $targetGroupIds = $group->groupSet->groups()->where('id', '!=', $group->id)->pluck('id')->toArray();

        $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'to_group_id' => ['required', 'integer', 'in:' . implode(',', $targetGroupIds)],
        ]);

        // Verify target user is in target group
        $targetMember = StudentGroupMember::where('student_group_id', $request->to_group_id)
            ->where('user_id', $request->target_user_id)
            ->first();

        if (! $targetMember) {
            return back()->with('error', 'Selected member is not in that group.')->with('_tab', 'members');
        }

        GroupSwapRequest::create([
            'requester_id' => $user->id,
            'target_user_id' => $request->target_user_id,
            'from_group_id' => $group->id,
            'to_group_id' => $request->to_group_id,
            'status' => GroupSwapRequest::STATUS_PENDING_MEMBER,
        ]);

        return back()->with('success', 'Swap request sent. Waiting for the other member to accept.')->with('_tab', 'members');
    }

    /**
     * Member B responds (accept/decline) to swap request.
     */
    public function respond(Request $request, string $tenantSlug, GroupSwapRequest $swap): RedirectResponse
    {
        $user = auth()->user();

        if ($swap->target_user_id !== $user->id || $swap->status !== GroupSwapRequest::STATUS_PENDING_MEMBER) {
            abort(403);
        }

        $request->validate([
            'action' => ['required', 'in:accept,decline'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->action === 'decline') {
            $swap->update([
                'status' => GroupSwapRequest::STATUS_REJECTED,
                'reject_reason' => $request->reason,
            ]);
            return back()->with('success', 'Swap request declined.')->with('_tab', 'members');
        }

        // Accept — now awaits lecturer approval
        $swap->update(['status' => GroupSwapRequest::STATUS_PENDING_LECTURER]);

        return back()->with('success', 'You accepted the swap. Waiting for lecturer approval.')->with('_tab', 'members');
    }

    /**
     * Lecturer approves or rejects the swap.
     */
    public function lecturerDecide(Request $request, string $tenantSlug, GroupSwapRequest $swap): RedirectResponse
    {
        $course = $swap->fromGroup->groupSet->course;
        $this->authorizeCourseAccess($course);

        if ($swap->status !== GroupSwapRequest::STATUS_PENDING_LECTURER) {
            return back()->with('error', 'This swap is not awaiting your decision.')->with('_tab', 'members');
        }

        $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->action === 'reject') {
            $swap->update([
                'status' => GroupSwapRequest::STATUS_REJECTED,
                'reject_reason' => $request->reason,
                'reviewed_by' => auth()->id(),
            ]);
            return back()->with('success', 'Swap request rejected.')->with('_tab', 'members');
        }

        // Execute the swap atomically
        $this->executeSwap($swap);

        $swap->update([
            'status' => GroupSwapRequest::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
        ]);

        return back()->with('success', 'Swap approved. Members have been moved.')->with('_tab', 'members');
    }

    private function executeSwap(GroupSwapRequest $swap): void
    {
        // Move requester from fromGroup → toGroup
        StudentGroupMember::where('student_group_id', $swap->from_group_id)
            ->where('user_id', $swap->requester_id)
            ->update(['student_group_id' => $swap->to_group_id, 'role' => 'member']);

        // Move target from toGroup → fromGroup
        StudentGroupMember::where('student_group_id', $swap->to_group_id)
            ->where('user_id', $swap->target_user_id)
            ->update(['student_group_id' => $swap->from_group_id, 'role' => 'member']);
    }
}
