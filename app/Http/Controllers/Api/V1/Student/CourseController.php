<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\StudentPresenter;
use App\Models\ActiveLearningPlan;
use App\Models\ActiveLearningSession;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseLearningOutcome;
use App\Models\CourseTopic;
use App\Models\Section;
use App\Models\SectionStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $enrollments = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('section')
            ->with('section.course.lecturer')
            ->get()
            ->filter(fn (SectionStudent $enrollment) => $enrollment->section?->course !== null);

        $courses = $enrollments->groupBy(fn (SectionStudent $e) => $e->section->course_id)
            ->map(function ($group) {
                $first = $group->first();
                $course = $first->section->course;

                return [
                    ...StudentPresenter::course($course),
                    'status' => $course->status,
                    'credit_hours' => $course->credit_hours,
                    'lecturer_name' => $course->lecturer?->name,
                    'sections' => $group->map(fn (SectionStudent $e) => [
                        'id' => $e->section->id,
                        'name' => $e->section->name,
                        'code' => $e->section->code,
                    ])->values(),
                    'enrolled_at' => $first->enrolled_at?->toIso8601String(),
                ];
            })
            ->sortBy('code')
            ->values();

        return response()->json(['data' => $courses]);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        $mySections = Section::where('course_id', $course->id)
            ->whereHas('sectionStudents', fn ($q) => $q->where('user_id', $user->id)->where('is_active', true))
            ->with('lecturers')
            ->get();

        if ($mySections->isEmpty()) {
            abort(403, 'You are not enrolled in this course.');
        }

        $course->load(['lecturer', 'topics', 'learningOutcomes']);

        $records = AttendanceRecord::where('user_id', $user->id)
            ->whereHas('session', fn ($q) => $q->whereIn('section_id', $mySections->pluck('id')))
            ->get();

        $present = $records->where('status', 'present')->count();
        $late = $records->where('status', 'late')->count();
        $total = $records->count();

        $upcomingAssignments = Assignment::where('course_id', $course->id)
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', now()))
            ->orderBy('deadline')
            ->limit(5)
            ->get();

        $plans = ActiveLearningPlan::where('course_id', $course->id)
            ->where('status', 'published')
            ->withCount('activities')
            ->with(['sessions' => fn ($q) => $q->where('status', ActiveLearningSession::STATUS_ACTIVE)])
            ->orderByDesc('week_number')
            ->get();

        $materialsCount = CourseFile::where('course_id', $course->id)
            ->whereHas('materialSection', fn ($q) => $q->where('is_visible', true))
            ->count();

        return response()->json([
            'data' => [
                'course' => [
                    ...StudentPresenter::course($course),
                    'description' => $course->description,
                    'credit_hours' => $course->credit_hours,
                    'num_weeks' => $course->num_weeks,
                    'teaching_mode' => $course->teaching_mode,
                    'status' => $course->status,
                    'lecturer' => $course->lecturer ? [
                        'id' => $course->lecturer->id,
                        'name' => $course->lecturer->name,
                        'avatar_url' => $course->lecturer->avatar_url,
                    ] : null,
                ],
                'my_sections' => $mySections->map(fn (Section $section) => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'code' => $section->code,
                    'schedule' => StudentPresenter::schedule($section->schedule),
                    'lecturers' => $section->lecturers->map(fn ($lecturer) => [
                        'id' => $lecturer->id,
                        'name' => $lecturer->name,
                    ])->values(),
                ])->values(),
                'attendance_summary' => [
                    'present' => $present,
                    'late' => $late,
                    'absent' => $records->where('status', 'absent')->count(),
                    'total' => $total,
                    'rate' => $total > 0 ? (int) round(($present + $late) / $total * 100) : 0,
                ],
                'upcoming_assignments' => $upcomingAssignments->map(fn (Assignment $assignment) => [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'type' => $assignment->type,
                    'total_marks' => (float) $assignment->total_marks,
                    'deadline' => $assignment->deadline?->toIso8601String(),
                ])->values(),
                'topics' => $course->topics->sortBy('week_number')->map(fn (CourseTopic $topic) => [
                    'week_number' => $topic->week_number,
                    'title' => $topic->title,
                ])->values(),
                'learning_outcomes' => $course->learningOutcomes->map(fn (CourseLearningOutcome $clo) => [
                    'code' => $clo->code,
                    'description' => $clo->description,
                ])->values(),
                'active_learning_plans' => $plans->map(fn (ActiveLearningPlan $plan) => [
                    'id' => $plan->id,
                    'title' => $plan->title,
                    'week_number' => $plan->week_number,
                    'duration_minutes' => $plan->duration_minutes,
                    'activities_count' => (int) $plan->activities_count,
                    'active_session_id' => $plan->sessions->first()?->id,
                ])->values(),
                'counts' => [
                    'materials' => $materialsCount,
                    'upcoming_assignments' => $upcomingAssignments->count(),
                    'active_learning_plans' => $plans->count(),
                ],
            ],
        ]);
    }

    public function enroll(Request $request): JsonResponse
    {
        $request->validate([
            'invite_code' => ['required', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $code = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $request->invite_code));

        $section = Section::where('invite_code', $code)->first();

        if (! $section) {
            $isCourseCode = $code !== '' && Course::where('invite_code', $code)->exists();

            throw ValidationException::withMessages([
                'invite_code' => $isCourseCode
                    ? 'That code belongs to a course, not a section. Please ask your lecturer for the section invite code (shown as "Student code" next to each section).'
                    : 'Invalid invite code. Please check with your lecturer.',
            ]);
        }

        if (! $section->is_active) {
            throw ValidationException::withMessages([
                'invite_code' => 'This section is not accepting enrollments. Please contact your lecturer.',
            ]);
        }

        $existing = SectionStudent::where('section_id', $section->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->is_active) {
                return $this->enrolled('You are already enrolled in '.$section->course->code.' — '.$section->name.'.', $section, true);
            }

            $existing->update(['is_active' => true, 'enrolled_at' => now()]);
        } else {
            if ($section->capacity) {
                $currentCount = SectionStudent::where('section_id', $section->id)->where('is_active', true)->count();

                if ($currentCount >= $section->capacity) {
                    throw ValidationException::withMessages([
                        'invite_code' => 'This section is full. Please contact your lecturer.',
                    ]);
                }
            }

            SectionStudent::create([
                'section_id' => $section->id,
                'user_id' => $user->id,
                'enrolled_at' => now(),
                'enrollment_method' => 'invite_code',
                'is_active' => true,
            ]);
        }

        return $this->enrolled('Successfully enrolled in '.$section->course->code.' — '.$section->name.'!', $section, false);
    }

    private function enrolled(string $message, Section $section, bool $alreadyEnrolled): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => [
                'course' => StudentPresenter::course($section->course),
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'code' => $section->code,
                ],
                'already_enrolled' => $alreadyEnrolled,
            ],
        ]);
    }
}
