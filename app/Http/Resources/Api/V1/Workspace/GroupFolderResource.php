<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Workspace;

use App\Models\StudentGroupFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentGroupFolder */
class GroupFolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fileCount = (int) ($this->resource->getAttributes()['files_count'] ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'file_count' => $fileCount,
            'is_synced_to_drive' => $this->drive_folder_id !== null,
            'created_by' => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'can_delete' => $fileCount === 0,
        ];
    }
}
