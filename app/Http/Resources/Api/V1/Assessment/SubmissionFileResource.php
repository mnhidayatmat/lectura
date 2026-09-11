<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Assessment;

use App\Models\AssessmentSubmissionFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssessmentSubmissionFile */
class SubmissionFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->file_name,
            'mime_type' => $this->file_type,
            'size_bytes' => (int) $this->file_size_bytes,
            'is_graded_copy' => $this->graded_file_path !== null,
            'has_annotations' => $this->annotated_image_path !== null,
            'download_url' => route('api.v1.tenant.assessments.files.download', [
                'tenant' => app('current_tenant')->slug,
                'file' => $this->id,
            ]),
        ];
    }
}
