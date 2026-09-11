<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Section */
class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'invite_code' => $this->invite_code,
            'capacity' => $this->capacity !== null ? (int) $this->capacity : null,
            'is_active' => (bool) $this->is_active,
            'schedule' => collect($this->schedule ?? [])
                ->map(fn ($slot) => [
                    'day' => $slot['day'] ?? null,
                    'start_time' => $slot['start_time'] ?? null,
                    'end_time' => $slot['end_time'] ?? null,
                    'location' => $slot['location'] ?? null,
                    'type' => $slot['type'] ?? 'lecture',
                ])
                ->values(),
            'academic_term' => $this->whenLoaded('academicTerm', fn () => [
                'id' => $this->academicTerm->id,
                'name' => $this->academicTerm->name,
            ]),
            'lecturers' => $this->whenLoaded('lecturers', fn () => $this->lecturers
                ->map(fn ($lecturer) => ['id' => $lecturer->id, 'name' => $lecturer->name])
                ->values()),
            'active_students_count' => array_key_exists('active_students_count', $attributes)
                ? (int) $attributes['active_students_count']
                : ($this->resource->relationLoaded('activeStudents') ? $this->activeStudents->count() : null),
            'active_session_id' => isset($attributes['active_session_id']) ? (int) $attributes['active_session_id'] : null,
        ];
    }
}
