<?php

declare(strict_types=1);

namespace App\Events\ActiveLearning;

use App\Models\ActiveLearningSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResponseSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ActiveLearningSession $session,
        public int $activityId,
        public int $responseCount,
        public int $participantCount,
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
            'activity_id' => $this->activityId,
            'response_count' => $this->responseCount,
            'participant_count' => $this->participantCount,
        ];
    }
}
