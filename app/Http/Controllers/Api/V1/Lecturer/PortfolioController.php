<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\PortfolioPhoto;
use App\Models\Section;
use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The lecturer's teaching portfolio: class photos per course, laid out week by week, with a
 * generated PDF. Each lecturer only ever sees and changes their own photos, as on the web.
 */
class PortfolioController extends Controller
{
    use AuthorizesLecturerAccess;

    public function __construct(private readonly PortfolioService $portfolio) {}

    /**
     * Every course I teach with my photo count and latest photo.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureLecturer();

        $courseIds = $this->accessibleCourseIds();
        $courses = Course::whereIn('id', $courseIds)->latest()->get(['id', 'code', 'title']);
        $photos = PortfolioPhoto::where('user_id', $request->user()->id)
            ->whereIn('course_id', $courseIds)
            ->latest('taken_at')
            ->get(['id', 'course_id', 'file_path', 'thumbnail_path', 'week_number', 'taken_at'])
            ->groupBy('course_id');

        return response()->json([
            'data' => $courses->map(function (Course $course) use ($photos) {
                $mine = $photos->get($course->id, collect());

                return [
                    'course' => ['id' => $course->id, 'code' => $course->code, 'title' => $course->title],
                    'photo_count' => $mine->count(),
                    'week_count' => $mine->pluck('week_number')->filter()->unique()->count(),
                    'cover_url' => $mine->first()?->thumbnail_url,
                    'last_taken_at' => $mine->first()?->taken_at?->toIso8601String(),
                ];
            })->sortByDesc('photo_count')->values(),
        ]);
    }

    /**
     * One course: the photos as a week-by-week timeline, plus what the add form needs.
     */
    public function show(Request $request, Course $course): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        $photos = $this->portfolio->photosFor($course, $request->user());

        return response()->json([
            'data' => [
                'course' => ['id' => $course->id, 'code' => $course->code, 'title' => $course->title],
                'categories' => collect(PortfolioPhoto::CATEGORIES)
                    ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                    ->values(),
                'sections' => $this->lecturerSections($course)->get(['id', 'name', 'code'])
                    ->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->name])
                    ->values(),
                'current_week' => $this->portfolio->weekFor(null, now(), $course->loadMissing('academicTerm')),
                'photo_count' => $photos->count(),
                'timeline' => $this->portfolio->timeline($photos)->map(fn (array $group) => [
                    'week' => $group['week'],
                    'label' => $group['label'],
                    'from' => $group['from']?->toIso8601String(),
                    'to' => $group['to']?->toIso8601String(),
                    'photos' => $group['photos']->map(fn (PortfolioPhoto $p) => $this->photo($p))->values(),
                ]),
            ],
        ]);
    }

    /**
     * Adds one photo. With `attendance_session_id` (a session of this course I may open), the
     * section, week, category and time come from that class session unless sent.
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'category' => ['nullable', 'string', Rule::in(array_keys(PortfolioPhoto::CATEGORIES))],
            'caption' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'section_id' => ['nullable', 'integer'],
            'attendance_session_id' => ['nullable', 'integer'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:30'],
            'taken_at' => ['nullable', 'date'],
        ]);
        unset($validated['photo']);

        if (! empty($validated['attendance_session_id'])) {
            $session = AttendanceSession::with('section')->find($validated['attendance_session_id']);
            if (! $session || $session->section?->course_id !== $course->id) {
                throw ValidationException::withMessages(['attendance_session_id' => 'That class session is not part of this course.']);
            }
            $this->authorizeSession($session);
            $validated = $this->portfolio->fromSession($session, array_filter($validated, fn ($v) => $v !== null));
        } elseif (! empty($validated['section_id'])) {
            $this->authorizeSection($course, Section::findOrFail($validated['section_id']));
        }

        $photo = $this->portfolio->store($request->file('photo'), $course, $request->user(), $validated);

        return response()->json([
            'message' => 'Photo added to your portfolio.',
            'data' => $this->photo($photo->load('section', 'attendanceSession')),
        ], 201);
    }

    /**
     * Caption, description, category or week.
     */
    public function update(Request $request, PortfolioPhoto $photo): JsonResponse
    {
        $this->authorizePhoto($request, $photo);

        $validated = $request->validate([
            'category' => ['sometimes', 'required', 'string', Rule::in(array_keys(PortfolioPhoto::CATEGORIES))],
            'caption' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'week_number' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $photo->update($validated);

        return response()->json([
            'message' => 'Photo updated.',
            'data' => $this->photo($photo->load('section', 'attendanceSession')),
        ]);
    }

    public function destroy(Request $request, PortfolioPhoto $photo): JsonResponse
    {
        $this->authorizePhoto($request, $photo);

        $this->portfolio->delete($photo);

        return response()->json(['message' => 'Photo removed from your portfolio.', 'data' => ['id' => $photo->id]]);
    }

    /**
     * The generated portfolio PDF, streamed with the bearer token like other downloads.
     */
    public function pdf(Request $request, Course $course): Response
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        return $this->portfolio->pdf($course, $request->user())->download($this->portfolio->pdfFilename($course));
    }

    private function authorizePhoto(Request $request, PortfolioPhoto $photo): void
    {
        $this->ensureLecturer();

        if ($photo->user_id !== $request->user()->id) {
            abort(403, 'You can only change your own portfolio photos.');
        }
    }

    private function photo(PortfolioPhoto $photo): array
    {
        $session = $photo->attendanceSession;

        return [
            'id' => $photo->id,
            'course_id' => $photo->course_id,
            'category' => $photo->category,
            'category_label' => $photo->category_label,
            'caption' => $photo->caption,
            'description' => $photo->description,
            'week_number' => $photo->week_number,
            'taken_at' => $photo->taken_at?->toIso8601String(),
            'image_url' => $photo->public_url,
            'thumbnail_url' => $photo->thumbnail_url,
            'file_size_bytes' => $photo->file_size_bytes,
            'section' => $photo->section ? ['id' => $photo->section->id, 'name' => $photo->section->name] : null,
            'attendance_session' => $session ? [
                'id' => $session->id,
                'session_type' => $session->session_type,
                'week_number' => $session->week_number,
                'started_at' => $session->started_at?->toIso8601String(),
            ] : null,
        ];
    }
}
