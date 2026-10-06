<?php

declare(strict_types=1);

namespace App\Services\Episodes;

use Illuminate\Support\Str;

/**
 * Reads scene markers (and a Quick Check draft) from a storyboard.
 *
 * Accepts the storyboard's markdown table (`| # | Time | Visual | On-screen text | Narration |`)
 * or plain lines such as `S01 0:00 Meet Titis` / `1:24 The rulebook`.
 */
final class StoryboardParser
{
    private const TIME = '(\d{1,2}:\d{2}(?::\d{2})?)';

    /**
     * @return array{
     *     scenes: list<array{code: ?string, title: string, start_seconds: int}>,
     *     check: ?array{at_seconds: int, prompt: ?string, options: list<string>, correct_index: ?int}
     * }
     */
    public function parse(string $text): array
    {
        $scenes = [];
        $check = null;
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

            if ($check === null && Str::contains(Str::lower($row['title'].' '.$row['visual']), 'quick check')) {
                $check = $this->checkDraft($row);
            }
        }

        usort($scenes, fn ($a, $b) => $a['start_seconds'] <=> $b['start_seconds']);

        return ['scenes' => $scenes, 'check' => $check];
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

    private function checkDraft(array $row): array
    {
        $options = [];
        if (preg_match('/options?\s*:\s*([^.]+)/iu', $row['visual'], $m)) {
            $options = array_values(array_filter(array_map(
                fn ($o) => trim($o, " \t\"'“”"),
                preg_split('#\s*/\s*#', $m[1])
            )));
        }

        $correct = null;
        if (preg_match('/["“]([^"”]+)["”]\s+(?:turns|goes|lights up)\s+green/iu', $row['visual'], $m)) {
            $found = array_search(Str::lower(trim($m[1])), array_map(fn ($o) => Str::lower($o), $options), true);
            $correct = $found === false ? null : $found;
        }

        $narration = trim(preg_replace('/^quick check!?\s*/iu', '', $row['narration']), ' .…');

        return [
            'at_seconds' => $row['start'],
            'prompt' => Str::endsWith($narration, '?') ? $narration : null,
            'options' => $options,
            'correct_index' => $correct,
        ];
    }

    private function clean(string $value): string
    {
        $value = preg_replace('/\*\*|__|`/', '', $value);

        return trim(preg_replace('/\s+/u', ' ', strip_tags($value)));
    }
}
