<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use App\Models\Assignment;
use App\Models\AssignmentGroup;
use App\Models\StudentGroup;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\User;

/**
 * JSON fragments for the student assignment endpoints.
 */
final class AssignmentPresenter
{
    /** File types the web submission form accepts. */
    public const ACCEPTED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    /** Per-file limit of the web form (25600 KB). */
    public const MAX_FILE_KILOBYTES = 25600;

    public const MAX_NOTES_LENGTH = 1000;

    public static function summary(Assignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'type' => $assignment->type,
            'course' => StudentPresenter::course($assignment->course),
            'deadline' => $assignment->deadline?->toIso8601String(),
            'is_past_due' => $assignment->deadline !== null && now()->isAfter($assignment->deadline),
            'total_marks' => (float) $assignment->total_marks,
            'submission_type' => $assignment->submission_type ?? 'file',
        ];
    }

    public static function instruction(Assignment $assignment, string $tenantSlug): ?array
    {
        if (! $assignment->instruction_file_path && ! $assignment->instruction_drive_web_link) {
            return null;
        }

        return [
            'filename' => $assignment->instruction_filename ?? 'Assignment instructions',
            'download_url' => $assignment->instruction_drive_web_link ? null : route('api.v1.tenant.student.assignments.instruction', [
                'tenant' => $tenantSlug,
                'assignment' => $assignment->id,
            ]),
            'external_url' => $assignment->instruction_drive_web_link,
        ];
    }

    /**
     * Upload rules the app enforces before sending a submission.
     */
    public static function rules(Assignment $assignment, int $attemptsUsed): array
    {
        $type = $assignment->submission_type ?? 'file';
        $allowsResubmission = (bool) $assignment->allow_resubmission;
        $maxResubmissions = (int) ($assignment->max_resubmissions ?? 0);
        $resubmissionsUsed = max($attemptsUsed - 1, 0);

        return [
            'submission_type' => $type,
            'allows_files' => $type === 'file' || $type === 'both',
            'allows_text' => $type === 'text' || $type === 'both',
            'requires_files' => $type === 'file',
            'requires_text' => $type === 'text',
            'accepted_extensions' => self::ACCEPTED_EXTENSIONS,
            'max_file_size_bytes' => self::MAX_FILE_KILOBYTES * 1024,
            'max_notes_length' => self::MAX_NOTES_LENGTH,
            'allow_resubmission' => $allowsResubmission,
            'max_resubmissions' => $maxResubmissions,
            'attempts_used' => $attemptsUsed,
            // null means "no configured limit" when resubmission is switched on.
            'attempts_remaining' => ! $allowsResubmission
                ? 0
                : ($maxResubmissions > 0 ? max($maxResubmissions - $resubmissionsUsed, 0) : null),
        ];
    }

    public static function group(StudentGroup|AssignmentGroup $group, bool $usesGroupSet, bool $isLeader): array
    {
        $group->loadMissing('members.user');

        $members = $group->members->map(fn ($member) => [
            'id' => (int) $member->user_id,
            'name' => $member->user?->name,
            'is_leader' => $usesGroupSet ? ($member->role ?? null) === 'leader' : (bool) ($member->is_leader ?? false),
        ])->values();

        return [
            'id' => $group->id,
            'name' => $group->name,
            'kind' => $usesGroupSet ? 'student_group_set' : 'assignment_group',
            'is_leader' => $isLeader,
            'leader_name' => $members->firstWhere('is_leader', true)['name'] ?? null,
            'members' => $members,
        ];
    }

    public static function submission(
        ?Submission $submission,
        Assignment $assignment,
        User $user,
        string $tenantSlug,
        bool $withAnnotations = false,
    ): ?array {
        if (! $submission) {
            return null;
        }

        $submission->loadMissing(['files', 'user']);

        return [
            'id' => $submission->id,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'is_late' => (bool) $submission->is_late,
            'status' => $submission->status,
            'submission_number' => (int) $submission->submission_number,
            'notes' => $submission->notes,
            'text_content' => $submission->text_content,
            'is_mine' => (int) $submission->user_id === $user->id,
            'submitted_by' => $submission->user?->name,
            'files' => $submission->files->map(fn (SubmissionFile $file) => [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'file_type' => $file->file_type,
                'size_bytes' => (int) $file->file_size_bytes,
                'download_url' => $file->storage_path ? route('api.v1.tenant.student.assignments.files.download', [
                    'tenant' => $tenantSlug,
                    'assignment' => $assignment->id,
                    'file' => $file->id,
                ]) : null,
                'annotated_url' => ($withAnnotations && $file->annotated_image_path) ? route('api.v1.tenant.student.assignments.files.annotated', [
                    'tenant' => $tenantSlug,
                    'assignment' => $assignment->id,
                    'file' => $file->id,
                ]) : null,
            ])->values(),
        ];
    }
}
