<?php

namespace Tests\Unit;

use App\Services\Episodes\YouTubeLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YouTubeLinkTest extends TestCase
{
    public static function links(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'watch with extras' => ['https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42s', 'dQw4w9WgXcQ'],
            'share link' => ['https://youtu.be/dQw4w9WgXcQ?si=abc123', 'dQw4w9WgXcQ'],
            'no scheme' => ['youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'embed' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ?feature=shared', 'dQw4w9WgXcQ'],
            'bare id' => ['dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'channel' => ['https://www.youtube.com/@lectura', null],
            'playlist only' => ['https://www.youtube.com/playlist?list=PL123', null],
            'other site' => ['https://vimeo.com/123456789', null],
            'look-alike host' => ['https://notyoutube.com/watch?v=dQw4w9WgXcQ', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('links')]
    public function test_reads_the_video_id(string $input, ?string $expected): void
    {
        $this->assertSame($expected, YouTubeLink::videoId($input));
    }
}
