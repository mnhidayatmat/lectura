<?php

declare(strict_types=1);

namespace App\Services\Episodes;

/**
 * Pulls the 11-character video id out of the links lecturers paste:
 * watch?v=, youtu.be/, /embed/, /shorts/, /live/, m. and music. hosts, or a bare id.
 */
final class YouTubeLink
{
    private const ID = '[A-Za-z0-9_-]{11}';

    public static function videoId(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        if (preg_match('/^'.self::ID.'$/', $input)) {
            return $input;
        }

        if (! preg_match('#^https?://#i', $input)) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);
        $host = strtolower(preg_replace('/^(www|m|music)\./', '', $parts['host'] ?? ''));
        $path = $parts['path'] ?? '';

        if ($host === 'youtu.be') {
            return preg_match('#^/('.self::ID.')#', $path, $m) ? $m[1] : null;
        }

        if (! in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            return null;
        }

        if ($path === '/watch') {
            parse_str($parts['query'] ?? '', $query);
            $v = is_string($query['v'] ?? null) ? $query['v'] : '';

            return preg_match('/^'.self::ID.'$/', $v) ? $v : null;
        }

        return preg_match('#^/(?:embed|shorts|live|v)/('.self::ID.')#', $path, $m) ? $m[1] : null;
    }

    public static function watchUrl(string $videoId): string
    {
        return "https://www.youtube.com/watch?v={$videoId}";
    }

    public static function embedUrl(string $videoId): string
    {
        return "https://www.youtube-nocookie.com/embed/{$videoId}?rel=0&enablejsapi=1";
    }

    public static function thumbnailUrl(string $videoId): string
    {
        return "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg";
    }
}
