<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubmissionReceived extends Notification
{
    use Queueable;

    public function __construct(
        protected Assignment $assignment,
        protected User $student,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'submission_received',
            'title' => 'New Submission',
            'message' => "{$this->student->name} submitted {$this->assignment->title}",
            'assignment_id' => $this->assignment->id,
            'icon' => 'upload',
            'color' => 'indigo',
        ];
    }
}
