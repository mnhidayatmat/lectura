<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceWarning;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseFolder;
use App\Models\Section;
use App\Models\SectionStudent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AttendanceReportService
{
    /**
     * Generate comprehensive attendance data for a course.
     */
    public function generateCourseReport(Course $course, ?Section $section = null): array
    {
        $sectionIds = $section
            ? collect([$section->id])
            : $course->sections()->pluck('id');

        $sessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'ended')
            ->with('section')
            ->orderBy('started_at')
            ->get();

        $sessionIds = $sessions->pluck('id');

        // All students enrolled in these sections
        $studentIds = SectionStudent::whereIn('section_id', $sectionIds)
            ->where('is_active', true)
            ->distinct()
            ->pluck('user_id');

        $students = \App\Models\User::whereIn('id', $studentIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // All records for these sessions
        $records = AttendanceRecord::whereIn('attendance_session_id', $sessionIds)
            ->get()
            ->groupBy('user_id');

        // Warnings
        $warnings = AttendanceWarning::where('course_id', $course->id)
            ->whereIn('user_id', $studentIds)
            ->get()
            ->groupBy('user_id');

        $policy = $course->attendancePolicy;

        // Build per-student summary
        $summary = $students->map(function ($student) use ($records, $sessions, $warnings, $policy) {
            $studentRecords = $records->get($student->id, collect());
            $present = $studentRecords->where('status', 'present')->count();
            $late = $studentRecords->where('status', 'late')->count();
            $absent = $studentRecords->where('status', 'absent')->count();
            $excused = $studentRecords->where('status', 'excused')->count();
            $total = $sessions->count();

            $absenceForCalc = $absent;
            if ($policy && $policy->include_late_as_absent) {
                $absenceForCalc += $late;
            }

            $rate = $total > 0 ? round(($total - $absenceForCalc) / $total * 100, 1) : 100;
            $maxWarning = $warnings->get($student->id)?->max('policy_level');

            return [
                'student' => $student,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'excused' => $excused,
                'total' => $total,
                'rate' => $rate,
                'warning_level' => $maxWarning,
            ];
        });

        // Build session detail matrix (student_id => session_id => status)
        $matrix = [];
        foreach ($students as $student) {
            $studentRecords = $records->get($student->id, collect())->keyBy('attendance_session_id');
            $matrix[$student->id] = [];
            foreach ($sessions as $session) {
                $record = $studentRecords->get($session->id);
                $matrix[$student->id][$session->id] = $record ? $record->status : null;
            }
        }

        return [
            'course' => $course,
            'section' => $section,
            'sessions' => $sessions,
            'students' => $students,
            'summary' => $summary,
            'matrix' => $matrix,
            'policy' => $policy,
        ];
    }

    /**
     * Generate PDF report and return the response.
     */
    public function exportPdf(Course $course, ?Section $section = null)
    {
        $data = $this->generateCourseReport($course, $section);

        $pdf = Pdf::loadView('tenant.attendance.report.pdf', $data);
        $pdf->setPaper('a4', 'landscape');

        $filename = $course->code . '_attendance_report.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate PDF, store to disk, and return the path.
     */
    public function generatePdfFile(Course $course, ?Section $section = null): string
    {
        $data = $this->generateCourseReport($course, $section);

        $pdf = Pdf::loadView('tenant.attendance.report.pdf', $data);
        $pdf->setPaper('a4', 'landscape');

        $path = "attendance-reports/{$course->id}/report_" . now()->format('Y_m_d_His') . '.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Sync attendance report into "Attendance Records" course folder.
     */
    public function syncToCourseFiles(Course $course): CourseFile
    {
        $path = $this->generatePdfFile($course);

        $folder = CourseFolder::firstOrCreate(
            ['course_id' => $course->id, 'name' => 'Attendance Records', 'parent_id' => null],
            ['sort_order' => 3],
        );

        // Remove previous auto-generated report
        CourseFile::where('course_folder_id', $folder->id)
            ->where('description', 'Auto-generated attendance report')
            ->delete();

        return CourseFile::create([
            'course_folder_id' => $folder->id,
            'course_id' => $course->id,
            'uploaded_by' => auth()->id(),
            'material_type' => 'file',
            'file_name' => $course->code . '_attendance_report.pdf',
            'file_type' => 'pdf',
            'file_size_bytes' => Storage::disk('local')->size($path),
            'storage_path' => $path,
            'description' => 'Auto-generated attendance report',
        ]);
    }
}
