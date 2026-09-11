<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects transient `student_id_number` and `override_by_name` attributes set by the controller.
 *
 * @mixin AttendanceRecord
 */
class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'student_id_number' => $attributes['student_id_number'] ?? null,
            'status' => $this->status,
            'method' => $this->method,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'override' => $this->override_by ? [
                'by' => ['id' => (int) $this->override_by, 'name' => $attributes['override_by_name'] ?? null],
                'reason' => $this->override_reason,
            ] : null,
            'excuse' => $this->whenLoaded('excuse', fn () => [
                'id' => $this->excuse->id,
                'status' => $this->excuse->status,
                'category' => $this->excuse->category,
            ]),
        ];
    }
}
