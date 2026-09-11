<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Assessment;

use App\Models\AssessmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssessmentSubmission */
class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $this->status_badge['label'],
            'is_late' => (bool) $this->is_late,
            'is_group_submission' => $this->student_group_id !== null,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'notes' => $this->notes,
            'files' => SubmissionFileResource::collection($this->whenLoaded('files')),
        ];
    }
}
