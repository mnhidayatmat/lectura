<?php

namespace Tests\Unit;

use App\Services\Episodes\StoryboardParser;
use PHPUnit\Framework\TestCase;

class StoryboardParserTest extends TestCase
{
    private const STORYBOARD = <<<'MD'
# BTG3333 Animated Series — Episode 1: "Titis Leaves Home"

| # | Time | Visual (what students see) | On-screen text | Narration |
|---|---|---|---|---|
| S01 | 0:00–0:14 | Night sea, an offshore platform with lights. | **Meet Titis** | Meet Titis… That's today's story. |
| S05 | 1:24–1:44 | A thick book drops onto a desk with a thud. | ASME B31.3 · para. 300.2 | But engineers can't just… |
| S09 | 2:47–3:00 | A quiz card with three options: Building frame / Pipe hanger / Pump casing. A 3-second countdown, then "Pipe hanger" turns green. | Quick Check | Quick check!… |
| S10 | 3:00–3:08 | Titis zooms off down a pipeline toward the horizon. | Next: Episode 2 — Build It Like LEGO | Titis is on her way… |

## Notes
- Exact scene timings are in `timing.csv`.
MD;

    public function test_reads_scenes_from_the_storyboard_table(): void
    {
        $result = (new StoryboardParser)->parse(self::STORYBOARD);

        $this->assertSame([
            ['code' => 'S01', 'title' => 'Meet Titis', 'start_seconds' => 0],
            ['code' => 'S05', 'title' => 'ASME B31.3 · para. 300.2', 'start_seconds' => 84],
            ['code' => 'S09', 'title' => 'Quick Check', 'start_seconds' => 167],
            ['code' => 'S10', 'title' => 'Next: Episode 2 — Build It Like LEGO', 'start_seconds' => 180],
        ], $result['scenes']);
    }

    public function test_drafts_the_quick_check_from_its_row(): void
    {
        $check = (new StoryboardParser)->parse(self::STORYBOARD)['check'];

        $this->assertSame(167, $check['at_seconds']);
        $this->assertSame(['Building frame', 'Pipe hanger', 'Pump casing'], $check['options']);
        $this->assertSame(1, $check['correct_index']);
        $this->assertNull($check['prompt']);
    }

    public function test_reads_plain_lines_in_any_order(): void
    {
        $result = (new StoryboardParser)->parse("S02 0:14 What piping is\n0:00 Meet Titis\n1:02:03 Long lecture end\nnot a scene");

        $this->assertSame([
            ['code' => null, 'title' => 'Meet Titis', 'start_seconds' => 0],
            ['code' => 'S02', 'title' => 'What piping is', 'start_seconds' => 14],
            ['code' => null, 'title' => 'Long lecture end', 'start_seconds' => 3723],
        ], $result['scenes']);
        $this->assertNull($result['check']);
    }
}
