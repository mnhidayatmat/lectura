<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Episode;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EpisodeReminder extends Notification
{
    use Queueable;

    public function __construct(protected Episode $episode, protected string $audience) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $episode = $this->episode;
        $due = $episode->required_by && $episode->required_by->isFuture()
            ? ' before '.$episode->required_by->copy()->timezone($episode->tenant?->timezone ?: config('app.timezone'))->format('D j M, g:i A')
            : '';
        $ask = $this->audience === 'not_finished' ? 'Finish' : 'Watch';

        return [
            'type' => 'episode_reminder',
            'title' => "{$ask} {$episode->title}{$due}",
            'message' => "Episode {$episode->episode_number} of {$episode->series?->title} in {$episode->course?->code} is waiting for you.",
            'icon' => 'play',
            'color' => 'amber',
            'episode_id' => $episode->id,
            'series_id' => $episode->course_series_id,
            'course_id' => $episode->course_id,
            'course_code' => $episode->course?->code,
        ];
    }
}
