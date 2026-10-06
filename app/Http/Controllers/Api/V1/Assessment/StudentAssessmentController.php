<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Assessment\SubmissionResource;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentSubmissionFile;
use App\Models\Course;
use App\Models\RubricCriteria;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\StudentGroup;
use App\Models\User;
use App\Notifications\AssessmentSubmissionReceived;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Student assessment submission API — mirrors the student methods of
 * Tenant\Assessment\AssessmentSubmissionController.
 */
class StudentAssessmentController extends Controller
{
    public const ACCEPTED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    public const MAX_FILE_SIZE_KB = 25600;

    public const MAX_NOTES_LENGTH = 1000;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $sectionIds = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('section_id');

        $courseIds = Section::whereIn('id', $sectionIds)->pluck('course_id')->unique();

        $assessments = Assessment::whereIn('course_id', $courseIds)
            ->where('requires_submission', true)
            ->whereIn('status', ['active', 'completed'])
            ->with('course')
            ->orderBy('due_date')
            ->get()
            ->filter(fn (Assessment $assessment) => $assessment->course !== null);

        $submissions = AssessmentSubmission::where('user_id', $user->id)
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->keyBy('assessment_id');

        $scores = AssessmentScore::where('user_id', $user->id)
            ->where('is_released', true)
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->keyBy('assessment_id');

