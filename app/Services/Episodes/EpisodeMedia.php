<?php

declare(strict_types=1);

namespace App\Services\Episodes;

use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeCaption;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Pre-signed links to episode videos, posters and series covers.
 *
 * The app loads them with no bearer token (video players and image widgets
 * cannot attach one reliably), so every link is signed and short-lived.
 */
final class EpisodeMedia
{
    public static function disk(?string $name = null): Filesystem
    {
        return Storage::disk($name ?? config('lectura.episodes.disk'));
    }

    /**
     * Object storage serves byte ranges itself. Local disks (even with `serve`)
     * go through our own stream route, which is the one that honours Range.
     */
    public static function isObjectStorage(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 's3';
    }

    public static function expiresAt(): Carbon
    {
        return now()->addMinutes((int) config('lectura.episodes.link_ttl_minutes'));
    }

    public static function streamUrl(Episode $episode, ?Carbon $expires = null): ?string
    {
        if ($episode->isYouTube() || ! $episode->video_path) {
            return null;
        }

        return self::link($episode->video_disk, $episode->video_path, $expires, 'api.v1.watch.episodes.stream', ['episode' => $episode->id]);
    }

    public static function captionUrl(EpisodeCaption $caption, ?Carbon $expires = null): string
    {
        return self::link($caption->disk, $caption->path, $expires, 'api.v1.watch.captions', ['caption' => $caption->id]);
    }

    public static function posterUrl(Episode $episode): ?string
    {
        if ($episode->poster_path) {
            return self::link($episode->video_disk ?? config('lectura.episodes.disk'), $episode->poster_path, null, 'api.v1.watch.episodes.poster', ['episode' => $episode->id]);
        }

        return $episode->isYouTube() && $episode->youtube_video_id
            ? YouTubeLink::thumbnailUrl($episode->youtube_video_id)
            : null;
    }

    public static function coverUrl(CourseSeries $series): ?string
    {
        return $series->cover_path
            ? self::link(config('lectura.episodes.disk'), $series->cover_path, null, 'api.v1.watch.series.cover', ['series' => $series->id])
            : null;
    }

    private static function link(string $disk, string $path, ?Carbon $expires, string $route, array $params): string
    {
        $expires ??= self::expiresAt();

        if (self::isObjectStorage($disk)) {
            return self::disk($disk)->temporaryUrl($path, $expires);
        }

        // Signed relative (verified with `signed:relative`), so a proxy that rewrites
        // the scheme or host cannot invalidate the signature.
        return url(URL::temporarySignedRoute($route, $expires, $params, absolute: false));
    }
}
