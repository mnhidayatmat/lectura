<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use App\Models\CourseFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CourseFile */
class MaterialItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isStoredFile = $this->material_type === 'file' && $this->storage_path;

        return [
            'id' => $this->id,
            'type' => $this->material_type,
            'title' => $this->file_name,
            'description' => $this->description,
            'file_type' => $this->file_type,
            'size_bytes' => $this->file_size_bytes !== null ? (int) $this->file_size_bytes : null,
            'size_label' => $this->isLink() ? null : $this->formattedSize(),
            'created_at' => $this->created_at?->toIso8601String(),
            'download_url' => $isStoredFile ? route('api.v1.tenant.student.materials.download', [
                'tenant' => app('current_tenant')->slug,
                'course' => $this->course_id,
                'file' => $this->id,
            ]) : null,
            'external_url' => ($this->isLink() || $this->isDriveFile()) ? $this->url : null,
        ];
    }
}
