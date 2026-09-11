<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\Api\V1\ApiTestCase;

abstract class LecturerApiTestCase extends ApiTestCase
{
    protected function startAttendance(Section $section, User $lecturer, array $attributes = []): AttendanceSession
    {
        return AttendanceSession::create(array_merge([
            'tenant_id' => $section->tenant_id,
            'section_id' => $section->id,
            'lecturer_id' => $lecturer->id,
            'session_type' => 'lecture',
            'week_number' => 3,
            'qr_secret' => Str::random(64),
            'qr_mode' => 'rotating',
            'qr_rotation_seconds' => 30,
            'late_threshold_minutes' => 15,
            'status' => 'active',
            'started_at' => now()->subMinutes(10),
        ], $attributes));
    }

    protected function markAttendance(AttendanceSession $session, User $student, string $status = 'present', array $attributes = []): AttendanceRecord
    {
        $scanned = in_array($status, ['present', 'late'], true);

        return AttendanceRecord::create(array_merge([
            'attendance_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => $status,
            'checked_in_at' => $scanned ? now() : null,
            'method' => $scanned ? 'qr_scan' : 'manual',
        ], $attributes));
    }
}
