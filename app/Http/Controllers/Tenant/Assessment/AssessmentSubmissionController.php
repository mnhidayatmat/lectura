<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Assessment;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentSubmissionFile;
use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\User;
use App\Notifications\AssessmentMarksReleased;
use App\Notifications\AssessmentSubmissionReceived;
use App\Services\Assessment\SubmissionReportStampingService;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentSubmissionController extends Controller
{
    use AuthorizesCourseAccess;
    // ─── Lecturer Methods ───────────────────────────────────────

    public function index(string $tenantSlug, Course $course, Assessment $assessment): View
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $tenant = app('current_tenant');

        // Get enrolled students
        $sectionIds = $this->lecturerSectionIds($course);
        $enrolledStudents = User::whereIn('id', function ($q) use ($sectionIds) {
            $q->select('user_id')
                ->from('section_students')
                ->whereIn('section_id', $sectionIds)
                ->where('is_active', true);
        })->orderBy('name')->get();

        $submissions = $assessment->submissions()->with(['user', 'files'])->get()->keyBy('user_id');
        $scores = $assessment->scores()->get()->keyBy('user_id');

        // Build a map user_id => group metadata (id, name, color, role) so the
        // submissions table can color-code each group and badge the leader.
        $groupInfoByUser = collect();
        if ($assessment->usesGroupSubmission()) {
            $assessment->load('studentGroupSet.groups.members');
            $palette = [
                'indigo', 'emerald', 'amber', 'rose', 'sky',
                'violet', 'teal', 'fuchsia', 'cyan', 'lime',
                'orange', 'pink',
            ];
            foreach ($assessment->studentGroupSet->groups ?? [] as $i => $group) {
                $color = $palette[$i % count($palette)];
                foreach ($group->members as $member) {
                    $groupInfoByUser[$member->user_id] = [
                        'group_id' => $group->id,
                        'group_name' => $group->name,
                        'color' => $color,
                        'role' => $member->role,
                        'sort' => $i,
                    ];
                }
            }

            // Order students so group members sit together (group sort, then leader first, then name).
            $enrolledStudents = $enrolledStudents->sortBy(function ($student) use ($groupInfoByUser) {
                $info = $groupInfoByUser[$student->id] ?? null;
                $groupSort = $info['sort'] ?? PHP_INT_MAX;
                $leaderSort = ($info['role'] ?? null) === 'leader' ? 0 : 1;

                return sprintf('%05d-%d-%s', $groupSort, $leaderSort, $student->name);
            })->values();
        }

        $stats = [
            'enrolled' => $enrolledStudents->count(),
            'submitted' => $submissions->count(),
            'graded' => $scores->where('finalized_at', '!=', null)->count(),
            'released' => $scores->where('is_released', true)->count(),
        ];

        return view('tenant.assessments.submissions.index', compact(
            'tenant', 'course', 'assessment', 'enrolledStudents', 'submissions', 'scores', 'stats', 'groupInfoByUser'
        ));
    }

    public function show(string $tenantSlug, Course $course, Assessment $assessment, AssessmentSubmission $submission): View
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $submission->load(['user', 'files', 'score']);
        $assessment->load('rubric.criteria.levels');

        // Ordered list: ungraded first, then by submission time — for prev/next navigation
        $orderedIds = $assessment->submissions()
            ->orderByRaw("CASE WHEN status = 'graded' THEN 1 ELSE 0 END")
            ->orderBy('submitted_at')
            ->pluck('id');

        $pos = $orderedIds->search($submission->id);
        $prev = $pos > 0 ? AssessmentSubmission::find($orderedIds[$pos - 1]) : null;
        $next = $pos < $orderedIds->count() - 1 ? AssessmentSubmission::find($orderedIds[$pos + 1]) : null;

        $gradedCount = $assessment->submissions()->where('status', 'graded')->count();
        $totalCount = $orderedIds->count();

        return view('tenant.assessments.submissions.show', compact(
            'tenant', 'course', 'assessment', 'submission',
            'prev', 'next', 'gradedCount', 'totalCount'
        ));
    }

    public function storeMark(Request $request, string $tenantSlug, Course $course, Assessment $assessment, AssessmentSubmission $submission): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $assessment->load('rubric.criteria');
        $hasRubric = $assessment->rubric && $assessment->rubric->criteria->isNotEmpty();

        $rules = [
            'feedback' => ['nullable', 'string', 'max:5000'],
            'answer_script' => ['nullable', 'file', 'max:25600', 'mimes:pdf,jpg,jpeg,png'],
            'remove_answer_script' => ['nullable', 'in:0,1,true,false'],
        ];

        if ($hasRubric) {
            $rules['criteria_marks'] = ['required', 'array'];
            foreach ($assessment->rubric->criteria as $criterion) {
                $rules['criteria_marks.'.$criterion->id] = [
                    'required', 'numeric', 'min:0', 'max:'.(float) $criterion->max_marks,
                ];
            }
        } else {
            $rules['raw_marks'] = ['required', 'numeric', 'min:0', 'max:'.$assessment->total_marks];
        }

        $request->validate($rules);

        $criteriaMarksInput = null;
        if ($hasRubric) {
            // Treat the rubric as weighted only when EVERY criterion has an explicit
            // positive weight: each criterion's contribution = (score / max) × (weight/100)
            // × assessment.total_marks. If any criterion is missing a weight, fall back
            // to a plain sum so partial-weight rubrics don't silently undercount.
            $isWeighted = $assessment->rubric->criteria->every(
                fn ($c) => $c->weightage !== null && (float) $c->weightage > 0
            );

            $rawMarks = 0.0;
            $criteriaMarksInput = [];
            foreach ($assessment->rubric->criteria as $criterion) {
                $score = (float) ($request->input('criteria_marks.'.$criterion->id) ?? 0);
                $criteriaMarksInput[(string) $criterion->id] = $score;
                if ($isWeighted) {
                    $max = (float) $criterion->max_marks;
                    $weight = (float) ($criterion->weightage ?? 0);
                    if ($max > 0 && $weight > 0) {
                        $rawMarks += ($score / $max) * ($weight / 100) * (float) $assessment->total_marks;
                    }
                } else {
                    $rawMarks += $score;
                }
            }
            $rawMarks = round(min($rawMarks, (float) $assessment->total_marks), 2);
        } else {
            $rawMarks = (float) $request->raw_marks;
        }

        $maxMarks = (float) $assessment->total_marks;
        $percentage = $maxMarks > 0 ? round(($rawMarks / $maxMarks) * 100, 2) : 0;
        $weightedMarks = round($percentage * (float) $assessment->weightage / 100, 2);

        $tenant = app('current_tenant');

        // Collect every AssessmentSubmission that should receive this mark.
        // For a group submission, every member of the same student_group_id has a
        // mirrored AssessmentSubmission row (created at submit time), and each
        // needs its own AssessmentScore + 'graded' status so re-marking the
        // leader keeps every member in sync.
        $targetSubmissions = collect([$submission]);
        if ($submission->student_group_id) {
            $targetSubmissions = AssessmentSubmission::where('assessment_id', $assessment->id)
                ->where('student_group_id', $submission->student_group_id)
                ->get();
        }

        foreach ($targetSubmissions as $target) {
            AssessmentScore::updateOrCreate(
                ['assessment_id' => $assessment->id, 'user_id' => $target->user_id],
                [
                    'tenant_id' => $tenant->id,
                    'assessment_submission_id' => $target->id,
                    'raw_marks' => $rawMarks,
                    'max_marks' => $maxMarks,
                    'weighted_marks' => $weightedMarks,
                    'percentage' => $percentage,
                    'is_computed' => false,
                    'is_released' => false,
                    'feedback' => $request->feedback,
                    'criteria_marks' => $criteriaMarksInput,
                    'finalized_by' => auth()->id(),
                    'finalized_at' => now(),
                ]
            );

            if ($target->status !== 'graded') {
                $target->update(['status' => 'graded']);
            }
        }

        // Marking answer script — upload to lecturer's Drive (and remove the
        // previous one if the lecturer is replacing or clearing it). Failures
        // here are surfaced as flash warnings; they don't block the grade save.
        $scriptError = null;
        try {
            $this->syncMarkingScriptToDrive(
                $request,
                $course,
                $assessment,
                $submission,
                $targetSubmissions,
            );
        } catch (\Throwable $e) {
            Log::warning('marking script drive sync failed', [
                'assessment_id' => $assessment->id,
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);
            $scriptError = $e->getMessage();
        }

        // Stamp a grade-report cover page onto every PDF in the leader's
        // submission (members share the leader's files via the group — their
        // mirrored submissions carry no files). Failures are logged and
        // never block saving the grade.
        try {
            app(SubmissionReportStampingService::class)->stamp($submission->fresh(['files', 'user', 'score', 'assessment']));
        } catch (\Throwable $e) {
            Log::warning('grade stamping failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }

        $extraMembers = $targetSubmissions->count() - 1;
        $groupSuffix = $extraMembers > 0
            ? " Applied to {$extraMembers} group member(s)."
            : '';

        // "Save & Next" button sends next_id; "Save Grade" stays on this submission.
        if ($request->filled('next_id')) {
            $next = AssessmentSubmission::where('assessment_id', $assessment->id)
                ->where('id', $request->integer('next_id'))
                ->firstOrFail();

            $nextName = $next->user->name ?? '';

            $redirect = redirect()->route('tenant.assessments.submissions.show', [$tenantSlug, $course, $assessment, $next])
                ->with('success', "Graded {$submission->user->name}.{$groupSuffix} Up next: {$nextName}.");

            if ($scriptError) {
                $redirect->with('warning', "Grade saved, but the answer script could not be saved to Drive: {$scriptError}");
            }

            return $redirect;
        }

        $redirect = redirect()->route('tenant.assessments.submissions.show', [$tenantSlug, $course, $assessment, $submission])
            ->with('success', "Grade saved for {$submission->user->name}.{$groupSuffix}");

        if ($scriptError) {
            $redirect->with('warning', "Grade saved, but the answer script could not be saved to Drive: {$scriptError}");
        }

        return $redirect;
    }

    /**
     * Upload (or remove) the lecturer's marking answer script for this
     * submission to the course owner's Google Drive, mirroring the same
     * folder hierarchy student submissions use, and apply the resulting
     * Drive metadata to every group-mirrored AssessmentScore.
     *
     * Called from storeMark; throws on Drive failure so the caller can
     * surface a flash warning while still keeping the grade save.
     */
    private function syncMarkingScriptToDrive(
        Request $request,
        Course $course,
        Assessment $assessment,
        AssessmentSubmission $submission,
        Collection $targetSubmissions,
    ): void {
        $hasFile = $request->hasFile('answer_script');
        $remove = filter_var($request->input('remove_answer_script'), FILTER_VALIDATE_BOOLEAN);
        if (! $hasFile && ! $remove) {
            return;
        }

        $lecturer = $course->lecturer;
        if (! $lecturer) {
            throw new \RuntimeException('This course has no lecturer assigned.');
        }
        if (! $lecturer->isDriveConnected()) {
            throw new \RuntimeException('The course lecturer has not connected Google Drive.');
        }

        $driveService = app(GoogleDriveService::class);

        // Tear down any previously-uploaded script — for the lecturer's own
        // submission and for every mirrored group-member score that points
        // at the same file. Drive deletes are idempotent in our wrapper.
        $existingScores = AssessmentScore::where('assessment_id', $assessment->id)
            ->whereIn('user_id', $targetSubmissions->pluck('user_id'))
            ->get();

        $existingDriveIds = $existingScores->pluck('answer_script_drive_file_id')->filter()->unique();
        foreach ($existingDriveIds as $fileId) {
            $driveService->deleteFile($lecturer, $fileId);
        }

        // Always clear the script columns on every mirrored score before
        // writing the new ones, so a remove-and-replace stays consistent.
        AssessmentScore::where('assessment_id', $assessment->id)
            ->whereIn('user_id', $targetSubmissions->pluck('user_id'))
            ->update([
                'answer_script_drive_file_id' => null,
                'answer_script_drive_link' => null,
                'answer_script_filename' => null,
            ]);

        if (! $hasFile) {
            return;
        }

        // Reuse the per-student folder student submissions already use, so
        // the lecturer's marking script sits next to the student's work.
        $studentFolderId = $submission->drive_folder_id
            ?: $this->ensureStudentSubmissionFolder($driveService, $lecturer, $course, $assessment, $submission);

        $file = $request->file('answer_script');
        $datePrefix = now()->format('Y-m-d');
        $safeName = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $file->getClientOriginalName());
        $driveName = "[Marking] {$datePrefix} {$safeName}";

        $result = $driveService->uploadFile(
            $lecturer,
            $file->getRealPath(),
            $driveName,
            $file->getClientMimeType(),
            $studentFolderId,
        );

        AssessmentScore::where('assessment_id', $assessment->id)
            ->whereIn('user_id', $targetSubmissions->pluck('user_id'))
            ->update([
                'answer_script_drive_file_id' => $result['id'],
                'answer_script_drive_link' => $result['web_view_link'],
                'answer_script_filename' => $file->getClientOriginalName(),
            ]);
    }

    private function ensureStudentSubmissionFolder(
        GoogleDriveService $driveService,
        User $lecturer,
        Course $course,
        Assessment $assessment,
        AssessmentSubmission $submission,
    ): string {
        $courseFolderId = $driveService->findOrCreateFolder(
            $lecturer,
            "{$course->code} — {$course->title}",
        );
        $submissionsFolderId = $driveService->findOrCreateFolder(
            $lecturer, 'Submissions', $courseFolderId,
        );
        $typeLabel = ucfirst($assessment->type ?? 'Assessment');
        $assessmentFolderId = $driveService->findOrCreateFolder(
            $lecturer, "[{$typeLabel}] {$assessment->title}", $submissionsFolderId,
        );
        $studentName = $submission->user->name ?? "Student {$submission->user_id}";
        $studentFolderId = $driveService->findOrCreateFolder(
            $lecturer, $studentName, $assessmentFolderId,
        );

        if (! $submission->drive_folder_id) {
            $submission->update(['drive_folder_id' => $studentFolderId]);
        }

        return $studentFolderId;
    }

    public function release(Request $request, string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $query = $assessment->scores()->whereNotNull('finalized_at')->where('is_released', false);

        if ($request->has('score_ids')) {
            $request->validate(['score_ids' => ['required', 'array'], 'score_ids.*' => ['integer']]);

            // Expand score_ids so that releasing any score belonging to a group
            // submission also releases every other group member's score, keeping
            // the group's release state in sync with the lecturer's action.
            $requestedIds = collect($request->score_ids)->map(fn ($id) => (int) $id)->all();

            $groupIds = AssessmentSubmission::where('assessment_id', $assessment->id)
                ->whereIn('id', function ($q) use ($assessment, $requestedIds) {
                    $q->select('assessment_submission_id')
                        ->from('assessment_scores')
                        ->where('assessment_id', $assessment->id)
                        ->whereIn('id', $requestedIds);
                })
                ->whereNotNull('student_group_id')
                ->pluck('student_group_id')
                ->unique()
                ->all();

            $expandedIds = $requestedIds;
            if (! empty($groupIds)) {
                $groupMemberScoreIds = AssessmentScore::where('assessment_id', $assessment->id)
                    ->whereIn('assessment_submission_id', function ($q) use ($assessment, $groupIds) {
                        $q->select('id')
                            ->from('assessment_submissions')
                            ->where('assessment_id', $assessment->id)
                            ->whereIn('student_group_id', $groupIds);
                    })
                    ->pluck('id')
                    ->all();

                $expandedIds = array_values(array_unique(array_merge($expandedIds, $groupMemberScoreIds)));
            }

            $query->whereIn('id', $expandedIds);
        }

        $scores = $query->with('user')->get();

        foreach ($scores as $score) {
            $score->update([
                'is_released' => true,
                'released_at' => now(),
            ]);

            if ($score->user) {
                $score->user->notify(new AssessmentMarksReleased($assessment, $score));
            }
        }

        return back()->with('success', "Marks released for {$scores->count()} student(s).");
    }

    public function unrelease(string $tenantSlug, Course $course, Assessment $assessment, AssessmentScore $score): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $score->update([
            'is_released' => false,
            'released_at' => null,
        ]);

        return back()->with('success', 'Marks retracted.');
    }

    public function downloadFile(string $tenantSlug, Course $course, Assessment $assessment, AssessmentSubmissionFile $file): StreamedResponse
    {
        $submission = $file->submission;

        // Lecturer can download any file; student can download own files
        $user = auth()->user();
        $isLecturer = $this->isCourseOwner($course) || Section::where('course_id', $course->id)->whereHas('lecturers', fn ($q) => $q->where('user_id', $user->id))->exists();
        if (! $isLecturer && $submission->user_id !== $user->id) {
            abort(403);
        }

        // Students always get the graded copy (cover page + original) once
        // it exists. Lecturers default to the same, but can fetch the raw
        // source by appending ?original=1 — handy for re-reading the
        // unannotated submission.
        $path = $file->viewablePath();
        if ($isLecturer && request()->boolean('original')) {
            $path = $file->storage_path;
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->download($path, $file->file_name);
    }

    public function viewFile(string $tenantSlug, Course $course, Assessment $assessment, AssessmentSubmissionFile $file): BinaryFileResponse
    {
        $submission = $file->submission;

        $user = auth()->user();
        $isLecturer = $this->isCourseOwner($course) || Section::where('course_id', $course->id)->whereHas('lecturers', fn ($q) => $q->where('user_id', $user->id))->exists();
        if (! $isLecturer && $submission->user_id !== $user->id) {
            abort(403);
        }

        $path = $file->viewablePath();
        if ($isLecturer && request()->boolean('original')) {
            $path = $file->storage_path;
        }

        $absolutePath = Storage::disk('local')->path($path);

        if (! file_exists($absolutePath)) {
            abort(404, 'File not found.');
        }

        return response()->file($absolutePath, [
            'Content-Type' => $file->file_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($file->file_name).'"',
        ]);
    }

    /**
     * Persist the lecturer's pen annotations for one submission file.
     *
     *   strokes: vector JSON the canvas re-loads on next visit so marks
     *            stay editable.
     *   image:   flattened PNG data URL the student sees alongside the original.
     */
    public function storeAnnotations(
        Request $request,
        string $tenantSlug,
        Course $course,
        Assessment $assessment,
        AssessmentSubmissionFile $file,
    ): JsonResponse {
        $this->authorizeCourseAccess($course);
        $this->ensureFileBelongsHere($course, $assessment, $file);

        $data = $request->validate([
            'strokes' => ['required', 'array'],
            'image' => ['nullable', 'string'],
        ]);

        $imagePath = $file->annotated_image_path;

        if (! empty($data['image'])) {
            if (! preg_match('#^data:image/(png|jpe?g);base64,(.+)$#', $data['image'], $m)) {
                abort(422, 'Annotated image must be a PNG or JPEG data URL.');
            }
            $ext = $m[1] === 'png' ? 'png' : 'jpg';
            $payload = base64_decode($m[2], true);
            if ($payload === false) {
                abort(422, 'Could not decode annotated image.');
            }
            if ($imagePath && Storage::disk('local')->exists($imagePath)) {
                Storage::disk('local')->delete($imagePath);
            }
            $imagePath = sprintf(
                'assessment-annotations/%d/%d-%s.%s',
                $file->assessment_submission_id,
                $file->id,
                Str::random(8),
                $ext,
            );
            Storage::disk('local')->put($imagePath, $payload);
        }

        $file->update([
            'annotations' => $data['strokes'],
            'annotated_image_path' => $imagePath,
            'annotated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'annotated_at' => $file->annotated_at?->toIso8601String(),
        ]);
    }

    /**
     * Wipe the lecturer's annotations for this file.
     */
    public function destroyAnnotations(
        string $tenantSlug,
        Course $course,
        Assessment $assessment,
        AssessmentSubmissionFile $file,
    ): RedirectResponse {
        $this->authorizeCourseAccess($course);
        $this->ensureFileBelongsHere($course, $assessment, $file);

        if ($file->annotated_image_path && Storage::disk('local')->exists($file->annotated_image_path)) {
            Storage::disk('local')->delete($file->annotated_image_path);
        }

        $file->update([
            'annotations' => null,
            'annotated_image_path' => null,
            'annotated_at' => null,
        ]);

        return back()->with('success', 'Annotations cleared.');
    }

    /**
     * Serve the flattened annotated PNG to lecturer or submission owner.
     */
    public function annotatedImage(
        string $tenantSlug,
        Course $course,
        Assessment $assessment,
        AssessmentSubmissionFile $file,
    ): Response {
        $user = auth()->user();
        $submission = $file->submission;

        $isLecturer = $this->isCourseOwner($course)
            || Section::where('course_id', $course->id)
                ->whereHas('lecturers', fn ($q) => $q->where('user_id', $user->id))
                ->exists();

        if (! $isLecturer && (! $submission || $submission->user_id !== $user->id)) {
            abort(403);
        }

        $this->ensureFileBelongsHere($course, $assessment, $file);

        if (! $file->annotated_image_path || ! Storage::disk('local')->exists($file->annotated_image_path)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($file->annotated_image_path, PATHINFO_EXTENSION));
        $mime = $ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/png';

        return response(
            Storage::disk('local')->get($file->annotated_image_path),
            200,
            [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=120',
            ]
        );
    }

    private function ensureFileBelongsHere(Course $course, Assessment $assessment, AssessmentSubmissionFile $file): void
    {
        $submission = $file->submission;
        if (! $submission
            || $submission->assessment_id !== $assessment->id
            || $assessment->course_id !== $course->id) {
            abort(404);
        }
    }

    // ─── Student Methods ────────────────────────────────────────

    public function studentIndex(string $tenantSlug): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        $sectionIds = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('section_id');

        $courseIds = Section::whereIn('id', $sectionIds)->pluck('course_id')->unique();

        $assessments = Assessment::whereIn('course_id', $courseIds)
            ->where('requires_submission', true)
            ->whereIn('status', ['active', 'completed'])
            ->with('course')
            ->orderBy('due_date')
            ->get();

        $submissions = AssessmentSubmission::where('user_id', $user->id)
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->keyBy('assessment_id');

        $scores = AssessmentScore::where('user_id', $user->id)
            ->where('is_released', true)
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->keyBy('assessment_id');

        return view('tenant.assessments.student-index', compact(
            'tenant', 'assessments', 'submissions', 'scores'
        ));
    }

    public function studentShow(string $tenantSlug, Course $course, Assessment $assessment): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        // Verify enrollment
        $sectionIds = $course->sections()->pluck('id');
        $isEnrolled = SectionStudent::where('user_id', $user->id)
            ->whereIn('section_id', $sectionIds)
            ->where('is_active', true)
            ->exists();

        if (! $isEnrolled || $assessment->course_id !== $course->id) {
            abort(403);
        }

        $submission = AssessmentSubmission::where('assessment_id', $assessment->id)
            ->where('user_id', $user->id)
            ->with('files')
            ->first();

        $score = AssessmentScore::where('assessment_id', $assessment->id)
            ->where('user_id', $user->id)
            ->where('is_released', true)
            ->first();

        // Group submission context
        $myGroup = null;
        $isLeader = false;
        $groupLeader = null;
        $activeVoteRound = null;
        if ($assessment->usesGroupSubmission()) {
            $myGroup = $assessment->groupForUser($user->id);
            if ($myGroup) {
                $myGroup->load('members.user');
                $isLeader = $assessment->isGroupLeader($user->id);
                $groupLeader = $myGroup->leader();
                $activeVoteRound = $myGroup->activeVoteRound();
            }
        }

        return view('tenant.assessments.student-show', compact(
            'tenant', 'course', 'assessment', 'submission', 'score',
            'myGroup', 'isLeader', 'groupLeader', 'activeVoteRound'
        ));
    }

    public function studentSubmit(Request $request, string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        $user = auth()->user();

        // Verify enrollment
        $sectionIds = $course->sections()->pluck('id');
        $isEnrolled = SectionStudent::where('user_id', $user->id)
            ->whereIn('section_id', $sectionIds)
            ->where('is_active', true)
            ->exists();

        if (! $isEnrolled || ! $assessment->requires_submission || $assessment->course_id !== $course->id) {
            abort(403);
        }

        // Group submission gate: only the elected leader can submit
        $myGroup = null;
        $studentGroupId = null;
        if ($assessment->usesGroupSubmission()) {
            $myGroup = $assessment->groupForUser($user->id);
            if (! $myGroup) {
                return back()->withErrors(['files' => 'You are not assigned to a group for this assessment.']);
            }
            if (! $assessment->isGroupLeader($user->id)) {
                return back()->withErrors(['files' => 'Only the group leader can submit. Your group needs to hold a vote in the group workspace to elect one.']);
            }
            $studentGroupId = $myGroup->id;
        }

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Existing submission(s) for this user / group are treated as a replace
        // operation — the leader (or non-group submitter) is authorized to
        // overwrite their own work. Graded submissions stay locked.
        $existingQuery = AssessmentSubmission::where('assessment_id', $assessment->id);
        if ($studentGroupId) {
            $existingQuery->where('student_group_id', $studentGroupId);
        } else {
            $existingQuery->where('user_id', $user->id);
        }
        $existingSubmissions = $existingQuery->with('files')->get();

        if ($existingSubmissions->contains(fn ($s) => $s->status === 'graded')) {
            return back()->withErrors(['files' => 'Cannot replace a graded submission.']);
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
            'drive_folder_id' => null, // Will be set after folder creation
        ]);

        // File upload with optional Google Drive sync
        $driveFolderId = null;
        $driveContext = [
            'assessment_id' => $assessment->id,
            'submission_id' => $submission->id,
            'course_id' => $course->id,
            'student_user_id' => $user->id,
        ];

        if (! $lecturer) {
            Log::info('drive submission skipped: course has no lecturer_id', $driveContext);
        } elseif (! $lecturer->isDriveConnected()) {
            Log::info('drive submission skipped: lecturer has not connected Drive', $driveContext + [
                'lecturer_id' => $lecturer->id,
                'lecturer_email' => $lecturer->email,
            ]);
        } else {
            try {
                $driveService = app(GoogleDriveService::class);

                // Level 1: Course folder
                $courseFolderId = $driveService->findOrCreateFolder(
                    $lecturer,
                    "{$assessment->course->code} — {$assessment->course->title}"
                );

                // Level 2: Submissions folder inside course
                $submissionsFolderId = $driveService->findOrCreateFolder(
                    $lecturer, 'Submissions', $courseFolderId
                );

                // Level 3: Per-assessment folder with type label
                $typeLabel = ucfirst($assessment->type ?? 'Assessment');
                $assessmentFolderName = "[{$typeLabel}] {$assessment->title}";
                $assessmentFolderId = $driveService->findOrCreateFolder(
                    $lecturer, $assessmentFolderName, $submissionsFolderId
                );

                // Level 4: Per-student folder
                $driveFolderId = $driveService->findOrCreateFolder(
                    $lecturer, $user->name, $assessmentFolderId
                );

                // Store folder ID on submission for later deletion
                $submission->update(['drive_folder_id' => $driveFolderId]);
            } catch (\Throwable $e) {
                $driveFolderId = null;
                Log::warning('drive submission folder creation failed', $driveContext + [
                    'lecturer_id' => $lecturer->id,
                    'lecturer_email' => $lecturer->email,
                    'error' => $e->getMessage(),
                    'exception_class' => $e::class,
                ]);
            }
        }

        foreach ($request->file('files') as $file) {
            $path = $file->store('assessment_submissions/'.$assessment->id, 'local');
            $driveFileId = null;

            if ($driveFolderId && $lecturer) {
                try {
                    // Sanitize filename and add date prefix
                    $datePrefix = now()->format('Y-m-d');
                    $safeName = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $file->getClientOriginalName());
                    $driveName = "[{$datePrefix}] {$safeName}";

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

        // Mirror the submission to every other group member so marks/release
        // propagate uniformly. Members share the same files/notes via the
        // leader's submission record; each mirror is a lightweight pointer.
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

        // Notify lecturer
        if ($lecturer) {
            $lecturer->notify(new AssessmentSubmissionReceived($assessment, $user));
        }

        $msg = $myGroup
            ? 'Group submission uploaded successfully.'
            : 'Submission uploaded successfully.';

        return back()->with('success', $msg.($isLate ? ' (Late submission)' : ''));
    }

    public function studentDeleteSubmission(string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        $user = auth()->user();

        // Verify enrollment
        $sectionIds = $course->sections()->pluck('id');
        $isEnrolled = SectionStudent::where('user_id', $user->id)
            ->whereIn('section_id', $sectionIds)
            ->where('is_active', true)
            ->exists();

        if (! $isEnrolled || $assessment->course_id !== $course->id) {
            abort(403);
        }

        $assessment->load('course');
        $lecturer = $assessment->course->lecturer;

        // Group submissions: only the elected leader may delete, and the
        // delete sweeps every mirror row for the group so subsequent submits
        // are not blocked by orphaned mirrors from members.
        if ($assessment->usesGroupSubmission()) {
            $myGroup = $assessment->groupForUser($user->id);
            if (! $myGroup || ! $assessment->isGroupLeader($user->id)) {
                return back()->withErrors(['submission' => 'Only the group leader can delete the submission.']);
            }

            $submissions = AssessmentSubmission::where('assessment_id', $assessment->id)
                ->where('student_group_id', $myGroup->id)
                ->with(['files', 'score'])
                ->get();

            if ($submissions->contains(fn ($s) => $s->status === 'graded')) {
                return back()->withErrors(['submission' => 'Cannot delete a graded submission.']);
            }

            foreach ($submissions as $sub) {
                $this->purgeSubmissionArtifacts($sub, $lecturer);
                $sub->forceDelete();
            }

            return redirect()->route('tenant.my-assessments.show', [$tenantSlug, $course, $assessment])
                ->with('success', 'Submission deleted. You may resubmit if needed.');
        }

        $submission = AssessmentSubmission::where('assessment_id', $assessment->id)
            ->where('user_id', $user->id)
            ->with(['files', 'score'])
            ->firstOrFail();

        if ($submission->status === 'graded') {
            return back()->withErrors(['submission' => 'Cannot delete a graded submission.']);
        }

        $this->purgeSubmissionArtifacts($submission, $lecturer);
        $submission->forceDelete();

        return redirect()->route('tenant.my-assessments.show', [$tenantSlug, $course, $assessment])
            ->with('success', 'Submission deleted. You may resubmit if needed.');
    }

    /**
     * Delete a submission's local files and remote Drive folder.
     * Drive failures are swallowed — local cleanup must still proceed.
     */
    private function purgeSubmissionArtifacts(AssessmentSubmission $submission, ?User $lecturer): void
    {
        $submission->loadMissing('files');

        foreach ($submission->files as $file) {
            if (Storage::disk('local')->exists($file->storage_path)) {
                Storage::disk('local')->delete($file->storage_path);
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

    public function studentResubmit(Request $request, string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        $user = auth()->user();

        // Verify enrollment
        $sectionIds = $course->sections()->pluck('id');
        $isEnrolled = SectionStudent::where('user_id', $user->id)
            ->whereIn('section_id', $sectionIds)
            ->where('is_active', true)
            ->exists();

        if (! $isEnrolled || $assessment->course_id !== $course->id) {
            abort(403);
        }

        // Group submissions: only the leader can replace. The "files holder"
        // is whichever row currently has files attached (typically the row
        // created by the original submitter, which may be a previous leader).
        // Falls back to the current user's row when no files exist yet.
        $myGroup = null;
        $studentGroupId = null;
        if ($assessment->usesGroupSubmission()) {
            $myGroup = $assessment->groupForUser($user->id);
            if (! $myGroup || ! $assessment->isGroupLeader($user->id)) {
                return back()->withErrors(['files' => 'Only the group leader can replace the submission.']);
            }
            $studentGroupId = $myGroup->id;

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
                return back()->withErrors(['files' => 'No submission found to replace.']);
            }
        } else {
            $submission = AssessmentSubmission::where('assessment_id', $assessment->id)
                ->where('user_id', $user->id)
                ->with('files')
                ->firstOrFail();
        }

        if ($submission->status === 'graded') {
            return back()->withErrors(['files' => 'Cannot modify a graded submission.']);
        }

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $assessment->load('course');
        $lecturer = $assessment->course->lecturer;

        $this->purgeSubmissionArtifacts($submission, $lecturer);

        // Build new Drive folder structure
        $driveFolderId = null;
        $driveContext = [
            'assessment_id' => $assessment->id,
            'submission_id' => $submission->id,
            'course_id' => $course->id,
            'student_user_id' => $user->id,
            'flow' => 'resubmit',
        ];

        if (! $lecturer) {
            Log::info('drive submission skipped: course has no lecturer_id', $driveContext);
        } elseif (! $lecturer->isDriveConnected()) {
            Log::info('drive submission skipped: lecturer has not connected Drive', $driveContext + [
                'lecturer_id' => $lecturer->id,
                'lecturer_email' => $lecturer->email,
            ]);
        } else {
            try {
                $driveService = app(GoogleDriveService::class);

                // Level 1: Course folder
                $courseFolderId = $driveService->findOrCreateFolder(
                    $lecturer,
                    "{$assessment->course->code} — {$assessment->course->title}"
                );

                // Level 2: Submissions folder
                $submissionsFolderId = $driveService->findOrCreateFolder(
                    $lecturer, 'Submissions', $courseFolderId
                );

                // Level 3: Per-assessment folder with type label
                $typeLabel = ucfirst($assessment->type ?? 'Assessment');
                $assessmentFolderName = "[{$typeLabel}] {$assessment->title}";
                $assessmentFolderId = $driveService->findOrCreateFolder(
                    $lecturer, $assessmentFolderName, $submissionsFolderId
                );

                // Level 4: Per-student folder
                $driveFolderId = $driveService->findOrCreateFolder(
                    $lecturer, $user->name, $assessmentFolderId
                );
            } catch (\Throwable $e) {
                $driveFolderId = null;
                Log::warning('drive submission folder creation failed', $driveContext + [
                    'lecturer_id' => $lecturer->id,
                    'lecturer_email' => $lecturer->email,
                    'error' => $e->getMessage(),
                    'exception_class' => $e::class,
                ]);
            }
        }

        // Upload new files
        $isLate = $assessment->due_date && now()->isAfter($assessment->due_date);

        foreach ($request->file('files') as $file) {
            $path = $file->store('assessment_submissions/'.$assessment->id, 'local');
            $driveFileId = null;

            if ($driveFolderId && $lecturer) {
                try {
                    // Sanitize filename and add date prefix
                    $datePrefix = now()->format('Y-m-d');
                    $safeName = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $file->getClientOriginalName());
                    $driveName = "[{$datePrefix}] {$safeName}";

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

        // Update submission
        $submission->update([
            'notes' => $request->notes,
            'is_late' => $isLate,
            'submitted_at' => now(),
            'status' => 'submitted',
            'drive_folder_id' => $driveFolderId,
        ]);

        // Keep every group member's mirror row in sync so timestamps and
        // status reflect the resubmission for the whole group.
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

        return back()->with('success', 'Submission updated successfully.'.($isLate ? ' (Late submission)' : ''));
    }
}
