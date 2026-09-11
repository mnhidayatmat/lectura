<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Workspace;

use App\Models\StudentGroupPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentGroupPost */
class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $name = (string) $this->user?->name;

        return [
            'id' => $this->id,
            'body' => $this->body,
            'user' => [
                'id' => (int) $this->user_id,
                'name' => $this->user?->name,
                'initial' => strtoupper(substr($name, 0, 1)),
                'avatar_url' => $this->user?->avatar_url,
            ],
            'is_mine' => (int) $this->user_id === (int) $request->user()?->id,
            'is_edited' => $this->wasChanged('body') || $this->updated_at->gt($this->created_at),
            'sent_at' => $this->created_at->toIso8601String(),
            'sent_at_label' => $this->created_at->format('H:i'),
        ];
    }
}
