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
        $checks = (new StoryboardParser)->parse(self::STORYBOARD)['checks'];
        $this->assertCount(1, $checks);
        $check = $checks[0];

        $this->assertSame(167, $check['at_seconds']);
        $this->assertSame(['Building frame', 'Pipe hanger', 'Pump casing'], $check['options']);
        $this->assertSame(1, $check['correct_index']);
        $this->assertNull($check['prompt']);
    }

    public function test_drafts_every_final_trial_and_quiz_row(): void
    {
        $storyboard = <<<'MD'
| # | Time | Visual | On-screen text | Narration |
|---|---|---|---|---|
| S01 | 0:00 | Titis on the platform. | Meet Titis | Meet Titis. |
| S11 | 4:10 | Three doors: A) Carbon steel B) Stainless steel C) PVC. Answer: B | Final Trial 1 | Final trial! Which material resists chloride best? |
| S12 | 4:40 | Options: 150 psi / 300 psi ✓ / 600 psi | Final Trial 2 | Final trial: which class suits 40 bar? |
| S13 | 5:05 | Quiz card. Options: Gate; Globe; Check. "Check" glows green. | Which valve stops backflow? | Quiz time! |
MD;

        $checks = (new StoryboardParser)->parse($storyboard)['checks'];

        $this->assertSame([
            ['at_seconds' => 250, 'prompt' => 'Which material resists chloride best?', 'options' => ['Carbon steel', 'Stainless steel', 'PVC'], 'correct_index' => 1],
            ['at_seconds' => 280, 'prompt' => 'Which class suits 40 bar?', 'options' => ['150 psi', '300 psi', '600 psi'], 'correct_index' => 1],
            ['at_seconds' => 305, 'prompt' => 'Which valve stops backflow?', 'options' => ['Gate', 'Globe', 'Check'], 'correct_index' => 2],
        ], $checks);
    }

    public function test_reads_plain_lines_in_any_order(): void
    {
        $result = (new StoryboardParser)->parse("S02 0:14 What piping is\n0:00 Meet Titis\n1:02:03 Long lecture end\nnot a scene");

        $this->assertSame([
            ['code' => null, 'title' => 'Meet Titis', 'start_seconds' => 0],
            ['code' => 'S02', 'title' => 'What piping is', 'start_seconds' => 14],
            ['code' => null, 'title' => 'Long lecture end', 'start_seconds' => 3723],
        ], $result['scenes']);
        $this->assertSame([], $result['checks']);
    }
}
