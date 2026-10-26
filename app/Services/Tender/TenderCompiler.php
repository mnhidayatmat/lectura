<?php

declare(strict_types=1);

namespace App\Services\Tender;

use App\Models\TenderJudge;
use App\Models\TenderSession;
use Illuminate\Support\Collection;

/**
 * The Leader's Compile Sheet (Student Guide, Appendix C), worked out by the server:
 * company mark = (Judge 1 level + Judge 2 level) ÷ 8 × maximum, flags where the
 * Judges differ by 2 or more levels or their totals by more than 10 marks, and
 * the winner with the tie-break.
 */
class TenderCompiler
{
    public const LEVEL_GAP = 2;

    public const TOTALS_GAP = 10.0;

    /**
     * @return array{
     *     criteria: list<array<string, mixed>>,
     *     totals: array{A: float, B: float},
     *     judge_totals: array{A: array<int, float>, B: array<int, float>},
     *     totals_flag: array{A: bool, B: bool},
     *     flag_count: int,
     *     single_judge: bool,
     *     winner: ?string,
     *     tie_break: ?string,
     *     needs_sudden_death: bool
     * }
     */
    public function compile(TenderSession $session): array
    {
        /** @var Collection<int, TenderJudge> $judges */
        $judges = $session->activeJudges()->whereNotNull('submitted_at')->with('scores')->get()->keyBy('slot');

        $rows = [];
        $totals = ['A' => 0.0, 'B' => 0.0];
        $judgeTotals = ['A' => [], 'B' => []];
        $flagCount = 0;

        foreach ($session->criteria() as $key => $max) {
            $row = ['key' => $key, 'label' => TenderCriteria::label($key), 'max' => $max];

            foreach (['A', 'B'] as $company) {
                $levels = [];
                foreach ($judges as $slot => $judge) {
                    $level = $judge->scores->firstWhere('criterion', $key)?->effective($company);
                    $levels[$slot] = $level;
                    if ($level !== null) {
                        $judgeTotals[$company][$slot] = ($judgeTotals[$company][$slot] ?? 0) + $level / 4 * $max;
                    }
                }

                $given = array_values(array_filter($levels, fn ($l) => $l !== null));
                $mark = $given === [] ? 0.0 : array_sum($given) / (4 * count($given)) * $max;
                $flag = count($given) === 2 && abs($given[0] - $given[1]) >= self::LEVEL_GAP;
                $flagCount += $flag ? 1 : 0;

                $row[strtolower($company)] = [
                    'j1' => $levels[1] ?? null,
                    'j2' => $levels[2] ?? null,
                    'mark' => round($mark, 2),
                    'flag' => $flag,
                ];
                $totals[$company] += $mark;
            }

            $rows[] = $row;
        }

        $totalsFlag = [];
        foreach (['A', 'B'] as $company) {
            $byJudge = $judgeTotals[$company];
            $totalsFlag[$company] = count($byJudge) === 2
                && abs(($byJudge[1] ?? 0) - ($byJudge[2] ?? 0)) > self::TOTALS_GAP;
            $flagCount += $totalsFlag[$company] ? 1 : 0;
            $judgeTotals[$company] = array_map(fn ($t) => round($t, 2), $byJudge);
            $totals[$company] = round($totals[$company], 2);
        }

        [$winner, $tieBreak] = $this->winner($totals, $rows);

        return [
            'criteria' => $rows,
            'totals' => $totals,
            'judge_totals' => $judgeTotals,
            'totals_flag' => $totalsFlag,
            'flag_count' => $flagCount,
            'single_judge' => $judges->count() < 2,
            'winner' => $winner,
            'tie_break' => $tieBreak,
            'needs_sudden_death' => $winner === null,
        ];
    }

    /**
     * Criteria the Judges may change in the reconcile round: the flagged rows, or
     * every row when a Judge's totals are flagged.
     *
     * @return list<string>
     */
    public function reconcilable(array $sheet): array
    {
        $all = array_column($sheet['criteria'], 'key');
        if ($sheet['totals_flag']['A'] || $sheet['totals_flag']['B']) {
            return $all;
        }

        return array_values(array_map(
            fn ($row) => $row['key'],
            array_filter($sheet['criteria'], fn ($row) => $row['a']['flag'] || $row['b']['flag']),
        ));
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function winner(array $totals, array $rows): array
    {
        if ($totals['A'] !== $totals['B']) {
            return [$totals['A'] > $totals['B'] ? 'A' : 'B', null];
        }

        $byKey = collect($rows)->keyBy('key');
        foreach (TenderCriteria::TIE_BREAK as $key) {
            $a = $byKey[$key]['a']['mark'] ?? 0;
            $b = $byKey[$key]['b']['mark'] ?? 0;
            if ($a !== $b) {
                return [$a > $b ? 'A' : 'B', $key];
            }
        }

        return [null, null];
    }
}
