<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Workspace;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\GroupSwapRequest;
use App\Models\StudentGroup;
use App\Models\StudentGroupSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    use AuthorizesCourseAccess;
    /**
     * Workspace landing: list all groups the student belongs to.
     */
    public function index(): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        // Query from tenant-scoped StudentGroupSet downward to avoid
        // eager-loading scope issues when going up from StudentGroupMember
        $sets = StudentGroupSet::whereHas('groups.members', fn ($q) => $q->where('user_id', $user->id))
            ->with([
                'course.academicTerm',
                'groups' => fn ($q) => $q->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id))
                    ->with(['members', 'groupSet']),
            ])
            ->get()
            ->groupBy('course_id');

        return view('tenant.workspace.index', compact('tenant', 'sets'));
    }

    /**
     * Workspace show: the full group workspace.
     */
    public function show(string $tenantSlug, StudentGroup $group): View
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            abort(403, 'You are not a member of this group.');
        }

        $group->load([
            'groupSet.course.academicTerm',
            'members.user',
            'folders.files.uploader',
            'files' => fn ($q) => $q->whereNull('folder_id')->with('uploader'),
            'tasks.assignee',
            'tasks.creator',
            'minutes.author',
            'voteRounds.votes',
            'voteRounds.winner',
        ]);

        $myMembership = $group->members->firstWhere('user_id', $user->id);
        $activeVoteRound = $group->voteRounds->firstWhere('status', 'open');

        // Pending swap requests involving me
        $myPendingSwap = GroupSwapRequest::where(function ($q) use ($user) {
            $q->where('requester_id', $user->id)
              ->orWhere('target_user_id', $user->id);
        })->whereIn('status', [GroupSwapRequest::STATUS_PENDING_MEMBER, GroupSwapRequest::STATUS_PENDING_LECTURER])
          ->with(['requester', 'targetUser', 'fromGroup', 'toGroup'])
          ->first();

        // Other groups in the same group set for swap requests
        $otherGroups = $group->groupSet->groups()
            ->where('id', '!=', $group->id)
            ->with('members.user')
            ->get();

        $isDriveConnected = $user->isDriveConnected();

        return view('tenant.workspace.show', compact(
            'group', 'myMembership', 'activeVoteRound', 'myPendingSwap', 'otherGroups', 'isDriveConnected'
        ));
    }

    /**
     * Update project details (title, description, deadline, whatsapp_link).
     */
    public function updateProject(Request $request, string $tenantSlug, StudentGroup $group): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            abort(403);
        }

        $request->validate([
            'project_title' => ['nullable', 'string', 'max:150'],
            'project_description' => ['nullable', 'string', 'max:2000'],
            'project_deadline' => ['nullable', 'date'],
            'whatsapp_link' => ['nullable', 'url', 'max:500'],
        ]);

        $group->update($request->only('project_title', 'project_description', 'project_deadline', 'whatsapp_link'));

        return back()->with('success', 'Project details updated.')->with('_tab', 'overview');
    }

    /**
     * Lecturer: release or update group score.
     */
    public function updateScore(Request $request, string $tenantSlug, StudentGroup $group): RedirectResponse
    {
        $course = $group->groupSet->course;
        $this->authorizeCourseAccess($course);

        $request->validate([
            'score' => ['required', 'numeric', 'min:0'],
            'score_max' => ['required', 'numeric', 'min:0.1'],
            'score_remarks' => ['nullable', 'string', 'max:1000'],
            'release' => ['boolean'],
        ]);

        $group->update([
            'score' => $request->score,
            'score_max' => $request->score_max,
            'score_remarks' => $request->score_remarks,
            'score_released_at' => $request->boolean('release') ? now() : null,
            'score_by' => auth()->id(),
        ]);

        return back()->with('success', 'Group score saved.')->with('_tab', 'overview');
    }
}
