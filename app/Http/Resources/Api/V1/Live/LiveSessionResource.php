<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Live;

use App\Models\ActiveLearningSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ActiveLearningSession */
class LiveSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->plan;
        $course = $plan?->course;
        $attributes = $this->resource->getAttributes();

        $totalActivities = 0;
        if ($plan) {
            $totalActivities = array_key_exists('activities_count', $plan->getAttributes())
                ? (int) $plan->getAttributes()['activities_count']
                : $plan->activities()->count();
        }

        return [
            'id' => $this->id,
            'type' => 'session',
            'title' => $plan?->title,
            'status' => $this->status,
            'join_code' => $this->join_code,
            'course' => $course ? [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
            ] : null,
            'week_number' => $plan?->week_number !== null ? (int) $plan->week_number : null,
            'total_activities' => $totalActivities,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'joined' => array_key_exists('joined', $attributes)
                ? (bool) $attributes['joined']
                : $this->participants()->where('user_id', $request->user()?->id)->exists(),
        ];
    }
}
