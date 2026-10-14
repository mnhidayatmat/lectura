<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent the moment the lecturer spins, so every phone in the class opens the wheel and
 * watches it turn. It never names the winner; RandomWheelPicked follows once it lands.
 */
class RandomWheelSpinning extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $spinId,
        protected ?int $courseId,
        protected string $courseCode,
    ) {}

    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'random_wheel_spin',
            'title' => 'Random wheel'.($this->courseCode !== '' ? " · {$this->courseCode}" : ''),
            'message' => 'Your lecturer is spinning the wheel. Watch it live!',
            'spin_id' => $this->spinId,
            'course_id' => $this->courseId,
            'course_code' => $this->courseCode,
        ];
    }
}
