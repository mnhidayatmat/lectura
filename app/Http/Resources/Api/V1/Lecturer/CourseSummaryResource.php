<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Course */
class CourseSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();
        $badge = $this->resource->status_badge;

        $term = $this->resource->relationLoaded('academicTerm') ? $this->resource->academicTerm : null;
        $termEnded = $this->status === 'active' && $term?->end_date !== null && $term->end_date->endOfDay()->isPast();
        if ($termEnded) {
            $badge = ['label' => 'Ended', 'color' => 'amber'];
        }

        // An active course with no active section in the current semester reads as inactive
        $notRunning = $this->status === 'active' && ! $termEnded && ! $this->resource->runsInCurrentTerm();
        if ($notRunning) {
            $badge = ['label' => 'Inactive', 'color' => 'red'];
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'status' => $this->status,
            'status_label' => $badge['label'],
            'status_color' => $badge['color'],
            'term_ended' => $termEnded,
            'not_running' => $notRunning,
            'teaching_mode' => $this->teaching_mode,
            'num_weeks' => (int) $this->num_weeks,
            'credit_hours' => $this->credit_hours !== null ? (int) $this->credit_hours : null,
            'sections_count' => array_key_exists('sections_count', $attributes) ? (int) $attributes['sections_count'] : null,
            'academic_term' => $this->whenLoaded('academicTerm', fn () => [
                'id' => $this->academicTerm->id,
                'name' => $this->academicTerm->name,
            ]),
            'faculty' => $this->whenLoaded('faculty', fn () => [
                'id' => $this->faculty->id,
                'name' => $this->faculty->name,
            ]),
        ];
    }
}
