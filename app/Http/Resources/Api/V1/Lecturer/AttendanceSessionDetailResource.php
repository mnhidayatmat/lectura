<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use App\Models\AttendanceSession;
use Illuminate\Http\Request;

/** @mixin AttendanceSession */
class AttendanceSessionDetailResource extends AttendanceSessionResource
{
    /** @var array<int, array{id: int, name: string, student_id_number: ?string}> */
    private array $notCheckedIn = [];

    public function withNotCheckedIn(array $students): static
    {
        $this->notCheckedIn = $students;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'qr_mode' => $this->qr_mode,
            'qr_rotation_seconds' => (int) $this->qr_rotation_seconds,
            'late_threshold_minutes' => (int) $this->late_threshold_minutes,
            'duration_minutes' => $this->started_at
                ? (int) abs($this->started_at->diffInMinutes($this->ended_at ?? now()))
                : null,
            'records' => AttendanceRecordResource::collection($this->whenLoaded('records')),
            'not_checked_in' => $this->notCheckedIn,
        ]);
    }
}
