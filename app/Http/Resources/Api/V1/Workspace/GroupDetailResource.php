<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Workspace;

use App\Models\GroupTask;
use App\Models\StudentGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin StudentGroup */
class GroupDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $attributes = $this->resource->getAttributes();
        $membership = $this->members->first(fn ($member) => (int) $member->user_id === (int) $user->id);
        $isLeader = $membership?->role === 'leader';
        $set = $this->groupSet;
        $course = $set->course;
        $tasks = $this->tasks;
        $deadline = $this->project_deadline ? Carbon::parse($this->project_deadline) : null;
        $scoreReleased = $this->score_released_at !== null && $this->score !== null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'color_tag' => $this->color_tag,
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'academic_term' => $course->academicTerm?->name,
            ],
            'group_set' => [
                'id' => $set->id,
                'name' => $set->name,
                'type' => $set->type,
                'type_label' => ucfirst((string) $set->type),
            ],
            'project' => [
                'title' => $this->project_title,
                'description' => $this->project_description,
                'deadline' => $deadline?->toDateString(),
                'is_deadline_past' => $deadline !== null && $deadline->endOfDay()->isPast(),
                'whatsapp_link' => $this->whatsapp_link,
            ],
            'score' => $scoreReleased ? [
                'score' => (float) $this->score,
                'score_max' => $this->score_max !== null ? (float) $this->score_max : null,
                'remarks' => $this->score_remarks,
                'released_at' => Carbon::parse($this->score_released_at)->toIso8601String(),
            ] : null,
            'members' => $this->members
                ->sortByDesc(fn ($member) => $member->role === 'leader')
                ->map(fn ($member) => [
                    'id' => (int) $member->user_id,
                    'name' => $member->user?->name,
                    'initial' => strtoupper(substr((string) $member->user?->name, 0, 1)),
                    'avatar_url' => $member->user?->avatar_url,
                    'role' => $member->role,
                    'is_me' => (int) $member->user_id === (int) $user->id,
                ])
                ->values(),
            'my_role' => $membership?->role,
            'is_leader' => $isLeader,
            'vote_in_progress' => $this->relationLoaded('voteRounds') && $this->voteRounds->contains('status', 'open'),
            'counts' => [
                'members' => $this->members->count(),
                'messages' => (int) ($attributes['messages_count'] ?? 0),
                'files' => (int) ($attributes['files_count'] ?? 0),
                'folders' => (int) ($attributes['folders_count'] ?? 0),
                'tasks' => $tasks->count(),
                'tasks_done' => $tasks->where('status', 'done')->count(),
                'tasks_overdue' => $tasks->filter(fn (GroupTask $task) => $task->isOverdue())->count(),
            ],
            'permissions' => [
                'can_chat' => true,
                'can_upload_files' => true,
                'can_create_folders' => true,
                'can_create_tasks' => true,
                'can_delete_any_task' => $isLeader,
                'can_delete_any_file' => $isLeader,
            ],
            'uses_google_drive' => $user->isDriveConnected(),
        ];
    }
}
