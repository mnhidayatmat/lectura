<?php

namespace Tests\Feature\Api\V1\Workspace;

use App\Models\Course;
use App\Models\StudentGroup;
use App\Models\StudentGroupMember;
use App\Models\StudentGroupSet;
use App\Models\User;

trait CreatesWorkspaceGroups
{
    /**
     * @param  array<int, User>  $members
     */
    protected function createGroup(Course $course, array $members, ?User $leader = null, string $name = 'Group A'): StudentGroup
    {
        $set = StudentGroupSet::create([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'type' => 'lecture',
            'name' => 'Project Groups',
            'created_by' => $course->lecturer_id,
        ]);

        $group = StudentGroup::create([
            'student_group_set_id' => $set->id,
            'name' => $name,
        ]);

        foreach ($members as $member) {
            StudentGroupMember::create([
                'student_group_id' => $group->id,
                'user_id' => $member->id,
                'role' => $leader && $leader->is($member) ? 'leader' : 'member',
                'joined_at' => now(),
            ]);
        }

        return $group;
    }
}
