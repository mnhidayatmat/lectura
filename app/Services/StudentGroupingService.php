<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\StudentGroup;
use App\Models\StudentGroupMember;
use App\Models\StudentGroupSet;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentGroupingService
{
    public function getEnrolledStudents(Course $course, ?Section $section = null): Collection
    {
        if ($section) {
            $userIds = SectionStudent::where('section_id', $section->id)
                ->where('is_active', true)
                ->pluck('user_id');
        } else {
            $userIds = SectionStudent::whereIn('section_id', $course->sections()->pluck('id'))
                ->where('is_active', true)
                ->distinct()
                ->pluck('user_id');
        }

        return User::whereIn('id', $userIds)->orderBy('name')->get();
    }

    public function getUnassignedStudents(StudentGroupSet $set): Collection
    {
        $assignedIds = StudentGroupMember::whereIn(
            'student_group_id',
            $set->groups()->pluck('id')
        )->pluck('user_id');

        $allStudents = $this->getEnrolledStudents($set->course, $set->section);

        return $allStudents->reject(fn (User $u) => $assignedIds->contains($u->id));
    }

    public function arrangeRandom(StudentGroupSet $set, int $groupSize): void
    {
        $students = $this->getEnrolledStudents($set->course, $set->section)->shuffle();

        if ($students->isEmpty() || $groupSize < 1) {
            return;
        }

        // Clear existing groups and members
        $set->groups()->delete();

        $chunks = $students->chunk($groupSize);
        $colors = ['#6366f1', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'];

        foreach ($chunks as $i => $members) {
            $group = StudentGroup::create([
                'student_group_set_id' => $set->id,
                'name' => 'Group ' . ($i + 1),
                'color_tag' => $colors[$i % count($colors)],
                'sort_order' => $i,
            ]);

            foreach ($members as $student) {
                StudentGroupMember::create([
                    'student_group_id' => $group->id,
                    'user_id' => $student->id,
                    'role' => 'member',
                ]);
            }
        }

        $set->update(['max_group_size' => $groupSize]);
    }

    public function addMember(StudentGroup $group, User $student, string $role = 'member'): void
    {
        StudentGroupMember::firstOrCreate(
            ['student_group_id' => $group->id, 'user_id' => $student->id],
            ['role' => $role]
        );
    }

    public function removeMember(StudentGroup $group, User $student): void
    {
        StudentGroupMember::where('student_group_id', $group->id)
            ->where('user_id', $student->id)
            ->delete();
    }
}
