<?php

declare(strict_types=1);

namespace App\Services\ActiveLearning;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningGroup;
use App\Models\ActiveLearningGroupMember;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Support\Collection;

class GroupingService
{
    public function createGroup(ActiveLearningActivity $activity, string $name, ?int $attendanceSessionId = null): ActiveLearningGroup
    {
        $maxOrder = $activity->groups()->max('sort_order') ?? -1;

        return ActiveLearningGroup::create([
            'active_learning_activity_id' => $activity->id,
            'attendance_session_id' => $attendanceSessionId,
            'name' => $name,
            'sort_order' => $maxOrder + 1,
        ]);
    }

    public function addMemberToGroup(ActiveLearningGroup $group, User $student, string $role = 'member'): void
    {
        ActiveLearningGroupMember::updateOrCreate(
            [
                'active_learning_group_id' => $group->id,
                'user_id' => $student->id,
            ],
            [
                'role' => $role,
                'assigned_at' => now(),
            ]
        );
    }

    public function removeMemberFromGroup(ActiveLearningGroup $group, User $student): void
    {
        $group->members()->where('user_id', $student->id)->delete();
    }

    public function clearGroups(ActiveLearningActivity $activity): void
    {
        $activity->groups()->delete();
    }

    /**
     * Auto-arrange students from an attendance session into groups.
     *
     * @return ActiveLearningGroup[]
     */
    public function arrangeGroupsFromAttendance(
        ActiveLearningActivity $activity,
        AttendanceSession $session,
        int $groupSize,
        string $strategy = 'random',
    ): array {
        // Get present/late students from attendance
        $studentIds = $session->records()
            ->whereIn('status', ['present', 'late'])
            ->pluck('user_id')
            ->shuffle();

        // Clear existing groups for this activity
        $this->clearGroups($activity);

        // Split into groups
        $chunks = $studentIds->chunk($groupSize);
        $groups = [];
        $groupNumber = 1;

        foreach ($chunks as $chunk) {
            $group = $this->createGroup(
                $activity,
                __('active_learning.group_number', ['number' => $groupNumber]),
                $session->id,
            );

            foreach ($chunk as $index => $studentId) {
                ActiveLearningGroupMember::create([
                    'active_learning_group_id' => $group->id,
                    'user_id' => $studentId,
                    'role' => $index === 0 ? 'facilitator' : 'member',
                    'assigned_at' => now(),
                ]);
            }

            $groups[] = $group;
            $groupNumber++;
        }

        return $groups;
    }

    /**
     * Get unassigned students from an attendance session for manual grouping.
     */
    public function getUnassignedStudents(ActiveLearningActivity $activity, AttendanceSession $session): Collection
    {
        $presentStudentIds = $session->records()
            ->whereIn('status', ['present', 'late'])
            ->pluck('user_id');

        $assignedStudentIds = ActiveLearningGroupMember::whereIn(
            'active_learning_group_id',
            $activity->groups()->pluck('id')
        )->pluck('user_id');

        $unassignedIds = $presentStudentIds->diff($assignedStudentIds);

        return User::whereIn('id', $unassignedIds)->get();
    }
}
