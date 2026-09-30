<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells the class who the random wheel picked. Everyone's phone is alerted; only
 * the picked student keeps it in their notification list.
 */
class RandomWheelPicked extends Notification implements ShouldQueue
{
    use Queueable;

    public const ANDROID_CHANNEL = 'random_wheel';

    public function __construct(
        protected int $spinId,
        protected int $winnerId,
        protected string $winnerName,
        protected ?int $courseId,
        protected string $courseCode,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->isWinner($notifiable) ? ['database', FcmChannel::class] : [FcmChannel::class];
    }

    public function fcmAndroidChannel(): string
    {
        return self::ANDROID_CHANNEL;
    }

    public function toArray(object $notifiable): array
    {
        $winner = $this->isWinner($notifiable);
        $where = $this->courseCode !== '' ? " in {$this->courseCode}" : '';

        return [
            'type' => 'random_wheel_pick',
            'title' => $winner ? "You've been picked!" : 'Random wheel'.($this->courseCode !== '' ? " · {$this->courseCode}" : ''),
            'message' => $winner
                ? "The random wheel picked you{$where}. Get ready!"
                : "{$this->winnerName} was picked{$where}.",
            'spin_id' => $this->spinId,
            'course_id' => $this->courseId,
            'course_code' => $this->courseCode,
            'is_winner' => $winner ? 1 : 0,
            'icon' => 'wheel',
            'color' => 'indigo',
        ];
    }

    private function isWinner(object $notifiable): bool
    {
        return (int) $notifiable->getKey() === $this->winnerId;
    }
}
