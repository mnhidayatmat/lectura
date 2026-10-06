<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Episode;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EpisodePublished extends Notification
{
    use Queueable;

    public function __construct(protected Episode $episode) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $episode = $this->episode;
        $course = $episode->course;
        $due = $episode->required_by
            ? ' Watch it before '.$episode->required_by->copy()->timezone($episode->tenant?->timezone ?: config('app.timezone'))->format('D j M, g:i A').'.'
            : '';

        return [
            'type' => 'episode_published',
            'title' => "New episode: {$episode->title}",
            'message' => "Episode {$episode->episode_number} of {$episode->series?->title} is ready in {$course?->code}.{$due}",
            'icon' => 'play',
            'color' => 'violet',
            'episode_id' => $episode->id,
            'series_id' => $episode->course_series_id,
            'course_id' => $episode->course_id,
            'course_code' => $course?->code,
        ];
    }
}
