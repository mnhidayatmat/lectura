<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\StudentGroupPost;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly StudentGroupPost $message,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('group.' . $this->message->student_group_id . '.chat'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'user_id' => $this->message->user_id,
            'user_name' => $this->message->user->name,
            'user_initial' => strtoupper(substr($this->message->user->name, 0, 1)),
            'sent_at' => $this->message->created_at->format('H:i'),
        ];
    }
}
