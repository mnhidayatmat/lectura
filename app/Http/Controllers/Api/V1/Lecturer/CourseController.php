<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Lecturer\CourseDetailResource;
use App\Http\Resources\Api\V1\Lecturer\CourseSummaryResource;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    use AuthorizesLecturerAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureLecturer();

        $user = $request->user();

        $ownedCourseIds = Course::where('lecturer_id', $user->id)->pluck('id');
        $sectionCourseIds = Section::whereHas('lecturers', fn ($q) => $q->where('user_id', $user->id))->pluck('course_id');

        $courses = Course::whereIn('id', $ownedCourseIds->merge($sectionCourseIds)->unique())
            ->withCount('sections')
            ->with(['academicTerm', 'faculty'])
            ->latest()
            ->get();

        return CourseSummaryResource::collection($courses);
    }

    public function show(Course $course): CourseDetailResource
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        $isOwner = $this->isCourseOwner($course);

        // Owners see every section (as on the web course page); section lecturers only their own
        $sections = ($isOwner ? $course->sections() : $this->lecturerSections($course))
            ->with(['academicTerm', 'lecturers'])
            ->withCount('activeStudents')
            ->orderBy('id')
            ->get();

        $activeSessions = AttendanceSession::whereIn('section_id', $sections->pluck('id'))
            ->where('status', 'active')
            ->pluck('id', 'section_id');

        $sections->each(fn (Section $section) => $section->setAttribute('active_session_id', $activeSessions[$section->id] ?? null));

        $course->load(['academicTerm', 'faculty', 'programme', 'learningOutcomes', 'topics']);
        $course->loadCount('sections');
        $course->setRelation('sections', $sections);

        return (new CourseDetailResource($course))->forOwner($isOwner);
    }
}
