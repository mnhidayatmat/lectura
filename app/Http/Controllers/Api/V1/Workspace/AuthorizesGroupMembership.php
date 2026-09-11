<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Workspace;

use App\Models\StudentGroup;
use App\Models\User;

trait AuthorizesGroupMembership
{
    /**
     * StudentGroup has no tenant column; its (tenant-scoped) group set resolves to
     * null for another institution's group, which we treat as not found.
     */
    protected function ensureGroupInTenant(StudentGroup $group): void
    {
        if (! $group->groupSet) {
            abort(404, 'Group not found.');
        }
    }

    protected function authorizeMember(StudentGroup $group, User $user): void
    {
        $this->ensureGroupInTenant($group);

        if (! $group->isMember($user->id)) {
            abort(403, 'You are not a member of this group.');
        }
    }

    protected function isLeader(StudentGroup $group, User $user): bool
    {
        return $group->members()->where('user_id', $user->id)->where('role', 'leader')->exists();
    }
}
