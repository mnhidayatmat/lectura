<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Assignment;
use App\Models\StudentMark;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackReleased extends Notification
{
    use Queueable;

    public function __construct(
        protected Assignment $assignment,
        protected StudentMark $mark,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Marks Released: {$this->assignment->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your marks for **{$this->assignment->title}** are now available.")
            ->line("Score: {$this->mark->total_marks} / {$this->mark->max_marks} ({$this->mark->percentage}%)")
            ->action('View Feedback', '#')
            ->line('Check your feedback for improvement tips.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'feedback_released',
            'title' => 'Marks Released',
            'message' => "{$this->assignment->title} — {$this->mark->total_marks}/{$this->mark->max_marks}",
            'assignment_id' => $this->assignment->id,
            'icon' => 'chart',
            'color' => 'emerald',
        ];
    }
}
