<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Workspace;

use App\Models\StudentGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentGroup */
class GroupSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membership = $this->members->first(fn ($member) => (int) $member->user_id === (int) $request->user()->id);
        $set = $this->groupSet;
        $course = $set->course;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'color_tag' => $this->color_tag,
            'project_title' => $this->project_title,
            'member_count' => (int) ($this->resource->getAttributes()['members_count'] ?? $this->members->count()),
            'my_role' => $membership?->role,
            'is_leader' => $membership?->role === 'leader',
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'academic_term' => $course->academicTerm?->name,
            ],
            'group_set' => [
                'id' => $set->id,
                'name' => $set->name,
                'type' => $set->type,
                'type_label' => ucfirst((string) $set->type),
            ],
        ];
    }
}
