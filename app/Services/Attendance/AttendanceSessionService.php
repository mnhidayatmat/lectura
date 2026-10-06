<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\SectionStudent;

class AttendanceSessionService
{
    public function __construct(
        protected AttendanceWarningService $warningService,
    ) {}

    /**
     * End the session, mark every enrolled student who never checked in absent and
     * re-check the course's attendance warnings. Returns how many were marked absent.
     */
    public function end(AttendanceSession $session): int
    {
        $session->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        $checkedInUserIds = $session->records()->pluck('user_id');
        $absentUserIds = SectionStudent::where('section_id', $session->section_id)
            ->where('is_active', true)
            ->whereNotIn('user_id', $checkedInUserIds)
            ->pluck('user_id');

        foreach ($absentUserIds as $userId) {
            AttendanceRecord::create([
                'attendance_session_id' => $session->id,
                'user_id' => $userId,
                'status' => 'absent',
                'method' => 'manual',
            ]);
        }

        if ($course = $session->section?->course) {
            $this->warningService->checkAndIssueWarnings($course);
        }

        return $absentUserIds->count();
    }
}
