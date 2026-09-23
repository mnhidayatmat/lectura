<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\Student\Concerns\InteractsWithEnrollments;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\AssignmentPresenter;
use App\Http\Resources\Api\V1\Student\StudentPresenter;
use App\Models\Assignment;
use App\Models\AssignmentGroup;
use App\Models\Feedback;
use App\Models\StudentGroup;
use App\Models\StudentMark;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\User;
use App\Notifications\SubmissionReceived;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Student side of Tenant\AssignmentController (index / show / submit / downloadInstruction).
 */
class AssignmentController extends Controller
{
    use InteractsWithEnrollments;

    private const VISIBLE_STATUSES = ['published', 'closed', 'marking', 'completed'];

    /**
     * `assignments.parent_id` has no migration, so sub-assignments only exist on
     * installations where the column was added by hand.
     */
    private static ?bool $supportsSubAssignments = null;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $assignments = Assignment::whereIn('course_id', $this->enrolledCourseIds($user))
            ->where('status', 'published')
            ->with('course')
            ->get()
            ->filter(fn (Assignment $assignment) => $assignment->parent_id === null)
            ->sort(function (Assignment $a, Assignment $b) {
                $byCourse = strcmp((string) $a->course?->code, (string) $b->course?->code);

                return $byCourse !== 0 ? $byCourse : (($b->deadline?->timestamp ?? 0) <=> ($a->deadline?->timestamp ?? 0));
            })
            ->values();

        $assignmentIds = $assignments->pluck('id');

        $marks = StudentMark::where('user_id', $user->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->get()
            ->keyBy('assignment_id');

        $submissions = Submission::where('user_id', $user->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->orderByDesc('submitted_at')
            ->get()
            ->groupBy('assignment_id');

        return response()->json([
            'data' => $assignments->map(function (Assignment $assignment) use ($marks, $submissions) {
                $mark = $marks->get($assignment->id);
                $submission = $submissions->get($assignment->id)?->first();

                return [
                    ...AssignmentPresenter::summary($assignment),
                    'status' => $this->statusFor($assignment, $mark, $submission),
                    'submitted_at' => $submission?->submitted_at?->toIso8601String(),
                    'is_late' => (bool) $submission?->is_late,
                    'mark' => $mark && $mark->is_final ? StudentPresenter::mark($mark) : null,
                ];
            })->values(),
        ]);
    }

    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAssignment($assignment, $user);

        $tenantSlug = app('current_tenant')->slug;
        $assignment->load('course');

        $mySubmission = Submission::where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->with(['files', 'user'])
            ->orderByDesc('submitted_at')
            ->first();

        $mark = StudentMark::where('assignment_id', $assignment->id)->where('user_id', $user->id)->first();

        $feedback = $mySubmission
            ? Feedback::where('submission_id', $mySubmission->id)
                ->where('user_id', $user->id)
                ->where('is_released', true)
                ->first()
            : null;

        $group = $this->groupContext($assignment, $user);
        $submission = $mySubmission ?? $group['submission'];
        $attemptsUsed = $this->attemptsUsed($assignment, $user, $group);
        $gate = $this->submissionGate($assignment, $user, $group, $submission !== null, $attemptsUsed);

        return response()->json([
            'data' => [
                'assignment' => [
                    ...AssignmentPresenter::summary($assignment),
                    'description' => $assignment->description,
                    'instruction' => AssignmentPresenter::instruction($assignment, $tenantSlug),
                    'sub_assignments' => $this->subAssignments($assignment),
                ],
                'rules' => AssignmentPresenter::rules($assignment, $attemptsUsed),
                'group' => $group['payload'],
                'submission' => AssignmentPresenter::submission(
                    $submission,
                    $assignment,
                    $user,
                    $tenantSlug,
                    withAnnotations: $feedback !== null,
                ),
                'status' => $this->statusFor($assignment, $mark, $submission),
                'mark' => $mark && $mark->is_final ? StudentPresenter::mark($mark) : null,
                'feedback' => $feedback ? StudentPresenter::feedback($feedback) : null,
                'can_submit' => $gate['can_submit'],
                'can_resubmit' => $gate['can_resubmit'],
                'blocked_reason' => $gate['blocked_reason'],
            ],
        ]);
    }

