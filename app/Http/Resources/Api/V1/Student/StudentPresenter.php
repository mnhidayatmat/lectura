<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use App\Models\AssessmentScore;
use App\Models\AttendanceExcuse;
use App\Models\Course;
use App\Models\Feedback;
use App\Models\StudentMark;

/**
 * Small, reusable JSON fragments shared by the student API responses.
 */
final class StudentPresenter
{
    public static function course(?Course $course): ?array
    {
        if (! $course) {
            return null;
        }

        return [
            'id' => $course->id,
            'code' => $course->code,
            'title' => $course->title,
        ];
    }

    public static function schedule(?array $schedule): array
    {
        return collect($schedule ?? [])
            ->map(fn (array $slot) => [
                'day' => $slot['day'] ?? null,
                'start_time' => $slot['start_time'] ?? null,
                'end_time' => $slot['end_time'] ?? null,
                'location' => $slot['location'] ?? null,
                'type' => $slot['type'] ?? 'lecture',
            ])
            ->values()
            ->all();
    }

    public static function decimal(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    public static function mark(StudentMark $mark): array
    {
        return [
            'id' => $mark->id,
            'total_marks' => (float) $mark->total_marks,
            'max_marks' => (float) $mark->max_marks,
            'percentage' => self::decimal($mark->percentage),
            'grade' => $mark->grade,
            'finalized_at' => $mark->finalized_at?->toIso8601String(),
        ];
    }

    public static function feedback(Feedback $feedback): array
    {
        return [
            'performance_level' => $feedback->performance_level,
            'ai_generated' => (bool) $feedback->ai_generated,
            'strengths' => $feedback->strengths,
            'improvement_tips' => $feedback->improvement_tips,
            'missing_points' => $feedback->missing_points,
            'misconceptions' => $feedback->misconceptions,
            'revision_advice' => $feedback->revision_advice,
            'released_at' => $feedback->released_at?->toIso8601String(),
        ];
    }

    public static function assessmentScore(AssessmentScore $score): array
    {
        $assessment = $score->assessment;
        $hasScript = $score->answer_script_drive_link || $score->answer_script_path;

        return [
            'id' => $score->id,
            'assessment' => $assessment ? [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'type' => $assessment->type,
                'weightage' => self::decimal($assessment->weightage),
            ] : null,
            'course' => self::course($assessment?->course),
            'raw_marks' => (float) $score->raw_marks,
            'max_marks' => (float) $score->max_marks,
            'percentage' => self::decimal($score->percentage),
            'feedback' => $score->feedback,
            'released_at' => $score->released_at?->toIso8601String(),
            'answer_script' => $hasScript ? [
                'filename' => $score->answer_script_filename,
                'download_url' => $score->answer_script_drive_link ? null : route('api.v1.tenant.student.marks.answer-script', [
                    'tenant' => app('current_tenant')->slug,
                    'score' => $score->id,
                ]),
                'external_url' => $score->answer_script_drive_link,
            ] : null,
        ];
    }

    public static function excuse(AttendanceExcuse $excuse): array
    {
        return [
            'id' => $excuse->id,
            'status' => $excuse->status,
            'category' => $excuse->category,
            'category_label' => ucfirst(str_replace('_', ' ', $excuse->category)),
            'reason' => $excuse->reason,
            'attachment_filename' => $excuse->attachment_filename,
            'reviewer_note' => $excuse->reviewer_note,
            'reviewed_at' => $excuse->reviewed_at?->toIso8601String(),
            'submitted_at' => $excuse->created_at?->toIso8601String(),
        ];
    }
}
