<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Formatting shared by the student Watch pages (`extract(WatchView::helpers($tenant))`).
 */
final class WatchView
{
    /**
     * @return array{tz: string, clock: \Closure, deadline: \Closure, episodeUrl: \Closure}
     */
    public static function helpers(Tenant $tenant): array
    {
        $tz = $tenant->timezone ?: config('app.timezone');

        return [
            'tz' => $tz,
            'clock' => fn (?int $s) => self::clock($s),
            'deadline' => fn (?string $iso) => self::deadline($iso, $tz),
            'episodeUrl' => fn (array $ep) => $ep['is_available']
                ? route('tenant.watch.episode', [$tenant->slug, $ep['id']])
                : route('tenant.watch.series', [$tenant->slug, $ep['series_id']]),
        ];
    }

    public static function clock(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        return $seconds >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * "Watch before Tue", "Watch before 2:00 PM today", "Watch before 20 Oct" or "Overdue".
     */
    public static function deadline(?string $iso, string $tz): ?string
    {
        if (! $iso) {
            return null;
        }

        $at = Carbon::parse($iso)->timezone($tz);
        $now = now()->timezone($tz);

        return match (true) {
            $at->isPast() => 'Overdue',
            $at->isSameDay($now) => 'Watch before '.$at->format('g:i A').' today',
            $at->diffInDays($now, true) < 6 => 'Watch before '.$at->format('D'),
            default => 'Watch before '.$at->format('j M'),
        };
    }
}
