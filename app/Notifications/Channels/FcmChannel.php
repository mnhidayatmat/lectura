<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Services\Push\FcmClient;
use Illuminate\Notifications\Notification;

/**
 * Pushes a notification to every device the user is signed in on.
 *
 * The notification's toArray() payload is what the app's notification list
 * already understands, so the push carries the same id, kind and related ids
 * and a tap can open what tapping the list entry would.
 */
class FcmChannel
{
    private const RELATED_KEYS = ['assignment_id', 'assessment_id', 'course_id', 'course_code', 'level'];

    public function __construct(private FcmClient $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $this->fcm->isConfigured() || ! method_exists($notifiable, 'deviceTokens')) {
            return;
        }

        $devices = $notifiable->deviceTokens()->get();
        if ($devices->isEmpty()) {
            return;
        }

        $payload = $notification->toArray($notifiable);
        $data = array_map('strval', array_filter([
            'notification_id' => $notification->id,
            'kind' => $payload['type'] ?? null,
            ...array_intersect_key($payload, array_flip(self::RELATED_KEYS)),
        ], fn ($value) => $value !== null));

        foreach ($devices as $device) {
            // A failed push must never break the request that raised the notification.
            try {
                if (! $this->fcm->send($device->token, $payload['title'] ?? 'Lectura', $payload['message'] ?? '', $data)) {
                    $device->delete();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
