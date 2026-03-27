<?php

declare(strict_types=1);

namespace App\Events\ActiveLearning;

use App\Models\ActiveLearningSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ActiveLearningSession $session,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('active-learning-session.' . $this->session->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'status' => 'completed',
            'ended_at' => $this->session->ended_at?->toISOString(),
            'summary_data' => $this->session->summary_data,
        ];
    }
}
