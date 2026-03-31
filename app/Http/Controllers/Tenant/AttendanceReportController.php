<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Exports\CourseAttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Section;
use App\Services\Attendance\AttendanceReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    public function __construct(
        protected AttendanceReportService $reportService,
    ) {}

    /**
     * Show comprehensive attendance report.
     */
    public function show(Request $request, string $tenantSlug, Course $course): View
    {
        $this->authorizeCourse($course);

        $section = null;
        if ($request->filled('section_id')) {
            $section = Section::where('course_id', $course->id)
                ->findOrFail($request->section_id);
        }

        $report = $this->reportService->generateCourseReport($course, $section);

        $sections = $course->sections;

        return view('tenant.attendance.report.show', compact('course', 'report', 'sections', 'section'));
    }

    /**
     * Download PDF report.
     */
    public function downloadPdf(Request $request, string $tenantSlug, Course $course)
    {
        $this->authorizeCourse($course);

        $section = null;
        if ($request->filled('section_id')) {
            $section = Section::where('course_id', $course->id)
                ->findOrFail($request->section_id);
        }

        return $this->reportService->exportPdf($course, $section);
    }

    /**
     * Download Excel report.
     */
    public function downloadExcel(Request $request, string $tenantSlug, Course $course)
    {
        $this->authorizeCourse($course);

        $section = null;
        if ($request->filled('section_id')) {
            $section = Section::where('course_id', $course->id)
                ->findOrFail($request->section_id);
        }

        $report = $this->reportService->generateCourseReport($course, $section);

        $filename = $course->code . '_attendance_report.xlsx';

        return (new CourseAttendanceExport($report))->download($filename);
    }

    /**
     * Sync report to Course Files.
     */
    public function syncToCourseFiles(string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);

        $this->reportService->syncToCourseFiles($course);

        return back()->with('success', 'Attendance report saved to Course Files under "Attendance Records" folder.');
    }

    protected function authorizeCourse(Course $course): void
    {
        if ($course->lecturer_id !== auth()->id() && ! auth()->user()->is_super_admin) {
            abort(403);
        }
    }
}
