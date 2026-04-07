<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssessmentSubmissionReceived extends Notification
{
    use Queueable;

    public function __construct(
        protected Assessment $assessment,
        protected User $student,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'assessment_submission_received',
            'title' => 'New Assessment Submission',
            'message' => "{$this->student->name} submitted {$this->assessment->title}",
            'assessment_id' => $this->assessment->id,
            'icon' => 'upload',
            'color' => 'indigo',
        ];
    }
}
