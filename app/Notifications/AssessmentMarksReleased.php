<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssessmentMarksReleased extends Notification
{
    use Queueable;

    public function __construct(
        protected Assessment $assessment,
        protected AssessmentScore $score,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Marks Released: {$this->assessment->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your marks for **{$this->assessment->title}** are now available.")
            ->line("Score: {$this->score->raw_marks} / {$this->score->max_marks} ({$this->score->percentage}%)")
            ->action('View Marks', '#')
            ->line('Check your assessment marks for feedback.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'assessment_marks_released',
            'title' => 'Assessment Marks Released',
            'message' => "{$this->assessment->title} — {$this->score->raw_marks}/{$this->score->max_marks}",
            'assessment_id' => $this->assessment->id,
            'icon' => 'chart',
            'color' => 'emerald',
        ];
    }
}