    public function submit(Request $request, Assignment $assignment): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAssignment($assignment, $user);

        $type = $assignment->submission_type ?? 'file';
        $fileRules = ['file', 'max:'.AssignmentPresenter::MAX_FILE_KILOBYTES, 'mimes:'.implode(',', AssignmentPresenter::ACCEPTED_EXTENSIONS)];

        $rules = ['notes' => ['nullable', 'string', 'max:'.AssignmentPresenter::MAX_NOTES_LENGTH]];

        if ($type === 'file') {
            $rules['files'] = ['required', 'array', 'min:1'];
            $rules['files.*'] = $fileRules;
        } elseif ($type === 'text') {
            $rules['text_content'] = ['required', 'string'];
        } else {
            $rules['files'] = ['nullable', 'array'];
            $rules['files.*'] = $fileRules;
            $rules['text_content'] = ['nullable', 'string'];
        }

        $request->validate($rules);

        if ($type === 'both' && ! $request->hasFile('files') && ! $request->filled('text_content')) {
            throw ValidationException::withMessages(['files' => 'Please provide either files or text content.']);
        }

        $group = $this->groupContext($assignment, $user);
        $existing = Submission::where('assignment_id', $assignment->id)->where('user_id', $user->id)->exists()
            || $group['submission'] !== null;
        $attemptsUsed = $this->attemptsUsed($assignment, $user, $group);
        $gate = $this->submissionGate($assignment, $user, $group, $existing, $attemptsUsed);

        if (! $gate['can_submit']) {
            return response()->json(['message' => $gate['blocked_reason'] ?? 'You cannot submit this assignment.'], 422);
        }

        $isLate = $assignment->deadline && now()->isAfter($assignment->deadline);
        $usesGroupSet = $assignment->usesStudentGroupSet();
        $groupModel = $group['model'];

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'assignment_group_id' => $groupModel && ! $usesGroupSet ? $groupModel->id : null,
            'student_group_id' => $groupModel && $usesGroupSet ? $groupModel->id : null,
            'submission_number' => $attemptsUsed + 1,
            'notes' => $request->notes,
            'text_content' => $request->text_content,
            'is_late' => $isLate,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        if ($request->hasFile('files')) {
            $this->storeFiles($request->file('files'), $assignment, $submission, $user);
        }

        // Group assignments: mirror the submission onto every other member, as the web does.
        if ($groupModel) {
            $groupModel->loadMissing('members');
            foreach ($groupModel->members as $member) {
                if ((int) $member->user_id === (int) $user->id) {
                    continue;
                }

                Submission::create([
                    'assignment_id' => $assignment->id,
                    'user_id' => $member->user_id,
                    'assignment_group_id' => $usesGroupSet ? null : $groupModel->id,
                    'student_group_id' => $usesGroupSet ? $groupModel->id : null,
                    'submission_number' => $attemptsUsed + 1,
                    'notes' => $request->notes,
                    'text_content' => $request->text_content,
                    'is_late' => $isLate,
                    'submitted_at' => now(),
                    'status' => 'submitted',
                ]);
            }
        }

        $lecturer = $assignment->course?->lecturer;
        if ($lecturer) {
            $lecturer->notify(new SubmissionReceived($assignment, $user));
        }

        $message = ($assignment->isGroupAssignment()
            ? 'Group submission uploaded successfully.'
            : 'Submission uploaded successfully.').($isLate ? ' (Late submission)' : '');

