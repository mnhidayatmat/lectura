<?php

declare(strict_types=1);

namespace App\Services\Performance;

use App\Models\AssessmentScore;
use App\Models\Feedback;
use App\Models\StudentMark;
use Illuminate\Support\Carbon;

/**
 * A single graded result, normalised across the two independent gradebooks:
 * the Assignments system (StudentMark) and the OBE Assessments system
 * (AssessmentScore). Performance aggregation reads only this shape, so both
 * sources contribute to averages, CLO attainment and per-student rollups.
 *
 * Assignments carry structured feedback (strengths / improvements / missing
 * points); assessments carry a single free-text field. Both are exposed rather
 * than flattened, because the student view renders them differently.
 */
class GradedItem
{
    public function __construct(
        public readonly string $source,
        public readonly int $userId,
        public readonly int $sourceId,
        public readonly string $title,
        public readonly ?string $type,
        public readonly ?Carbon $dueAt,
        public readonly float $obtained,
        public readonly float $max,
        public readonly float $percentage,
        public readonly ?string $grade,
        public readonly array $cloIds,
        public readonly bool $isReleased,
        /**
         * Share of the final grade, as a percentage. Null for Assignments,
         * which carry no weightage — such courses fall back to a plain mean.
         */
        public readonly ?float $weightage = null,
        public readonly ?Feedback $feedbackDetail = null,
        public readonly ?string $feedbackText = null,
    ) {}

    public static function fromStudentMark(StudentMark $mark): self
    {
        $assignment = $mark->assignment;

        // Feedback must come from *this student's* submission. Reading
        // $assignment->submissions->first() would surface another student's.
        $feedback = $mark->submission?->feedback;

        return new self(
            source: 'assignment',
            userId: (int) $mark->user_id,
            sourceId: (int) $mark->assignment_id,
            title: $assignment->title ?? 'Unknown',
            type: $assignment->type ?? null,
            dueAt: $assignment->deadline ?? null,
            obtained: (float) $mark->total_marks,
            max: (float) $mark->max_marks,
            percentage: (float) $mark->percentage,
            grade: $mark->grade,
            cloIds: array_map('intval', $assignment->clo_ids ?? []),
            // StudentMark has never been release-gated; preserve that.
            isReleased: true,
            feedbackDetail: ($feedback && $feedback->is_released) ? $feedback : null,
        );
    }

    public static function fromAssessmentScore(AssessmentScore $score): self
    {
        $assessment = $score->assessment;

        return new self(
            source: 'assessment',
            userId: (int) $score->user_id,
            sourceId: (int) $score->assessment_id,
            title: $assessment->title ?? 'Unknown',
            type: $assessment->type ?? null,
            dueAt: $assessment->due_date ?? null,
            obtained: (float) $score->raw_marks,
            max: (float) $score->max_marks,
            percentage: (float) $score->percentage,
            // The Assessments system stores no letter grade.
            grade: null,
            cloIds: $assessment->relationLoaded('clos')
                ? $assessment->clos->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [],
            isReleased: (bool) $score->is_released,
            weightage: $assessment->weightage === null ? null : (float) $assessment->weightage,
            feedbackText: $score->is_released ? $score->feedback : null,
        );
    }
}
