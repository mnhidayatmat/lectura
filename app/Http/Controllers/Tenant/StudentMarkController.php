<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\SectionStudent;
use App\Models\StudentMark;
use Illuminate\View\View;

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

        // Stats
        $totalAssignments = $assignments->count();
        $gradedCount = $marks->where('is_final', true)->count();
        $avgPercentage = $marks->where('is_final', true)->avg('percentage');
        $pendingCount = $totalAssignments - $gradedCount;

        return view('tenant.marks.index', compact(
            'tenant', 'assignments', 'marks', 'submissions',
            'totalAssignments', 'gradedCount', 'avgPercentage', 'pendingCount'
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
}