        return response()->json([
            'message' => $message,
            'data' => [
                'submission' => AssignmentPresenter::submission($submission, $assignment, $user, app('current_tenant')->slug),
            ],
        ], 201);
    }

    public function downloadInstruction(Request $request, Assignment $assignment): mixed
    {
        $this->authorizeAssignment($assignment, $request->user());

        if (! $assignment->instruction_file_path && ! $assignment->instruction_drive_web_link) {
            abort(404);
        }

        if ($assignment->instruction_drive_web_link) {
            return redirect()->away($assignment->instruction_drive_web_link);
        }

        if (! Storage::disk('uploads')->exists($assignment->instruction_file_path)) {
            abort(404);
        }

        return Storage::disk('uploads')->download(
            $assignment->instruction_file_path,
            $assignment->instruction_filename ?? 'assignment-instructions'
        );
    }

    public function downloadFile(Request $request, Assignment $assignment, SubmissionFile $file): mixed
    {
        $this->authorizeSubmissionFile($assignment, $file, $request->user());

        if (! $file->storage_path || ! Storage::disk('uploads')->exists($file->storage_path)) {
            abort(404);
        }

        return Storage::disk('uploads')->download($file->storage_path, $file->file_name);
    }

    public function downloadAnnotated(Request $request, Assignment $assignment, SubmissionFile $file): mixed
    {
        $this->authorizeSubmissionFile($assignment, $file, $request->user());

        if (! $file->annotated_image_path || ! Storage::disk('uploads')->exists($file->annotated_image_path)) {
            abort(404);
        }

        return Storage::disk('uploads')->response($file->annotated_image_path);
    }

    /**
     * Students see published assignments of courses they are enrolled in, plus closed and marked
     * ones (the marks list links to them); only published ones accept submissions.
     */
    private function authorizeAssignment(Assignment $assignment, User $user): void
    {
        if (! in_array($assignment->status, self::VISIBLE_STATUSES, true) || ! $assignment->course) {
            abort(404);
        }

        $this->ensureEnrolled($assignment->course, $user);
    }

    private function authorizeSubmissionFile(Assignment $assignment, SubmissionFile $file, User $user): void
    {
        $this->authorizeAssignment($assignment, $user);

        $submission = $file->submission;

        if (! $submission || (int) $submission->assignment_id !== $assignment->id) {
            abort(404);
        }

        if ((int) $submission->user_id === $user->id) {
            return;
        }

        $group = $this->groupContext($assignment, $user);
        $groupModel = $group['model'];
        $sameGroup = $groupModel !== null && (
            (int) ($submission->student_group_id ?? 0) === (int) $groupModel->id
            || (int) ($submission->assignment_group_id ?? 0) === (int) $groupModel->id
        );

        if (! $sameGroup) {
            abort(403, 'This submission does not belong to you.');
        }
    }

    /**
     * Parts of a multi-part assignment, when this installation supports them.
     */
    private function subAssignments(Assignment $assignment): array
    {
        self::$supportsSubAssignments ??= Schema::hasColumn('assignments', 'parent_id');

        if (! self::$supportsSubAssignments) {
            return [];
        }

        return $assignment->subAssignments()
            ->where('status', 'published')
            ->get()
            ->map(fn (Assignment $sub) => [
                'id' => $sub->id,
                'title' => $sub->title,
                'deadline' => $sub->deadline?->toIso8601String(),
                'total_marks' => (float) $sub->total_marks,
            ])
            ->values()
            ->all();
    }

    private function statusFor(Assignment $assignment, ?StudentMark $mark, ?Submission $submission): string
    {
        if ($mark && $mark->is_final) {
            return 'graded';
        }

        if ($submission) {
            return 'submitted';
        }

        return $assignment->deadline && now()->isAfter($assignment->deadline) ? 'overdue' : 'not_submitted';
    }

    /**
     * @return array{model: StudentGroup|AssignmentGroup|null, payload: array|null, submission: Submission|null}
     */
    private function groupContext(Assignment $assignment, User $user): array
    {
        $empty = ['model' => null, 'payload' => null, 'submission' => null];

        if (! $assignment->isGroupAssignment()) {
            return $empty;
        }

        $group = $assignment->groupForUser($user->id);

        if (! $group) {
            return $empty;
        }

        $usesGroupSet = $assignment->usesStudentGroupSet();
        $isLeader = $assignment->isGroupLeader($user->id);

        $submission = Submission::where('assignment_id', $assignment->id)
            ->when(
                $usesGroupSet,
                fn ($query) => $query->where('student_group_id', $group->id),
                fn ($query) => $query->where('assignment_group_id', $group->id),
            )
            ->with(['files', 'user'])
            ->orderByDesc('submitted_at')
            ->first();

        return [
            'model' => $group,
            'payload' => AssignmentPresenter::group($group, $usesGroupSet, $isLeader),
            'submission' => $submission,
        ];
    }

    /**
     * Attempts made so far — the student's own rows, which the group flow mirrors.
     */
    private function attemptsUsed(Assignment $assignment, User $user, array $group): int
    {
        return Submission::where('assignment_id', $assignment->id)->where('user_id', $user->id)->count();
    }

    /**
     * @return array{can_submit: bool, can_resubmit: bool, blocked_reason: string|null}
     */
    private function submissionGate(Assignment $assignment, User $user, array $group, bool $hasSubmission, int $attemptsUsed): array
    {
        if ($assignment->status !== 'published') {
            return [
                'can_submit' => false,
                'can_resubmit' => false,
                'blocked_reason' => 'This assignment is no longer accepting submissions.',
            ];
        }

        if ($assignment->isGroupAssignment()) {
            if (! $group['model']) {
                return [
                    'can_submit' => false,
                    'can_resubmit' => false,
                    'blocked_reason' => 'You are not assigned to a group for this assignment.',
                ];
            }

            if (! $assignment->isGroupLeader($user->id)) {
                return [
                    'can_submit' => false,
                    'can_resubmit' => false,
                    'blocked_reason' => $assignment->usesStudentGroupSet()
                        ? 'Only the group leader can submit. Your group needs to hold a vote to elect a leader.'
                        : 'Only the group leader can submit for the group.',
                ];
            }
        }

        if (! $hasSubmission) {
            return ['can_submit' => true, 'can_resubmit' => false, 'blocked_reason' => null];
        }

        $maxResubmissions = (int) ($assignment->max_resubmissions ?? 0);
        $canResubmit = (bool) $assignment->allow_resubmission
            && ($maxResubmissions === 0 || max($attemptsUsed - 1, 0) < $maxResubmissions);

        return [
            'can_submit' => $canResubmit,
            'can_resubmit' => $canResubmit,
            'blocked_reason' => $canResubmit ? null : 'You have already submitted this assignment.',
        ];
    }

    /**
     * Stores each file locally and mirrors it to the lecturer's Drive when connected.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function storeFiles(array $files, Assignment $assignment, Submission $submission, User $user): void
    {
        $lecturer = $assignment->course?->lecturer;
        $driveFolderId = null;

        if ($lecturer && $lecturer->isDriveConnected()) {
            try {
                $driveService = app(GoogleDriveService::class);
                $courseFolderId = $driveService->findOrCreateFolder(
                    $lecturer,
                    "{$assignment->course->code} — {$assignment->course->title}"
                );
                $submissionsFolderId = $driveService->findOrCreateFolder($lecturer, 'Submissions', $courseFolderId);
                $driveFolderId = $driveService->findOrCreateFolder($lecturer, $assignment->title, $submissionsFolderId);
            } catch (\Throwable) {
                // Drive unavailable — continue with local storage only
                $driveFolderId = null;
            }
        }

        foreach ($files as $file) {
            $path = $file->store('submissions/'.$assignment->id, 'uploads');
            $driveFileId = null;

            if ($driveFolderId && $lecturer) {
                try {
                    $result = app(GoogleDriveService::class)->uploadFile(
                        $lecturer,
                        $file->getRealPath(),
                        $user->name.' — '.$file->getClientOriginalName(),
                        $file->getClientMimeType(),
                        $driveFolderId
                    );
                    $driveFileId = $result['id'];
                } catch (\Throwable) {
                    // Drive upload failed — keep local copy
                }
            }

            SubmissionFile::create([
                'submission_id' => $submission->id,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size_bytes' => $file->getSize(),
                'storage_path' => $path,
                'drive_file_id' => $driveFileId,
            ]);
        }
    }
}