        $data = $assessments->groupBy('course_id')
            ->map(fn ($courseAssessments) => [
                'course' => $this->courseSummary($courseAssessments->first()->course),
                'assessments' => $courseAssessments
                    ->map(fn (Assessment $assessment) => $this->summary(
                        $assessment,
                        $submissions->get($assessment->id),
                        $scores->get($assessment->id),
                        $user,
                    ))
                    ->values(),
            ])
            ->values();

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, Course $course, Assessment $assessment): JsonResponse
    {
        $user = $request->user();
        $this->authorizeEnrollment($course, $assessment, $user);

        return response()->json(['data' => $this->detail($course, $assessment, $user)]);
    }

    public function submit(Request $request, Course $course, Assessment $assessment): JsonResponse
    {
        $user = $request->user();
        $this->authorizeEnrollment($course, $assessment, $user);

        if (! $assessment->requires_submission) {
            abort(403, 'This assessment does not accept submissions.');
        }

        // Group submission gate: only the elected leader can submit
        $myGroup = null;
        $studentGroupId = null;
        if ($assessment->usesGroupSubmission()) {
            $myGroup = $assessment->groupForUser($user->id);
            if (! $myGroup) {
                throw ValidationException::withMessages(['files' => 'You are not assigned to a group for this assessment.']);
            }
            if (! $assessment->isGroupLeader($user->id)) {
                throw ValidationException::withMessages(['files' => 'Only the group leader can submit. Your group needs to hold a vote in the group workspace to elect one.']);
            }
            $studentGroupId = $myGroup->id;
        }

        $this->validateSubmissionFiles($request);

        $existingSubmissions = AssessmentSubmission::where('assessment_id', $assessment->id)
            ->when(
                $studentGroupId,
                fn ($q) => $q->where('student_group_id', $studentGroupId),
                fn ($q) => $q->where('user_id', $user->id),
            )
            ->with('files')
            ->get();

        if ($existingSubmissions->contains(fn ($s) => $s->status === 'graded')) {
            throw ValidationException::withMessages(['files' => 'Cannot replace a graded submission.']);
        }

        $assessment->load('course');
        $lecturer = $assessment->course->lecturer;

        foreach ($existingSubmissions as $old) {
            $this->purgeSubmissionArtifacts($old, $lecturer);
            $old->forceDelete();
        }

        $isLate = $assessment->due_date && now()->isAfter($assessment->due_date);
        $tenant = app('current_tenant');

        $submission = AssessmentSubmission::create([
            'tenant_id' => $tenant->id,
            'assessment_id' => $assessment->id,
            'user_id' => $user->id,
            'student_group_id' => $studentGroupId,
            'notes' => $request->notes,
            'is_late' => $isLate,
            'submitted_at' => now(),
            'status' => 'submitted',
            'drive_folder_id' => null,
        ]);

        $driveContext = [
            'assessment_id' => $assessment->id,
            'submission_id' => $submission->id,
            'course_id' => $course->id,
            'student_user_id' => $user->id,
        ];

        $driveFolderId = $this->resolveDriveFolder($lecturer, $assessment, $user, $driveContext);

        if ($driveFolderId) {
            $submission->update(['drive_folder_id' => $driveFolderId]);
        }

        $this->storeSubmissionFiles($request->file('files'), $submission, $assessment, $lecturer, $driveFolderId, $driveContext);

        // Mirror the submission to every other group member so marks/release propagate uniformly
        if ($myGroup) {
            $myGroup->loadMissing('members');
            foreach ($myGroup->members as $member) {
                if ((int) $member->user_id === (int) $user->id) {
                    continue;
                }
                AssessmentSubmission::create([
                    'tenant_id' => $tenant->id,
                    'assessment_id' => $assessment->id,
                    'user_id' => $member->user_id,
                    'student_group_id' => $studentGroupId,
                    'notes' => $request->notes,
                    'is_late' => $isLate,
                    'submitted_at' => now(),
                    'status' => 'submitted',
                    'drive_folder_id' => null,
                ]);
            }
        }

        if ($lecturer) {
            $lecturer->notify(new AssessmentSubmissionReceived($assessment, $user));
        }

        $message = $myGroup
            ? 'Group submission uploaded successfully.'
            : 'Submission uploaded successfully.';

        return response()->json([
            'message' => $message.($isLate ? ' (Late submission)' : ''),
            'data' => $this->detail($course, $assessment, $user),
        ], 201);
    }

    public function resubmit(Request $request, Course $course, Assessment $assessment): JsonResponse
    {
        $user = $request->user();
        $this->authorizeEnrollment($course, $assessment, $user);

        $studentGroupId = null;
        if ($assessment->usesGroupSubmission()) {
            $myGroup = $assessment->groupForUser($user->id);
            if (! $myGroup || ! $assessment->isGroupLeader($user->id)) {
                throw ValidationException::withMessages(['files' => 'Only the group leader can replace the submission.']);
            }
            $studentGroupId = $myGroup->id;

            // The "files holder" is whichever group row currently has files attached
            $submission = AssessmentSubmission::where('assessment_id', $assessment->id)
                ->where('student_group_id', $studentGroupId)
                ->whereHas('files')
                ->with('files')
                ->first()
                ?? AssessmentSubmission::where('assessment_id', $assessment->id)
                    ->where('student_group_id', $studentGroupId)
                    ->where('user_id', $user->id)
                    ->with('files')
                    ->first();

            if (! $submission) {
                throw ValidationException::withMessages(['files' => 'No submission found to replace.']);
            }
        } else {
            $submission = AssessmentSubmission::where('assessment_id', $assessment->id)
                ->where('user_id', $user->id)
                ->with('files')
                ->first();

            if (! $submission) {
                abort(404, 'No submission found to replace.');
            }
        }

        if ($submission->status === 'graded') {
            throw ValidationException::withMessages(['files' => 'Cannot modify a graded submission.']);
        }

        $this->validateSubmissionFiles($request);

        $assessment->load('course');
        $lecturer = $assessment->course->lecturer;

        $this->purgeSubmissionArtifacts($submission, $lecturer);

        $driveContext = [
            'assessment_id' => $assessment->id,
            'submission_id' => $submission->id,
            'course_id' => $course->id,
            'student_user_id' => $user->id,
            'flow' => 'resubmit',
        ];

        $driveFolderId = $this->resolveDriveFolder($lecturer, $assessment, $user, $driveContext);
        $isLate = $assessment->due_date && now()->isAfter($assessment->due_date);

        $this->storeSubmissionFiles($request->file('files'), $submission, $assessment, $lecturer, $driveFolderId, $driveContext);

        $submission->update([
            'notes' => $request->notes,
            'is_late' => $isLate,
            'submitted_at' => now(),
            'status' => 'submitted',
            'drive_folder_id' => $driveFolderId,
        ]);

        // Keep every group member's mirror row in sync
        if ($studentGroupId) {
            AssessmentSubmission::where('assessment_id', $assessment->id)
                ->where('student_group_id', $studentGroupId)
                ->where('id', '!=', $submission->id)
                ->update([
                    'notes' => $request->notes,
                    'is_late' => $isLate,
                    'submitted_at' => now(),
                    'status' => 'submitted',
                ]);
        }

        return response()->json([
            'message' => 'Submission updated successfully.'.($isLate ? ' (Late submission)' : ''),
            'data' => $this->detail($course, $assessment, $user),
        ]);
    }

    public function destroySubmission(Request $request, Course $course, Assessment $assessment): JsonResponse
    {
        $user = $request->user();
        $this->authorizeEnrollment($course, $assessment, $user);

        $assessment->load('course');
        $lecturer = $assessment->course->lecturer;

        if ($assessment->usesGroupSubmission()) {
            $myGroup = $assessment->groupForUser($user->id);
            if (! $myGroup || ! $assessment->isGroupLeader($user->id)) {
                throw ValidationException::withMessages(['submission' => 'Only the group leader can delete the submission.']);
            }

            $submissions = AssessmentSubmission::where('assessment_id', $assessment->id)
                ->where('student_group_id', $myGroup->id)
                ->with(['files', 'score'])
                ->get();

            if ($submissions->contains(fn ($s) => $s->status === 'graded')) {
                throw ValidationException::withMessages(['submission' => 'Cannot delete a graded submission.']);
            }

            foreach ($submissions as $sub) {
                $this->purgeSubmissionArtifacts($sub, $lecturer);
                $sub->forceDelete();
            }
        } else {
            $submission = AssessmentSubmission::where('assessment_id', $assessment->id)
                ->where('user_id', $user->id)
                ->with(['files', 'score'])
                ->first();

            if (! $submission) {
                abort(404, 'No submission found.');
            }

            if ($submission->status === 'graded') {
                throw ValidationException::withMessages(['submission' => 'Cannot delete a graded submission.']);
            }

            $this->purgeSubmissionArtifacts($submission, $lecturer);
            $submission->forceDelete();
        }

        return response()->json([
            'message' => 'Submission deleted. You may resubmit if needed.',
            'data' => $this->detail($course, $assessment, $user),
        ]);
    }

    public function downloadInstruction(Request $request, Course $course, Assessment $assessment): StreamedResponse
    {
        $this->authorizeEnrollment($course, $assessment, $request->user());

        if (! $assessment->instruction_file_path || ! Storage::disk('uploads')->exists($assessment->instruction_file_path)) {
            abort(404, 'Instruction file not found.');
        }

        return Storage::disk('uploads')->download(
            $assessment->instruction_file_path,
            $assessment->instruction_file_name ?? 'instruction'
        );
    }

    public function downloadFile(Request $request, AssessmentSubmissionFile $file): StreamedResponse
    {
        $user = $request->user();
        $submission = $file->submission;

        if (! $submission) {
            abort(404, 'File not found.');
        }

        $isOwnGroup = $submission->student_group_id !== null
            && StudentGroup::whereKey($submission->student_group_id)
                ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
                ->exists();

        if ((int) $submission->user_id !== (int) $user->id && ! $isOwnGroup) {
            abort(403, 'You can only download your own submission files.');
        }

        // Students always get the graded copy once it exists
        $path = $file->viewablePath();

        if (! Storage::disk('uploads')->exists($path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('uploads')->download($path, $file->file_name);
    }

    private function authorizeEnrollment(Course $course, Assessment $assessment, User $user): void
    {
        $sectionIds = $course->sections()->pluck('id');
        $isEnrolled = SectionStudent::where('user_id', $user->id)
            ->whereIn('section_id', $sectionIds)
            ->where('is_active', true)
            ->exists();

        if (! $isEnrolled) {
            abort(403, 'You are not enrolled in this course.');
        }

        if ($assessment->course_id !== $course->id) {
            abort(403, 'This assessment does not belong to this course.');
        }
    }

    private function validateSubmissionFiles(Request $request): void
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.self::MAX_FILE_SIZE_KB, 'mimes:'.implode(',', self::ACCEPTED_EXTENSIONS)],
            'notes' => ['nullable', 'string', 'max:'.self::MAX_NOTES_LENGTH],
        ]);
    }

    private function courseSummary(Course $course): array
    {
        return [
            'id' => $course->id,
            'code' => $course->code,
            'title' => $course->title,
        ];
    }

    private function typeLabel(Assessment $assessment): string
    {
        return ucfirst(str_replace('_', ' ', (string) $assessment->type));
    }

    private function summary(Assessment $assessment, ?AssessmentSubmission $submission, ?AssessmentScore $score, User $user): array
    {
        $isPastDue = $assessment->due_date !== null && now()->isAfter($assessment->due_date);
        $group = $assessment->usesGroupSubmission() ? $assessment->groupForUser($user->id) : null;

        return [
            'id' => $assessment->id,
            'course_id' => $assessment->course_id,
            'title' => $assessment->title,
            'type' => $assessment->type,
            'type_label' => $this->typeLabel($assessment),
            'total_marks' => (float) $assessment->total_marks,
            'weightage' => (float) $assessment->weightage,
            'due_date' => $assessment->due_date?->toIso8601String(),
            'is_past_due' => $isPastDue,
            'status' => $assessment->status,
            'is_group' => $assessment->usesGroupSubmission(),
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'is_leader' => $group->members()->where('user_id', $user->id)->where('role', 'leader')->exists(),
            ] : null,
            'submission_state' => match (true) {
                $score !== null => 'released',
                $submission !== null => $submission->status === 'graded' ? 'graded' : 'submitted',
                $isPastDue => 'overdue',
                default => 'not_submitted',
            },
            'submission' => $submission ? [
                'status' => $submission->status,
                'status_label' => $submission->status_badge['label'],
                'is_late' => (bool) $submission->is_late,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
            ] : null,
            'score' => $score ? [
                'raw_marks' => (float) $score->raw_marks,
                'max_marks' => (float) $score->max_marks,
                'percentage' => $score->percentage !== null ? (float) $score->percentage : null,
            ] : null,
        ];
    }

    private function detail(Course $course, Assessment $assessment, User $user): array
    {
        $assessment->loadMissing('rubric.criteria');

        $submission = AssessmentSubmission::where('assessment_id', $assessment->id)
            ->where('user_id', $user->id)
            ->with('files')
            ->first();

        $score = AssessmentScore::where('assessment_id', $assessment->id)
            ->where('user_id', $user->id)
            ->where('is_released', true)
            ->first();

        $usesGroup = $assessment->usesGroupSubmission();
        $group = $usesGroup ? $assessment->groupForUser($user->id) : null;
        $isLeader = false;
        $leader = null;
        $voteInProgress = false;

        if ($group) {
            $group->load('members.user');
            $isLeader = $group->members->contains(fn ($m) => (int) $m->user_id === (int) $user->id && $m->role === 'leader');
            $leader = $group->leader();
            $voteInProgress = $group->activeVoteRound() !== null;

            // Group members hold mirror rows; show the files from the row that holds them
            if ($submission && $submission->files->isEmpty()) {
                $holder = AssessmentSubmission::where('assessment_id', $assessment->id)
                    ->where('student_group_id', $group->id)
                    ->whereHas('files')
                    ->with('files')
                    ->first();

                if ($holder) {
                    $submission->setRelation('files', $holder->files);
                }
            }
        }

        $isPastDue = $assessment->due_date !== null && now()->isAfter($assessment->due_date);
        $acceptsSubmissions = $assessment->requires_submission && $assessment->status === 'active';
        $leaderAllowed = ! $usesGroup || ($group && $isLeader);

        $notice = match (true) {
            $submission !== null && $score === null => 'Your submission is being reviewed. Marks will appear here once released.',
            $submission !== null => null,
            ! $acceptsSubmissions => 'This assessment is not currently accepting submissions.',
            $usesGroup && ! $group => 'You are not assigned to any group for this assessment. Contact your lecturer for group assignment.',
            $usesGroup && ! $isLeader && $leader !== null => "Waiting for group leader to submit. {$leader->name} will submit on behalf of your group.",
            $usesGroup && ! $isLeader && $voteInProgress => 'Leader election in progress. Submission unlocks once voting closes.',
            $usesGroup && ! $isLeader => 'No leader elected yet. Start a vote in your group workspace first.',
            $isPastDue => 'The due date has passed. Your submission will be marked as late.',
            default => null,
        };

        $tenant = app('current_tenant');

        return [
            'id' => $assessment->id,
            'title' => $assessment->title,
            'type' => $assessment->type,
            'type_label' => $this->typeLabel($assessment),
            'description' => $assessment->description,
            'total_marks' => (float) $assessment->total_marks,
            'weightage' => (float) $assessment->weightage,
            'due_date' => $assessment->due_date?->toIso8601String(),
            'is_past_due' => $isPastDue,
            'status' => $assessment->status,
            'requires_submission' => (bool) $assessment->requires_submission,
            'is_group' => $usesGroup,
            'course' => $this->courseSummary($course),
            'instruction_file' => $assessment->instruction_file_path ? [
                'name' => $assessment->instruction_file_name ?? 'instruction',
                'extension' => strtolower(pathinfo((string) $assessment->instruction_file_name, PATHINFO_EXTENSION)) ?: null,
                'download_url' => route('api.v1.tenant.assessments.instruction', [
                    'tenant' => $tenant->slug,
                    'course' => $course->id,
                    'assessment' => $assessment->id,
                ]),
            ] : null,
            'submission_rules' => [
                'accepted_extensions' => self::ACCEPTED_EXTENSIONS,
                'max_file_size_kb' => self::MAX_FILE_SIZE_KB,
                'max_notes_length' => self::MAX_NOTES_LENGTH,
                'multiple_files' => true,
            ],
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'is_leader' => $isLeader,
                'leader' => $leader ? ['id' => $leader->id, 'name' => $leader->name] : null,
                'vote_in_progress' => $voteInProgress,
                'members' => $group->members
                    ->map(fn ($member) => [
                        'id' => (int) $member->user_id,
                        'name' => $member->user?->name,
                        'role' => $member->role,
                    ])
                    ->values(),
            ] : null,
            'submission' => $submission ? (new SubmissionResource($submission))->resolve() : null,
            'score' => $score ? $this->scorePayload($score, $assessment) : null,
            'can_submit' => $submission === null && $acceptsSubmissions && $leaderAllowed,
            'can_resubmit' => $submission !== null && $submission->status !== 'graded' && $leaderAllowed,
            'can_delete' => $submission !== null && $submission->status !== 'graded' && $leaderAllowed,
            'notice' => $notice,
        ];
    }

    private function scorePayload(AssessmentScore $score, Assessment $assessment): array
    {
        $criteriaMarks = is_array($score->criteria_marks) ? $score->criteria_marks : [];
        $criteria = $assessment->rubric?->criteria ?? collect();

        return [
            'raw_marks' => (float) $score->raw_marks,
            'max_marks' => (float) $score->max_marks,
            'weighted_marks' => (float) $score->weighted_marks,
            'percentage' => $score->percentage !== null ? (float) $score->percentage : null,
            'feedback' => $score->feedback,
            'released_at' => $score->released_at?->toIso8601String(),
            'criteria' => $criteria
                ->map(fn (RubricCriteria $criterion) => [
                    'id' => $criterion->id,
                    'title' => $criterion->title,
                    'description' => $criterion->description,
                    'max_marks' => $criterion->max_marks !== null ? (float) $criterion->max_marks : null,
                    'weightage' => $criterion->weightage !== null ? (float) $criterion->weightage : null,
                    'marks' => array_key_exists((string) $criterion->id, $criteriaMarks)
                        ? (float) $criteriaMarks[(string) $criterion->id]
                        : null,
                ])
                ->values(),
        ];
    }

    private function resolveDriveFolder(?User $lecturer, Assessment $assessment, User $user, array $driveContext): ?string
    {
        if (! $lecturer) {
            Log::info('drive submission skipped: course has no lecturer_id', $driveContext);

            return null;
        }

        if (! $lecturer->isDriveConnected()) {
            Log::info('drive submission skipped: lecturer has not connected Drive', $driveContext + [
                'lecturer_id' => $lecturer->id,
                'lecturer_email' => $lecturer->email,
            ]);

            return null;
        }

        try {
            $driveService = app(GoogleDriveService::class);

            $courseFolderId = $driveService->findOrCreateFolder(
                $lecturer,
                "{$assessment->course->code} — {$assessment->course->title}"
            );

            $submissionsFolderId = $driveService->findOrCreateFolder($lecturer, 'Submissions', $courseFolderId);

            $typeLabel = ucfirst($assessment->type ?? 'Assessment');
            $assessmentFolderId = $driveService->findOrCreateFolder(
                $lecturer, "[{$typeLabel}] {$assessment->title}", $submissionsFolderId
            );

            return $driveService->findOrCreateFolder($lecturer, $user->name, $assessmentFolderId);
        } catch (\Throwable $e) {
            Log::warning('drive submission folder creation failed', $driveContext + [
                'lecturer_id' => $lecturer->id,
                'lecturer_email' => $lecturer->email,
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function storeSubmissionFiles(
        array $files,
        AssessmentSubmission $submission,
        Assessment $assessment,
        ?User $lecturer,
        ?string $driveFolderId,
        array $driveContext,
    ): void {
        foreach ($files as $file) {
            $path = $file->store('assessment_submissions/'.$assessment->id, 'uploads');
            $driveFileId = null;

            if ($driveFolderId && $lecturer) {
                try {
                    $safeName = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $file->getClientOriginalName());
                    $driveName = '['.now()->format('Y-m-d')."] {$safeName}";

                    $result = app(GoogleDriveService::class)->uploadFile(
                        $lecturer,
                        $file->getRealPath(),
                        $driveName,
                        $file->getClientMimeType(),
                        $driveFolderId
                    );
                    $driveFileId = $result['id'];
                } catch (\Throwable $e) {
                    Log::warning('drive submission file upload failed', $driveContext + [
                        'lecturer_id' => $lecturer->id,
                        'lecturer_email' => $lecturer->email,
                        'drive_folder_id' => $driveFolderId,
                        'file_name' => $file->getClientOriginalName(),
                        'error' => $e->getMessage(),
                        'exception_class' => $e::class,
                    ]);
                }
            }

            AssessmentSubmissionFile::create([
                'assessment_submission_id' => $submission->id,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size_bytes' => $file->getSize(),
                'storage_path' => $path,
                'drive_file_id' => $driveFileId,
            ]);
        }
    }

    /**
     * Delete a submission's local files and remote Drive folder; Drive failures are swallowed.
     */
    private function purgeSubmissionArtifacts(AssessmentSubmission $submission, ?User $lecturer): void
    {
        $submission->loadMissing('files');

        foreach ($submission->files as $file) {
            if (Storage::disk('uploads')->exists($file->storage_path)) {
                Storage::disk('uploads')->delete($file->storage_path);
            }
            $file->delete();
        }

        if ($submission->drive_folder_id && $lecturer) {
            try {
                app(GoogleDriveService::class)->deleteFile($lecturer, $submission->drive_folder_id);
            } catch (\Throwable) {
                // Drive deletion failed — submission still locally deleted
            }
        }
    }
}
