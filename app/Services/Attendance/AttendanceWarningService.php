<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceWarning;
use App\Models\Course;
use App\Models\Section;
use App\Models\User;
use App\Notifications\AttendanceWarningNotification;

class AttendanceWarningService
{
    /**
     * Check and issue warnings for all students in a course after a session ends.
     */
    public function checkAndIssueWarnings(Course $course): void
    {
        $policy = $course->attendancePolicy;

        if (! $policy) {
            return;
        }

        $sectionIds = $course->sections()->pluck('id');

        $totalSessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'ended')
            ->count();

        if ($totalSessions === 0) {
            return;
        }

        // Get all students enrolled in any section of this course
        $studentIds = \App\Models\SectionStudent::whereIn('section_id', $sectionIds)
            ->where('is_active', true)
            ->distinct()
            ->pluck('user_id');

        $sessionIds = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'ended')
            ->pluck('id');

        foreach ($studentIds as $studentId) {
            $this->checkStudentWarnings($policy, $course->id, $studentId, $sessionIds, $totalSessions);
        }
    }

    /**
     * Get absence summary for a student in a course.
     */
    public function getAbsenceSummary(Course $course, User $student): array
    {
        $sectionIds = $course->sections()->pluck('id');

        $sessionIds = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'ended')
            ->pluck('id');

        $totalSessions = $sessionIds->count();

        $records = AttendanceRecord::where('user_id', $student->id)
            ->whereIn('attendance_session_id', $sessionIds)
            ->selectRaw("status, count(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status');

        $absent = (int) ($records['absent'] ?? 0);
        $excused = (int) ($records['excused'] ?? 0);
        $present = (int) ($records['present'] ?? 0);
        $late = (int) ($records['late'] ?? 0);

        $policy = $course->attendancePolicy;
        $absenceForCalc = $absent;
        if ($policy && $policy->include_late_as_absent) {
            $absenceForCalc += $late;
        }

        $rate = $totalSessions > 0
            ? round(($totalSessions - $absenceForCalc) / $totalSessions * 100, 1)
            : 100;

        $warningLevel = AttendanceWarning::where('course_id', $course->id)
            ->where('user_id', $student->id)
            ->max('policy_level');

        return [
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'excused' => $excused,
            'total_sessions' => $totalSessions,
            'absence_count' => $absenceForCalc,
            'attendance_rate' => $rate,
            'warning_level' => $warningLevel,
        ];
    }

    protected function checkStudentWarnings(
        AttendancePolicy $policy,
        int $courseId,
        int $studentId,
        $sessionIds,
        int $totalSessions,
    ): void {
        $records = AttendanceRecord::where('user_id', $studentId)
            ->whereIn('attendance_session_id', $sessionIds)
            ->pluck('status');

        $absentCount = $records->where(fn ($s) => $s === 'absent')->count();

        if ($policy->include_late_as_absent) {
            $absentCount += $records->where(fn ($s) => $s === 'late')->count();
        }

        $absencePercentage = round(($absentCount / $totalSessions) * 100, 2);

        $thresholds = collect($policy->warning_thresholds)->sortBy('level');

        foreach ($thresholds as $threshold) {
            $thresholdValue = (float) $threshold['value'];
            $level = (int) $threshold['level'];

            $exceeded = $policy->mode === 'percentage'
                ? $absencePercentage >= $thresholdValue
                : $absentCount >= $thresholdValue;

            if (! $exceeded) {
                continue;
            }

            // Check if warning already issued
            $exists = AttendanceWarning::where('course_id', $courseId)
                ->where('user_id', $studentId)
                ->where('policy_level', $level)
                ->exists();

            if ($exists) {
                continue;
            }

            AttendanceWarning::create([
                'course_id' => $courseId,
                'user_id' => $studentId,
                'policy_level' => $level,
                'absence_count' => $absentCount,
                'total_sessions' => $totalSessions,
                'absence_percentage' => $absencePercentage,
                'created_at' => now(),
            ]);

            // Send notifications
            if ($policy->notify_student) {
                $student = User::find($studentId);
                $course = Course::find($courseId);
                if ($student && $course) {
                    $student->notify(new AttendanceWarningNotification(
                        $course,
                        $level,
                        $threshold['label'] ?? "Level {$level}",
                        $absencePercentage,
                        $absentCount,
                    ));
                }
            }
        }
    }
}
