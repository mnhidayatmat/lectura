<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use App\Models\Course;
use Illuminate\Http\Request;

/** @mixin Course */
class CourseDetailResource extends CourseSummaryResource
{
    private bool $isOwner = false;

    public function forOwner(bool $isOwner): static
    {
        $this->isOwner = $isOwner;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'format' => collect(is_array($this->format) ? $this->format : [])->filter()->keys()->values(),
            'is_owner' => $this->isOwner,
            'invite_code' => $this->isOwner ? $this->invite_code : null,
            'total_students' => $this->totalStudents(),
            'programme' => $this->whenLoaded('programme', fn () => [
                'id' => $this->programme->id,
                'name' => $this->programme->name,
            ]),
            'learning_outcomes' => $this->whenLoaded('learningOutcomes', fn () => $this->learningOutcomes
                ->map(fn ($clo) => ['id' => $clo->id, 'code' => $clo->code, 'description' => $clo->description])
                ->values()),
            'topics' => $this->whenLoaded('topics', fn () => $this->topics
                ->map(fn ($topic) => [
                    'id' => $topic->id,
                    'week_number' => $topic->week_number !== null ? (int) $topic->week_number : null,
                    'title' => $topic->title,
                ])
                ->values()),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
        ]);
    }
}
