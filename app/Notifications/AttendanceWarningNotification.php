<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AttendanceWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Course $course,
        protected int $level,
        protected string $label,
        protected float $absencePercentage,
        protected int $absenceCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'attendance_warning',
            'title' => "Attendance {$this->label}",
            'message' => "You have reached {$this->absencePercentage}% absence ({$this->absenceCount} sessions) in {$this->course->code} {$this->course->title}.",
            'course_id' => $this->course->id,
            'course_code' => $this->course->code,
            'level' => $this->level,
            'icon' => 'warning',
            'color' => $this->level >= 3 ? 'red' : ($this->level >= 2 ? 'amber' : 'yellow'),
        ];
    }
}
