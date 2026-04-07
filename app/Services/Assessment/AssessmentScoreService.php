<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Models\Assessment;
use App\Models\AssessmentCloScore;
use App\Models\AssessmentScore;
use App\Models\Course;
use App\Models\QuizParticipant;
use App\Models\SectionStudent;
use App\Models\StudentMark;
use App\Models\User;
use Illuminate\Support\Collection;

class AssessmentScoreService
{
    /**
     * Compute scores for an assessment from its linked items.
     */
    public function computeScores(Assessment $assessment): int
    {
        $assessment->load(['items.assessable', 'clos']);
        $tenant = app('current_tenant');

        // No linked items — re-normalise any existing manual/submission scores
        // (handles cases where total_marks or weightage was changed after marking)
        if ($assessment->items->isEmpty()) {
            return $this->renormaliseExistingScores($assessment);
        }

        // Collect raw scores from all linked items per student
        $studentScores = collect();

        foreach ($assessment->items as $item) {
            $itemScores = $this->getItemScores($item);
            $contribution = $item->contribution_percentage / 100;

            foreach ($itemScores as $userId => $data) {
                $existing = $studentScores->get($userId, ['raw' => 0, 'max' => 0]);
                $existing['raw'] += $data['marks'] * $contribution;
                $existing['max'] += $data['max'] * $contribution;
                $studentScores->put($userId, $existing);
            }
        }

        // Normalize to assessment's total_marks scale and save
        $count = 0;
        foreach ($studentScores as $userId => $data) {
            if ($data['max'] <= 0) {
                continue;
            }

            $percentage = ($data['raw'] / $data['max']) * 100;
            $normalizedMarks = ($data['raw'] / $data['max']) * $assessment->total_marks;
            $weightedMarks = $normalizedMarks * ($assessment->weightage / 100);

            AssessmentScore::updateOrCreate(
                ['assessment_id' => $assessment->id, 'user_id' => $userId],
                [
                    'tenant_id' => $tenant->id,
                    'raw_marks' => round($normalizedMarks, 2),
                    'max_marks' => $assessment->total_marks,
                    'weighted_marks' => round($weightedMarks, 2),
                    'percentage' => round($percentage, 2),
                    'is_computed' => true,
                ]
            );

            $count++;
        }

        // Compute CLO-level scores
        $this->computeCloScores($assessment, $studentScores);

        return $count;
    }

    /**
     * Distribute assessment scores across mapped CLOs.
     */
    protected function computeCloScores(Assessment $assessment, Collection $studentScores): void
    {
        $clos = $assessment->clos;
        if ($clos->isEmpty()) {
            return;
        }

        // Determine per-CLO weightage
        $totalCloWeight = $clos->sum('pivot.weightage');
        $equalWeight = $totalCloWeight > 0 ? false : true;
        $perCloFraction = $equalWeight ? (1 / $clos->count()) : null;

        foreach ($studentScores as $userId => $data) {
            if ($data['max'] <= 0) {
                continue;
            }

            $percentage = ($data['raw'] / $data['max']) * 100;

            foreach ($clos as $clo) {
                $fraction = $equalWeight
                    ? $perCloFraction
                    : (($clo->pivot->weightage ?? 0) / $totalCloWeight);

                $cloMarks = $data['raw'] * $fraction;
                $cloMax = $data['max'] * $fraction;

                AssessmentCloScore::updateOrCreate(
                    [
                        'assessment_id' => $assessment->id,
                        'course_learning_outcome_id' => $clo->id,
                        'user_id' => $userId,
                    ],
                    [
                        'marks' => round($cloMarks, 2),
                        'max_marks' => round($cloMax, 2),
                        'percentage' => round($percentage, 2),
                    ]
                );
            }
        }
    }

    /**
     * Re-calculate percentage and weighted_marks for all existing scores on this assessment.
     * Useful after total_marks or weightage is edited without changing the raw marks.
     */
    protected function renormaliseExistingScores(Assessment $assessment): int
    {
        $scores = $assessment->scores()->get();
        $count  = 0;

        foreach ($scores as $score) {
            if ($score->max_marks <= 0) {
                continue;
            }

            $percentage    = round(($score->raw_marks / $score->max_marks) * 100, 2);
            $weightedMarks = round($score->raw_marks * ($assessment->weightage / 100), 2);

            $score->update([
                'percentage'    => $percentage,
                'weighted_marks' => $weightedMarks,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Get per-student scores from a linked item.
     *
     * @return array<int, array{marks: float, max: float}>
     */
    protected function getItemScores($item): array
    {
        $scores = [];

        if ($item->assessable_type === \App\Models\Assignment::class) {
            $marks = StudentMark::where('assignment_id', $item->assessable_id)->get();
            foreach ($marks as $mark) {
                $scores[$mark->user_id] = [
                    'marks' => (float) $mark->total_marks,
                    'max' => (float) $mark->max_marks,
                ];
            }
        } elseif ($item->assessable_type === \App\Models\QuizSession::class) {
            $quiz = $item->assessable;
            if (!$quiz) {
                return [];
            }

            // Calculate max possible score for this quiz
            $maxScore = $quiz->sessionQuestions()
                ->with('question')
                ->get()
                ->sum(fn ($sq) => (float) ($sq->question->points ?? 1));

            if ($maxScore <= 0) {
                return [];
            }

            $participants = QuizParticipant::where('quiz_session_id', $quiz->id)->get();
            foreach ($participants as $p) {
                $scores[$p->user_id] = [
                    'marks' => (float) $p->total_score,
                    'max' => $maxScore,
                ];
            }
        }

        return $scores;
    }
}
