<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Workspace\GroupDetailResource;
use App\Http\Resources\Api\V1\Workspace\GroupSummaryResource;
use App\Models\StudentGroup;
use App\Models\StudentGroupSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkspaceController extends Controller
{
    use AuthorizesGroupMembership;

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $sets = StudentGroupSet::whereHas('groups.members', fn ($q) => $q->where('user_id', $user->id))
            ->with([
                'course.academicTerm',
                'groups' => fn ($q) => $q->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id))
                    ->withCount('members')
                    ->with('members'),
            ])
            ->get();

        $groups = $sets
            ->filter(fn (StudentGroupSet $set) => $set->course !== null)
            ->flatMap(fn (StudentGroupSet $set) => $set->groups->each(
                fn (StudentGroup $group) => $group->setRelation('groupSet', $set)
            ))
            ->sortBy(fn (StudentGroup $group) => $group->groupSet->course->code.'|'.str_pad((string) $group->sort_order, 4, '0', STR_PAD_LEFT))
            ->values();

        return GroupSummaryResource::collection($groups);
    }

    public function show(Request $request, StudentGroup $group): GroupDetailResource
    {
        $this->authorizeMember($group, $request->user());

        $group->load(['groupSet.course.academicTerm', 'members.user', 'tasks', 'voteRounds' => fn ($q) => $q->where('status', 'open')])
            ->loadCount([
                'posts as messages_count' => fn ($q) => $q->whereNull('parent_id'),
                'files',
                'folders',
            ]);

        return new GroupDetailResource($group);
    }
}
