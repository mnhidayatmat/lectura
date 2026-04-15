<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\LiSupervisionDetail;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\StudentMark;
use App\Models\StudentMentorship;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenteeController extends Controller
{
    public function index(): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        $mentorships = StudentMentorship::query()
            ->where('lecturer_id', $user->id)
            ->active()
            ->with(['student', 'academicTerm', 'liDetail'])
            ->latest('assigned_at')
            ->get();

        $tutees = $mentorships->where('role', StudentMentorship::ROLE_ACADEMIC_TUTOR);
        $supervisees = $mentorships->where('role', StudentMentorship::ROLE_LI_SUPERVISOR);

        return view('tenant.mentees.index', compact('tenant', 'tutees', 'supervisees'));
    }

    public function show(string $tenantSlug, User $student): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        $mentorship = $this->authorizeAndLoadMentorship($user, $student);

        // Enrolled sections for this tenant
        $sectionIds = SectionStudent::where('user_id', $student->id)
            ->where('is_active', true)
            ->pluck('section_id');

        $sections = Section::whereIn('id', $sectionIds)
            ->with(['course:id,code,title,academic_term_id', 'course.academicTerm:id,name'])
            ->get();

        $courseIds = $sections->pluck('course_id')->unique();

        // Attendance summary per course
        $attendanceByCourse = [];
        foreach ($sections as $section) {
            $records = AttendanceRecord::whereHas('session', fn ($q) => $q->where('section_id', $section->id))
                ->where('user_id', $student->id)
                ->get();

            $total = $records->count();
            $present = $records->whereIn('status', ['present', 'late'])->count();
            $attendanceByCourse[$section->course_id] = [
                'total' => $total,
                'present' => $present,
                'percentage' => $total > 0 ? (int) round(($present / $total) * 100) : null,
            ];
        }

        // Grades — finalized student marks + released assessment scores
        $marks = StudentMark::where('user_id', $student->id)
            ->with(['assignment.course'])
            ->whereHas('assignment', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->get();

        $assessmentScores = AssessmentScore::where('user_id', $student->id)
            ->where('is_released', true)
            ->whereHas('assessment', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->with(['assessment.course'])
            ->get();

        $avgPercentage = $marks->where('is_final', true)->avg('percentage');

        return view('tenant.mentees.show', compact(
            'tenant', 'student', 'mentorship', 'sections',
            'attendanceByCourse', 'marks', 'assessmentScores', 'avgPercentage'
        ));
    }

    public function editLiDetails(string $tenantSlug, User $student): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        $mentorship = StudentMentorship::where('lecturer_id', $user->id)
            ->where('student_id', $student->id)
            ->where('role', StudentMentorship::ROLE_LI_SUPERVISOR)
            ->active()
            ->with('liDetail')
            ->firstOrFail();

        $detail = $mentorship->liDetail ?? new LiSupervisionDetail(['mentorship_id' => $mentorship->id]);

        return view('tenant.mentees.li-details', compact('tenant', 'student', 'mentorship', 'detail'));
    }

    public function updateLiDetails(Request $request, string $tenantSlug, User $student): RedirectResponse
    {
        $user = auth()->user();

        $mentorship = StudentMentorship::where('lecturer_id', $user->id)
            ->where('student_id', $student->id)
            ->where('role', StudentMentorship::ROLE_LI_SUPERVISOR)
            ->active()
            ->firstOrFail();

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'industry_supervisor_name' => ['nullable', 'string', 'max:255'],
            'industry_supervisor_email' => ['nullable', 'email', 'max:255'],
            'industry_supervisor_phone' => ['nullable', 'string', 'max:50'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'placement_status' => ['required', 'in:pending,ongoing,completed,terminated'],
            'final_evaluation_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'supervisor_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        LiSupervisionDetail::updateOrCreate(
            ['mentorship_id' => $mentorship->id],
            $validated
        );

        return redirect()
            ->route('tenant.mentees.show', [$tenantSlug, $student])
            ->with('success', 'LI supervision details updated.');
    }

    private function authorizeAndLoadMentorship(User $user, User $student): StudentMentorship
    {
        $mentorship = StudentMentorship::where('lecturer_id', $user->id)
            ->where('student_id', $student->id)
            ->active()
            ->with(['academicTerm', 'liDetail'])
            ->first();

        if (! $mentorship && ! $user->is_super_admin) {
            abort(403, 'You are not assigned as a mentor for this student.');
        }

        return $mentorship;
    }
}
