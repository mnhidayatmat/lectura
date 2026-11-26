<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Exports\CourseAttendanceExport;
use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Section;
use App\Services\Attendance\AttendanceReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(
        protected AttendanceReportService $reportService,
    ) {}

    /**
     * Show comprehensive attendance report.
     */
    public function show(Request $request, string $tenantSlug, Course $course): View
    {
        $this->authorizeCourse($course);

        [$sections, $section] = $this->reportSections($request, $course);

        $report = $this->reportService->generateCourseReport($course, $section, $sections->pluck('id'));

        return view('tenant.attendance.report.show', compact('course', 'report', 'sections', 'section'));
    }

    /**
     * Download PDF report.
     */
    public function downloadPdf(Request $request, string $tenantSlug, Course $course)
    {
        $this->authorizeCourse($course);

        [$sections, $section] = $this->reportSections($request, $course);

        return $this->reportService->exportPdf($course, $section, $sections->pluck('id'));
    }

    /**
     * Download Excel report.
     */
    public function downloadExcel(Request $request, string $tenantSlug, Course $course)
    {
        $this->authorizeCourse($course);

        [$sections, $section] = $this->reportSections($request, $course);

        $report = $this->reportService->generateCourseReport($course, $section, $sections->pluck('id'));

        $filename = $course->code . '_attendance_report.xlsx';

        return (new CourseAttendanceExport($report))->download($filename);
    }

    /**
     * Sync report to Course Files.
     */
    public function syncToCourseFiles(string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);

        $this->reportService->syncToCourseFiles($course, $this->reportSections(request(), $course)[0]->pluck('id'));

        return back()->with('success', 'Attendance report saved to Course Files under "Attendance Records" folder.');
    }

    /**
     * The sections this lecturer may report on — their own, this semester's — and the one
     * `section_id` picks (null = all of them). Another lecturer's section is a 404.
     *
     * @return array{0: Collection<int, Section>, 1: ?Section}
     */
    protected function reportSections(Request $request, Course $course): array
    {
        $sections = $course->currentSectionsOf($this->lecturerSections($course)->orderBy('name')->get());

        $section = $request->filled('section_id')
            ? ($sections->firstWhere('id', (int) $request->input('section_id')) ?? abort(404))
            : null;

        return [$sections, $section];
    }

    protected function authorizeCourse(Course $course): void
    {
        $this->authorizeCourseAccess($course);
    }
}
