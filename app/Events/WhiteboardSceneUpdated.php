<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhiteboardSceneUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $whiteboardId,
        public readonly array $elements,
        public readonly ?array $appState,
        public readonly int $version,
        public readonly string $sourceId,
        public readonly int $updatedBy,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('whiteboard.' . $this->whiteboardId)];
    }

    public function broadcastAs(): string
    {
        return 'scene.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'elements' => $this->elements,
            'appState' => $this->appState,
            'version' => $this->version,
            'sourceId' => $this->sourceId,
            'updatedBy' => $this->updatedBy,
        ];
    }
}
