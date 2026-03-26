<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AttendanceAlert extends Notification
{
    use Queueable;

    public function __construct(
        protected User $student,
        protected string $courseName,
        protected int $missedCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'attendance_alert',
            'title' => 'Absence Alert',
            'message' => "{$this->student->name} has missed {$this->missedCount} sessions in {$this->courseName}",
            'icon' => 'alert',
            'color' => 'red',
        ];
    }
}
