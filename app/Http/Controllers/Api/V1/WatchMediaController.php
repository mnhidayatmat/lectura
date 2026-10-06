<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeCaption;
use App\Services\Episodes\EpisodeMedia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves episode media behind pre-signed URLs (the `signed` middleware is the
 * authorization). Local files go out as BinaryFileResponse, which answers HTTP
 * Range requests, so video players can seek.
 */
class WatchMediaController extends Controller
{
    public function stream(Episode $episode): Response
    {
        return $this->serve($episode->video_disk, $episode->video_path, $episode->video_mime);
    }

    public function poster(Episode $episode): Response
    {
        abort_unless($episode->poster_path, 404);

        return $this->serve($episode->video_disk, $episode->poster_path);
    }

    public function cover(CourseSeries $series): Response
    {
        abort_unless($series->cover_path, 404);

        return $this->serve(config('lectura.episodes.disk'), $series->cover_path);
    }

    public function caption(EpisodeCaption $caption): Response
    {
        return $this->serve($caption->disk, $caption->path, 'text/vtt; charset=utf-8');
    }

    private function serve(string $disk, string $path, ?string $mime = null): Response
    {
        $storage = EpisodeMedia::disk($disk);

        if (! $storage->exists($path)) {
            abort(404, 'File not found.');
        }

        if (EpisodeMedia::isObjectStorage($disk)) {
            return redirect()->away($storage->temporaryUrl($path, EpisodeMedia::expiresAt()));
        }

        return response()->file($storage->path($path), array_filter([
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]));
    }
}
