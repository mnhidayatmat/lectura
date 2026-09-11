<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Workspace;

use App\Models\StudentGroupFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentGroupFile */
class GroupFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $isLeader = $this->relationLoaded('group')
            && $this->group->members->contains(fn ($member) => (int) $member->user_id === $userId && $member->role === 'leader');
        $isDrive = $this->isDriveFile();

        return [
            'id' => $this->id,
            'name' => $this->file_name,
            'extension' => $this->file_type ? strtolower($this->file_type) : null,
            'size_bytes' => (int) $this->file_size_bytes,
            'size_label' => $this->formattedSize(),
            'description' => $this->description,
            'folder_id' => $this->folder_id !== null ? (int) $this->folder_id : null,
            'storage' => $isDrive ? 'google_drive' : 'local',
            'uploaded_by' => $this->uploader ? ['id' => $this->uploader->id, 'name' => $this->uploader->name] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'download_url' => $isDrive ? null : route('api.v1.tenant.workspace.files.download', [
                'tenant' => app('current_tenant')->slug,
                'group' => $this->student_group_id,
                'file' => $this->id,
            ]),
            'external_url' => $isDrive ? $this->drive_web_link : null,
            'can_delete' => (int) $this->uploaded_by === $userId || $isLeader,
        ];
    }
}
