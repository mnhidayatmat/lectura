<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Lecturer\CourseDetailResource;
use App\Http\Resources\Api\V1\Lecturer\CourseSummaryResource;
use App\Models\AcademicTerm;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
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
            ->with(['academicTerm', 'faculty', 'sections.academicTerm'])
            // The app groups by semester but the payload carries no term dates, so
            // it relies on this order. Keep the two in step if either changes.
            ->orderByDesc(AcademicTerm::select('start_date')->whereColumn('academic_terms.id', 'courses.academic_term_id'))
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

        // Inactive sections stay listed so they can be switched back on, but without a live session
        $activeSessions = AttendanceSession::whereIn('section_id', $sections->where('is_active', true)->pluck('id'))
            ->where('status', 'active')
            ->pluck('id', 'section_id');

        $sections->each(fn (Section $section) => $section->setAttribute('active_session_id', $activeSessions[$section->id] ?? null));

        $course->load(['academicTerm', 'faculty', 'programme', 'learningOutcomes', 'topics']);
        $course->loadCount('sections');
        $course->setRelation('sections', $sections);

        return (new CourseDetailResource($course))->forOwner($isOwner);
    }

    /**
     * Take over a course by its invite code, as `courses/join` does on the web.
     */
    public function join(Request $request): JsonResponse
    {
        $request->validate(['invite_code' => ['required', 'string', 'max:20']]);

        $user = $request->user();

        if (! $user->hasRoleInTenant(app('current_tenant')->id, ['lecturer', 'admin'])) {
            abort(403, 'Only lecturers can join a course.');
        }

        $code = preg_replace('/[^A-Z0-9]/', '', strtoupper($request->string('invite_code')->toString()));
        $course = Course::where('invite_code', $code)->first();

        if (! $course) {
            return response()->json([
                'message' => 'Invalid course invite code. Please check and try again.',
                'errors' => ['invite_code' => ['Invalid course invite code. Please check and try again.']],
            ], 422);
        }

        $alreadyYours = $course->lecturer_id === $user->id;

        if (! $alreadyYours) {
            $course->update(['lecturer_id' => $user->id]);
        }

        return response()->json([
            'message' => $alreadyYours
                ? "You are already the lecturer for {$course->code} — {$course->title}."
                : "You joined {$course->code} — {$course->title}.",
            'data' => ['id' => $course->id, 'code' => $course->code, 'title' => $course->title, 'already_joined' => $alreadyYours],
        ]);
    }
}
