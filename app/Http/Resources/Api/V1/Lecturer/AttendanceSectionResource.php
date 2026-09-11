<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Section */
class AttendanceSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'course' => $this->course ? [
                'id' => $this->course->id,
                'code' => $this->course->code,
                'title' => $this->course->title,
            ] : null,
            'active_session_id' => isset($attributes['active_session_id']) ? (int) $attributes['active_session_id'] : null,
        ];
    }
}
