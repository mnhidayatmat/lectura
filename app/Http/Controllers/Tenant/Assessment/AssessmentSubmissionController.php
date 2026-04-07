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
use App\Notifications\AssessmentMarksReleased;
use App\Notifications\AssessmentSubmissionReceived;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
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
        $enrolledStudents = \App\Models\User::whereIn('id', function ($q) use ($sectionIds) {
            $q->select('user_id')
                ->from('section_students')
                ->whereIn('section_id', $sectionIds)
                ->where('is_active', true);
        })->orderBy('name')->get();

        $submissions = $assessment->submissions()->with(['user', 'files'])->get()->keyBy('user_id');
        $scores = $assessment->scores()->get()->keyBy('user_id');

        $stats = [
            'enrolled' => $enrolledStudents->count(),
            'submitted' => $submissions->count(),
            'graded' => $scores->where('finalized_at', '!=', null)->count(),
            'released' => $scores->where('is_released', true)->count(),
        ];

        return view('tenant.assessments.submissions.index', compact(
            'tenant', 'course', 'assessment', 'enrolledStudents', 'submissions', 'scores', 'stats'
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

        // Ordered list: ungraded first, then by submission time — for prev/next navigation
        $orderedIds = $assessment->submissions()
            ->orderByRaw("CASE WHEN status = 'graded' THEN 1 ELSE 0 END")
            ->orderBy('submitted_at')
            ->pluck('id');

        $pos  = $orderedIds->search($submission->id);
        $prev = $pos > 0                        ? AssessmentSubmission::find($orderedIds[$pos - 1]) : null;
        $next = $pos < $orderedIds->count() - 1 ? AssessmentSubmission::find($orderedIds[$pos + 1]) : null;

        $gradedCount = $assessment->submissions()->where('status', 'graded')->count();
        $totalCount  = $orderedIds->count();

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

        $request->validate([
            'raw_marks' => ['required', 'numeric', 'min:0', 'max:' . $assessment->total_marks],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $rawMarks = (float) $request->raw_marks;
        $maxMarks = (float) $assessment->total_marks;
        $percentage = $maxMarks > 0 ? round(($rawMarks / $maxMarks) * 100, 2) : 0;
        $weightedMarks = round($percentage * (float) $assessment->weightage / 100, 2);

        $tenant = app('current_tenant');

        AssessmentScore::updateOrCreate(
            ['assessment_id' => $assessment->id, 'user_id' => $submission->user_id],
            [
                'tenant_id' => $tenant->id,
                'assessment_submission_id' => $submission->id,
                'raw_marks' => $rawMarks,
                'max_marks' => $maxMarks,
                'weighted_marks' => $weightedMarks,
                'percentage' => $percentage,
                'is_computed' => false,
                'is_released' => false,
                'feedback' => $request->feedback,
                'finalized_by' => auth()->id(),
                'finalized_at' => now(),
            ]
        );

        $submission->update(['status' => 'graded']);

        // "Save & Next" button sends next_id; "Save Grade" stays on this submission.
        if ($request->filled('next_id')) {
            $next = AssessmentSubmission::where('assessment_id', $assessment->id)
                ->where('id', $request->integer('next_id'))
                ->firstOrFail();

            $nextName = $next->user->name ?? '';

            return redirect()->route('tenant.assessments.submissions.show', [$tenantSlug, $course, $assessment, $next])
                ->with('success', "Graded {$submission->user->name}. Up next: {$nextName}.");
        }

        return redirect()->route('tenant.assessments.submissions.show', [$tenantSlug, $course, $assessment, $submission])
            ->with('success', "Grade saved for {$submission->user->name}.");
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
            $query->whereIn('id', $request->score_ids);
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

        if (! Storage::disk('local')->exists($file->storage_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->download($file->storage_path, $file->file_name);
    }

    // ─── Student Methods ────────────────────────────────────────

    public function studentIndex(string $tenantSlug): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        $sectionIds = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('section_id');

        $courseIds = \App\Models\Section::whereIn('id', $sectionIds)->pluck('course_id')->unique();

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

        return view('tenant.assessments.student-show', compact(
            'tenant', 'course', 'assessment', 'submission', 'score'
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

        // Check if already submitted
        $existing = AssessmentSubmission::where('assessment_id', $assessment->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($existing) {
            return back()->withErrors(['files' => 'You have already submitted for this assessment.']);
        }

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $isLate = $assessment->due_date && now()->isAfter($assessment->due_date);
        $tenant = app('current_tenant');

        $submission = AssessmentSubmission::create([
            'tenant_id' => $tenant->id,
            'assessment_id' => $assessment->id,
            'user_id' => $user->id,
            'notes' => $request->notes,
            'is_late' => $isLate,
            'submitted_at' => now(),
            'status' => 'submitted',
            'drive_folder_id' => null, // Will be set after folder creation
        ]);

        // File upload with optional Google Drive sync
        $assessment->load('course');
        $lecturer = $assessment->course->lecturer;
        $driveFolderId = null;

        if ($lecturer && $lecturer->isDriveConnected()) {
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
            } catch (\Throwable) {
                $driveFolderId = null;
            }
        }

        foreach ($request->file('files') as $file) {
            $path = $file->store('assessment_submissions/' . $assessment->id, 'local');
            $driveFileId = null;

            if ($driveFolderId && $lecturer) {
                try {
                    // Sanitize filename and add date prefix
                    $datePrefix = now()->format('Y-m-d');
                    $safeName   = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $file->getClientOriginalName());
                    $driveName  = "[{$datePrefix}] {$safeName}";

                    $result = app(GoogleDriveService::class)->uploadFile(
                        $lecturer,
                        $file->getRealPath(),
                        $driveName,
                        $file->getClientMimeType(),
                        $driveFolderId
                    );
                    $driveFileId = $result['id'];
                } catch (\Throwable) {
                    // Drive upload failed — keep local copy
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

        // Notify lecturer
        if ($lecturer) {
            $lecturer->notify(new AssessmentSubmissionReceived($assessment, $user));
        }

        return back()->with('success', 'Submission uploaded successfully.' . ($isLate ? ' (Late submission)' : ''));
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

        if (!$isEnrolled || $assessment->course_id !== $course->id) {
            abort(403);
        }

        $submission = AssessmentSubmission::where('assessment_id', $assessment->id)
            ->where('user_id', $user->id)
            ->with(['files', 'score'])
            ->firstOrFail();

        // Prevent deletion of graded submissions
        if ($submission->status === 'graded') {
            return back()->withErrors(['submission' => 'Cannot delete a graded submission.']);
        }

        $assessment->load('course');
        $lecturer = $assessment->course->lecturer;

        // Delete local files
        foreach ($submission->files as $file) {
            if (Storage::disk('local')->exists($file->storage_path)) {
                Storage::disk('local')->delete($file->storage_path);
            }
            $file->delete();
        }

        // Delete entire Drive folder
        if ($submission->drive_folder_id && $lecturer) {
            try {
                app(GoogleDriveService::class)->deleteFile($lecturer, $submission->drive_folder_id);
            } catch (\Throwable) {
                // Drive deletion failed — submission still locally deleted
            }
        }

        // Force delete submission to clear unique constraint
        $submission->forceDelete();

        return redirect()->route('tenant.my-assessments.show', [$tenantSlug, $course, $assessment])
            ->with('success', 'Submission deleted. You may resubmit if needed.');
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

        if (!$isEnrolled || $assessment->course_id !== $course->id) {
            abort(403);
        }

        $submission = AssessmentSubmission::where('assessment_id', $assessment->id)
            ->where('user_id', $user->id)
            ->with('files')
            ->firstOrFail();

        // Prevent resubmission of graded submissions
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

        // Delete old local files
        foreach ($submission->files as $file) {
            if (Storage::disk('local')->exists($file->storage_path)) {
                Storage::disk('local')->delete($file->storage_path);
            }
            $file->delete();
        }

        // Delete old Drive folder
        if ($submission->drive_folder_id && $lecturer) {
            try {
                app(GoogleDriveService::class)->deleteFile($lecturer, $submission->drive_folder_id);
            } catch (\Throwable) {
                // Drive deletion failed — continue with local update
            }
        }

        // Build new Drive folder structure
        $driveFolderId = null;
        if ($lecturer && $lecturer->isDriveConnected()) {
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
            } catch (\Throwable) {
                $driveFolderId = null;
            }
        }

        // Upload new files
        $isLate = $assessment->due_date && now()->isAfter($assessment->due_date);

        foreach ($request->file('files') as $file) {
            $path = $file->store('assessment_submissions/' . $assessment->id, 'local');
            $driveFileId = null;

            if ($driveFolderId && $lecturer) {
                try {
                    // Sanitize filename and add date prefix
                    $datePrefix = now()->format('Y-m-d');
                    $safeName   = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $file->getClientOriginalName());
                    $driveName  = "[{$datePrefix}] {$safeName}";

                    $result = app(GoogleDriveService::class)->uploadFile(
                        $lecturer,
                        $file->getRealPath(),
                        $driveName,
                        $file->getClientMimeType(),
                        $driveFolderId
                    );
                    $driveFileId = $result['id'];
                } catch (\Throwable) {
                    // Drive upload failed — keep local copy
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

        return back()->with('success', 'Submission updated successfully.' . ($isLate ? ' (Late submission)' : ''));
    }
}
