<?php

declare(strict_types=1);

namespace App\Events\ActiveLearning;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityAdvanced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ActiveLearningSession $session,
        public ActiveLearningActivity $activity,
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
            'activity' => [
                'id' => $this->activity->id,
                'title' => $this->activity->title,
                'type' => $this->activity->type,
                'instructions' => $this->activity->instructions,
                'description' => $this->activity->description,
                'duration_minutes' => $this->activity->duration_minutes,
                'response_mode' => $this->activity->response_mode,
                'response_type' => $this->activity->response_type,
                'sort_order' => $this->activity->sort_order,
            ],
        ];
    }
}
