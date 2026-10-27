<?php

declare(strict_types=1);

namespace App\Services\Tender;

/**
 * The rules a tender game is played by. `piping` is the BTG3333 Piping Design
 * Tender (levels 1 to 4, the criteria due each session); `ophir` is the BTG2663
 * Ophir Offshore Tender (a band, then a mark inside it, on the same seven
 * criteria every week; four Judges, two per company; two retrenched per loss;
 * student Judges at the Grand Final).
 */
final class TenderRules
{
    public const RULESETS = ['piping', 'ophir'];

    private const OPHIR_CRITERIA = [
        'technical' => 25.0,
        'compliance' => 25.0,
        'data' => 10.0,
        'integration' => 10.0,
        'defence' => 10.0,
        'communication' => 10.0,
        'progress' => 10.0,
    ];

    private const OPHIR_LABELS = [
        'technical' => 'Technical correctness and depth',
        'compliance' => 'Compliance and standards',
        'data' => 'Data judgement',
        'integration' => 'Integration across sub-teams',
        'defence' => 'Questions and defence',
        'communication' => 'Communication and teamwork',
        'progress' => 'Progress and deliverable',
    ];

    /** Share of a criterion's maximum where each band starts, best first. */
    public const BANDS = ['Excellent' => 0.9, 'Good' => 0.7, 'Fair' => 0.5, 'Poor' => 0.0];

    private function __construct(public readonly string $key) {}

    public static function for(?string $ruleset): self
    {
        return new self(in_array($ruleset, self::RULESETS, true) ? $ruleset : 'piping');
    }

    public function isOphir(): bool
    {
        return $this->key === 'ophir';
    }

    /** @return array<string, int> session code => default week */
    public function defaultWeeks(): array
    {
        return $this->isOphir()
            ? ['S1' => 5, 'S2' => 6, 'S3' => 7, 'S4' => 8, 'S5' => 9, 'S6' => 10, 'GF' => 11]
            : TenderCriteria::DEFAULT_WEEKS;
    }

    /** @return array<string, float> criterion => maximum marks */
    public function criteria(string $code): array
    {
        return $this->isOphir() ? self::OPHIR_CRITERIA : TenderCriteria::forSession($code);
    }

    public function label(string $criterion): string
    {
        return $this->isOphir() ? (self::OPHIR_LABELS[$criterion] ?? $criterion) : TenderCriteria::label($criterion);
    }

    /** `levels` (1 to 4, compliance from 0) or `marks` (0 to the criterion's maximum). */
    public function scale(): string
    {
        return $this->isOphir() ? 'marks' : 'levels';
    }

    public function minScore(string $criterion): int
    {
        return $this->isOphir() ? 0 : TenderCriteria::minLevel($criterion);
    }

    public function maxScore(float $max): int
    {
        return $this->isOphir() ? (int) floor($max) : 4;
    }

    /** A Judge's score as marks out of the criterion's maximum. */
    public function marks(int $score, float $max): float
    {
        return $this->isOphir() ? (float) $score : $score / 4 * $max;
    }

    /** Whether the two Judges are far enough apart on one row to be flagged. */
    public function flagged(int $first, int $second, float $max): bool
    {
        return $this->isOphir()
            ? abs($first - $second) >= 0.2 * $max
            : abs($first - $second) >= TenderCompiler::LEVEL_GAP;
    }

    /** @return list<array{label: string, min: int, max: int}> whole-mark bands, best first */
    public function bands(float $max): array
    {
        if (! $this->isOphir()) {
            return [];
        }

        $top = (int) floor($max);
        $bands = [];
        foreach (self::BANDS as $label => $share) {
            $from = (int) ceil($share * $max);
            $bands[] = ['label' => $label, 'min' => $from, 'max' => $top];
            $top = $from - 1;
        }

        return $bands;
    }

    /** @return list<string> tie-break order after equal totals; then sudden death */
    public function tieBreak(): array
    {
        return $this->isOphir() ? ['compliance', 'defence', 'technical'] : TenderCriteria::TIE_BREAK;
    }

    /** Criteria every company member carries into marks; the rest are a first read. */
    public function companyCriteria(): array
    {
        return $this->isOphir() ? array_keys(self::OPHIR_CRITERIA) : TenderCriteria::COMPANY_CRITERIA;
    }

    /**
     * Judge slots and the company each Judge comes from. Piping has one Judge per
     * company; Ophir two, alternating so slots 1 and 2 still mean A and B.
     *
     * @return array<int, string>
     */
    public function judgeSlots(): array
    {
        return $this->isOphir() ? [1 => 'A', 2 => 'B', 3 => 'A', 4 => 'B'] : [1 => 'A', 2 => 'B'];
    }

    /** A company is never retrenched below this. */
    public function minCompany(): int
    {
        return $this->isOphir() ? 9 : TenderFlow::MIN_COMPANY;
    }

    /** The size a company plays with at most (a roster hint for Ophir, a hire cap for Piping). */
    public function maxCompany(): int
    {
        return $this->isOphir() ? 15 : TenderFlow::MAX_COMPANY;
    }

    /** No group is retrenched below this. */
    public function minGroup(): int
    {
        return $this->isOphir() ? 1 : TenderFlow::MIN_GROUP;
    }

    /** Students the losing MD names after each retrenching session. */
    public function retrenchCount(): int
    {
        return $this->isOphir() ? 2 : 1;
    }

    /** @return list<string> sessions after which the loser retrenches */
    public function retrenchingSessions(): array
    {
        return $this->isOphir() ? ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'] : ['S1', 'S2', 'S3', 'S4', 'S5'];
    }

    /** Piping's Grand Final is scored by staff; Ophir's by student Judges like every week. */
    public function staffFinal(): bool
    {
        return ! $this->isOphir();
    }

    /** What the Leader's setup note is called. */
    public function noteLabel(): string
    {
        return $this->isOphir() ? 'Mission' : 'Change Order';
    }

    public function flagRule(): string
    {
        return $this->isOphir()
            ? 'The Judges differ by 20% of the criterion or more'
            : 'The Judges differ by 2 or more levels';
    }

    public function retrenchRule(): string
    {
        return $this->isOphir()
            ? 'That student cannot be retrenched: no group may go to zero and no company below 9.'
            : 'That student cannot be retrenched: no group may go below 2 and no company below 4.';
    }

    /** The session payload's `rules` object, so the app needs no ruleset of its own. */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'scale' => $this->scale(),
            'note_label' => $this->noteLabel(),
            'flag_rule' => $this->flagRule(),
            'retrench_count' => $this->retrenchCount(),
            'judge_slots' => $this->judgeSlots(),
        ];
    }
}
