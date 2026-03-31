<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\AttendanceExcuse;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AttendanceExcuseService
{
    public function submit(AttendanceRecord $record, User $student, array $data, ?UploadedFile $attachment = null): AttendanceExcuse
    {
        $excuseData = [
            'attendance_record_id' => $record->id,
            'user_id' => $student->id,
            'reason' => $data['reason'],
            'category' => $data['category'],
            'status' => 'pending',
        ];

        if ($attachment) {
            $path = $attachment->store("attendance-excuses/{$record->id}", 'local');
            $excuseData['attachment_path'] = $path;
            $excuseData['attachment_filename'] = $attachment->getClientOriginalName();
        }

        return AttendanceExcuse::create($excuseData);
    }

    public function approve(AttendanceExcuse $excuse, User $reviewer, ?string $note = null): void
    {
        $excuse->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reviewer_note' => $note,
        ]);

        $excuse->record->update(['status' => 'excused']);
    }

    public function reject(AttendanceExcuse $excuse, User $reviewer, ?string $note = null): void
    {
        $excuse->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reviewer_note' => $note,
        ]);
    }
}
