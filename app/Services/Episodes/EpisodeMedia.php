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

    public static function isPublicUrlDisk(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.visibility") === 'public'
            && filled(config("filesystems.disks.{$disk}.url"));
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

    /**
     * Captions always come through our own signed route: the web player fetch()es
     * them, and a bucket or CDN URL on another origin would need CORS rules.
     */
    public static function captionUrl(EpisodeCaption $caption, ?Carbon $expires = null): string
    {
        return url(URL::temporarySignedRoute('api.v1.watch.captions', $expires ?? self::expiresAt(), ['caption' => $caption->id], absolute: false));
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

        // A public disk with a URL (EPISODES_DISK=media: public object storage or a CDN in front
        // of it) serves plain, cacheable URLs. Signed URLs are unique per request and defeat caching.
        if (self::isPublicUrlDisk($disk)) {
            return self::disk($disk)->url($path);
        }

        if (self::isObjectStorage($disk)) {
            return self::disk($disk)->temporaryUrl($path, $expires);
        }

        // Signed relative (verified with `signed:relative`), so a proxy that rewrites
        // the scheme or host cannot invalidate the signature.
        return url(URL::temporarySignedRoute($route, $expires, $params, absolute: false));
    }
}
