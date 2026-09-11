<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Workspace;

use App\Models\GroupTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GroupTask */
class GroupTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $isLeader = $this->relationLoaded('group')
            && $this->group->members->contains(fn ($member) => (int) $member->user_id === $userId && $member->role === 'leader');
        $isCreatorOrLeader = (int) $this->created_by === $userId || $isLeader;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'in_progress' => 'In progress',
                'done' => 'Done',
                default => 'To do',
            },
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'assignee' => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null,
            'creator' => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'can_edit' => $isCreatorOrLeader,
            'can_delete' => $isCreatorOrLeader,
        ];
    }
}
