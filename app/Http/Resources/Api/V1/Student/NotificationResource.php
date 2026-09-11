<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    private const RELATED_KEYS = ['assignment_id', 'assessment_id', 'course_id', 'course_code', 'level'];

    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id' => $this->id,
            'type' => class_basename($this->type),
            'kind' => $data['type'] ?? null,
            'title' => $data['title'] ?? 'Notification',
            'body' => $data['message'] ?? '',
            'icon' => $data['icon'] ?? 'document',
            'color' => $data['color'] ?? 'slate',
            'related' => (object) Arr::only($data, self::RELATED_KEYS),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
