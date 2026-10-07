<?php

declare(strict_types=1);

namespace App\Services\Episodes;

use Illuminate\Support\Str;

/**
 * Reads scene markers (and Quick Check drafts) from a storyboard.
 *
 * A row is a quiz when its title or visual says "Quick Check", "Final trial" or "Quiz", or when
 * its visual lists options. Each one becomes a draft for the lecturer to confirm.
 *
 * Accepts the storyboard's markdown table (`| # | Time | Visual | On-screen text | Narration |`)
 * or plain lines such as `S01 0:00 Meet Titis` / `1:24 The rulebook`.
 */
final class StoryboardParser
{
    private const TIME = '(\d{1,2}:\d{2}(?::\d{2})?)';

    private const QUIZ = '/quick\s*check|final\s*trial|\bquiz\b/iu';

    private const QUIZ_LEAD = '/^(?:quick\s*check|final\s*trial|quiz(?:\s*time)?)\s*[!:.…-]*\s*/iu';

    /**
     * @return array{
     *     scenes: list<array{code: ?string, title: string, start_seconds: int}>,
     *     checks: list<array{at_seconds: int, prompt: ?string, options: list<string>, correct_index: ?int}>
     * }
     */
    public function parse(string $text): array
    {
        $scenes = [];
        $checks = [];
        $columns = null;

        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '|')) {
                $cells = array_map('trim', explode('|', trim($line, '|')));

                if ($this->isDivider($cells)) {
                    continue;
                }
                if ($columns === null && $this->headerColumns($cells) !== null) {
                    $columns = $this->headerColumns($cells);

                    continue;
                }

                $row = $this->tableRow($cells, $columns ?? ['code' => 0, 'time' => 1, 'visual' => 2, 'title' => 3, 'narration' => 4]);
            } else {
                $row = $this->plainLine($line);
            }

            if ($row === null) {
                continue;
            }

            $scenes[] = ['code' => $row['code'], 'title' => $row['title'], 'start_seconds' => $row['start']];

            if ($this->isQuiz($row)) {
                $checks[] = $this->checkDraft($row);
            }
        }

        usort($scenes, fn ($a, $b) => $a['start_seconds'] <=> $b['start_seconds']);
        usort($checks, fn ($a, $b) => $a['at_seconds'] <=> $b['at_seconds']);

        return ['scenes' => $scenes, 'checks' => $checks];
    }

    public static function seconds(string $time): ?int
    {
        if (! preg_match('/^'.self::TIME.'$/', trim($time))) {
            return null;
        }

        $parts = array_map('intval', explode(':', trim($time)));

        return count($parts) === 3
            ? $parts[0] * 3600 + $parts[1] * 60 + $parts[2]
            : $parts[0] * 60 + $parts[1];
    }

    private function isDivider(array $cells): bool
    {
        return collect($cells)->every(fn ($cell) => preg_match('/^:?-{2,}:?$/', $cell) === 1 || $cell === '');
    }

    private function headerColumns(array $cells): ?array
    {
        $lower = array_map(fn ($c) => Str::lower(strip_tags($c)), $cells);
        $time = $this->findColumn($lower, ['time', 'timing', 'start']);

        if ($time === null) {
            return null;
        }

        return [
            'code' => $this->findColumn($lower, ['#', 'scene', 'no', 'no.']) ?? ($time > 0 ? 0 : null),
            'time' => $time,
            'visual' => $this->findColumn($lower, ['visual']),
            'title' => $this->findColumn($lower, ['on-screen text', 'on-screen', 'onscreen', 'title', 'text']),
            'narration' => $this->findColumn($lower, ['narration', 'voice', 'voiceover', 'script']),
        ];
    }

    private function findColumn(array $lower, array $names): ?int
    {
        foreach ($lower as $i => $cell) {
            foreach ($names as $name) {
                if ($cell === $name || Str::startsWith($cell, $name.' ')) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function tableRow(array $cells, array $columns): ?array
    {
        $cell = fn (?int $i) => $i !== null ? ($cells[$i] ?? '') : '';

        if (! preg_match('/'.self::TIME.'/', $cell($columns['time']), $m)) {
            return null;
        }

        $visual = $this->clean($cell($columns['visual']));
        $title = $this->clean($cell($columns['title'])) ?: Str::limit($visual, 60, '…');

        return [
            'code' => $this->clean($cell($columns['code'])) ?: null,
            'start' => self::seconds($m[1]),
            'title' => Str::limit($title ?: 'Scene', 250, '…'),
            'visual' => $visual,
            'narration' => $this->clean($cell($columns['narration'])),
        ];
    }

    private function plainLine(string $line): ?array
    {
        if (! preg_match('/^(?:([A-Za-z]*\d+[A-Za-z]*)\s+)?'.self::TIME.'\s*[-–—|:]?\s*(.+)$/u', $line, $m)) {
            return null;
        }

        return [
            'code' => $m[1] !== '' ? $m[1] : null,
            'start' => self::seconds($m[2]),
            'title' => Str::limit($this->clean($m[3]) ?: 'Scene', 250, '…'),
            'visual' => '',
            'narration' => '',
        ];
    }

    private function isQuiz(array $row): bool
    {
        return preg_match(self::QUIZ, $row['title'].' '.$row['visual']) === 1
            || preg_match('/\boptions?\s*:/iu', $row['visual']) === 1;
    }

    private function checkDraft(array $row): array
    {
        $text = $row['visual'].' '.$row['narration'];
        $options = $this->options($row['visual']) ?: $this->options($row['narration']);

        // A ✓ or "(correct)" after an option marks it, and comes off the label
        $correct = null;
        foreach ($options as $i => $option) {
            $clean = trim(preg_replace('/\s*(?:✓|✔|\((?:correct|answer)\))\s*$/iu', '', $option));
            if ($clean !== $option) {
                $options[$i] = $clean;
                $correct ??= $i;
            }
        }

        $correct ??= $this->correctIndex($text, $options);

        return [
            'at_seconds' => $row['start'],
            'prompt' => $this->question($row),
            'options' => $options,
            'correct_index' => $correct,
        ];
    }

    /**
     * "Options: A / B / C" (or separated by ;), or lettered "A) … B) … C) …".
     *
     * @return list<string>
     */
    private function options(string $text): array
    {
        if (preg_match('/\boptions?\s*:\s*([^.]+)/iu', $text, $m)) {
            return $this->labels(preg_split('#\s*[/;]\s*#u', $m[1]));
        }

        if (preg_match_all('/(?:^|\s)\(?([A-Fa-f])[).]\s+(.+?)(?=\s+\(?[A-Fa-f][).]\s|[.?!](?:\s|$)|$)/u', $text, $m) >= 2) {
            return $this->labels($m[2]);
        }

        return [];
    }

    private function labels(array $parts): array
    {
        return array_values(array_filter(array_map(fn ($o) => trim($o, " \t\"'“”,"), $parts), fn ($o) => $o !== ''));
    }

    /**
     * '"X" turns green', 'Answer: X', 'Correct answer is B' and the like.
     */
    private function correctIndex(string $text, array $options): ?int
    {
        $lower = array_map(fn ($o) => Str::lower($o), $options);

        if (preg_match('/["“]([^"”]+)["”]\s+(?:turns|goes|lights up|glows)\s+green/iu', $text, $m)) {
            $found = array_search(Str::lower(trim($m[1])), $lower, true);

            return $found === false ? null : $found;
        }

        $lead = '\b(?:correct(?:\s+answer)?|answer)\s*(?::|is|=)\s*';

        if (preg_match('/'.$lead.'\(?([A-Fa-f])\)?(?![\w])/iu', $text, $m)) {
            $index = ord(Str::lower($m[1])) - ord('a');

            return $index < count($options) ? $index : null;
        }

        if (preg_match('/'.$lead.'["“]?([^"”.;!?]+)/iu', $text, $m)) {
            $answer = Str::lower(trim($m[1]));

            foreach ($lower as $i => $option) {
                if ($answer === $option || Str::startsWith($answer, $option)) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * The narration's question, else an on-screen question; null when the row asks none.
     */
    private function question(array $row): ?string
    {
        foreach ([$row['narration'], $row['title']] as $text) {
            $text = trim(preg_replace(self::QUIZ_LEAD, '', $text));

            if (preg_match_all('/[^.!?…]*\?/u', $text, $m)) {
                $question = trim(end($m[0]));

                if (mb_strlen($question) > 1) {
                    return Str::ucfirst($question);
                }
            }
        }

        return null;
    }

    private function clean(string $value): string
    {
        $value = preg_replace('/\*\*|__|`/', '', $value);

        return trim(preg_replace('/\s+/u', ' ', strip_tags($value)));
    }
}
