<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssignmentPublished extends Notification
{
    use Queueable;

    public function __construct(
        protected Assignment $assignment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Assignment: {$this->assignment->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new assignment has been published in {$this->assignment->course->code}.")
            ->line("**{$this->assignment->title}** — {$this->assignment->total_marks} marks")
            ->line($this->assignment->deadline ? "Deadline: {$this->assignment->deadline->format('d M Y, H:i')}" : 'No deadline set.')
            ->action('View Assignment', '#')
            ->line('Good luck!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'assignment_published',
            'title' => 'New Assignment',
            'message' => "{$this->assignment->title} — {$this->assignment->course->code}",
            'assignment_id' => $this->assignment->id,
            'course_code' => $this->assignment->course->code,
            'icon' => 'document',
            'color' => 'amber',
        ];
    }
}
