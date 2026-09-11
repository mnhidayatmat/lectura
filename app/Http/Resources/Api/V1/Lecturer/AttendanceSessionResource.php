<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use App\Models\AttendanceSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceSession */
class AttendanceSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $section = $this->section;
        $course = $section?->course;
        $sectionAttributes = $section?->getAttributes() ?? [];

        return [
            'id' => $this->id,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'session_type' => $this->session_type,
            'week_number' => $this->week_number !== null ? (int) $this->week_number : null,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'course' => $course ? ['id' => $course->id, 'code' => $course->code, 'title' => $course->title] : null,
            'section' => $section ? ['id' => $section->id, 'name' => $section->name, 'code' => $section->code] : null,
            'counts' => $this->counts(),
            'total_students' => array_key_exists('active_students_count', $sectionAttributes)
                ? (int) $sectionAttributes['active_students_count']
                : null,
        ];
    }

    /**
     * Uses loaded records when present, otherwise the `records as {status}_count` aggregates.
     */
    protected function counts(): array
    {
        if ($this->resource->relationLoaded('records')) {
            $byStatus = $this->resource->records->countBy('status');
        } else {
            $attributes = $this->resource->getAttributes();
            $byStatus = collect(['present', 'late', 'absent', 'excused'])
                ->mapWithKeys(fn (string $status) => [$status => $attributes["{$status}_count"] ?? 0]);
        }

        $present = (int) ($byStatus['present'] ?? 0);
        $late = (int) ($byStatus['late'] ?? 0);

        return [
            'present' => $present,
            'late' => $late,
            'absent' => (int) ($byStatus['absent'] ?? 0),
            'excused' => (int) ($byStatus['excused'] ?? 0),
            'checked_in' => $present + $late,
        ];
    }
}
