<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Assessment;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssessmentPublished extends Notification
{
    use Queueable;

    public function __construct(protected Assessment $assessment) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $assessment = $this->assessment;
        $course = $assessment->course;
        $due = $assessment->due_date
            ? ' Due '.$assessment->due_date->copy()->timezone($assessment->tenant?->timezone ?: config('app.timezone'))->format('D j M, g:i A').'.'
            : '';

        return [
            'type' => 'assessment_published',
            'title' => "New assessment: {$assessment->title}",
            'message' => "{$course?->code} has released {$assessment->title}.{$due}",
            'icon' => 'document',
            'color' => 'amber',
            'assessment_id' => $assessment->id,
            'course_id' => $assessment->course_id,
            'course_code' => $course?->code,
        ];
    }
}
