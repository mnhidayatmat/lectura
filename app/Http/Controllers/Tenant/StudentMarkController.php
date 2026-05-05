<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AssessmentScore;
use App\Models\Assignment;
use App\Models\SectionStudent;
use App\Models\StudentMark;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentMarkController extends Controller
{
    public function index(): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        // Get courses the student is enrolled in
        $sectionIds = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('section_id');

        $courseIds = \App\Models\Section::whereIn('id', $sectionIds)->pluck('course_id')->unique();

        // Get all assignments for those courses that are published or later
        $assignments = Assignment::whereIn('course_id', $courseIds)
            ->whereIn('status', ['published', 'closed', 'marking', 'completed'])
            ->with(['course', 'section'])
            ->latest('deadline')
            ->get();

        // Get student's marks and submissions
        $marks = StudentMark::where('user_id', $user->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->with(['submission.feedback'])
            ->get()
            ->keyBy('assignment_id');

        $submissions = \App\Models\Submission::where('user_id', $user->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->with('feedback')
            ->get()
            ->keyBy('assignment_id');

        // Released assessment scores
        $assessmentScores = AssessmentScore::where('user_id', $user->id)
            ->where('is_released', true)
            ->whereHas('assessment', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->with(['assessment.course'])
            ->get();

        // Stats
        $totalAssignments = $assignments->count();
        $gradedCount = $marks->where('is_final', true)->count();
        $avgPercentage = $marks->where('is_final', true)->avg('percentage');
        $pendingCount = $totalAssignments - $gradedCount;

        return view('tenant.marks.index', compact(
            'tenant', 'assignments', 'marks', 'submissions',
            'totalAssignments', 'gradedCount', 'avgPercentage', 'pendingCount',
            'assessmentScores'
        ));
    }

    public function show(string $tenantSlug, StudentMark $mark): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        if ($mark->user_id !== $user->id) {
            abort(403);
        }

        $mark->load(['assignment.course', 'assignment.rubric.criteria', 'submission.feedback', 'submission.files']);

        return view('tenant.marks.show', compact('tenant', 'mark'));
    }

    public function viewAnswerScript(string $tenantSlug, AssessmentScore $score): BinaryFileResponse
    {
        $this->authorizeReleasedScript($score);

        $absolutePath = Storage::disk('local')->path($score->answer_script_path);
        if (! file_exists($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath);
    }

    public function downloadAnswerScript(string $tenantSlug, AssessmentScore $score): StreamedResponse
    {
        $this->authorizeReleasedScript($score);

        return Storage::disk('local')->download(
            $score->answer_script_path,
            $score->answer_script_filename ?? 'answer-script.pdf'
        );
    }

    private function authorizeReleasedScript(AssessmentScore $score): void
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        if ($score->user_id !== $user->id || ! $score->is_released || ! $score->answer_script_path) {
            abort(404);
        }

        $score->loadMissing('assessment.course');
        if (! $score->assessment || $score->assessment->course?->tenant_id !== $tenant->id) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($score->answer_script_path)) {
            abort(404);
        }
    }
}
