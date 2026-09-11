<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\Student\Concerns\InteractsWithEnrollments;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\MaterialItemResource;
use App\Http\Resources\Api\V1\Student\StudentPresenter;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseMaterialSection;
use App\Models\Section;
use App\Models\SectionStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    use InteractsWithEnrollments;

    public function index(Request $request): JsonResponse
    {
        $sectionIds = $this->enrolledSectionIds($request->user());

        $courses = Course::whereHas('sections', fn ($q) => $q->whereIn('id', $sectionIds))
            ->withCount('files')
            ->with('lecturer')
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $courses->map(fn (Course $course) => [
                ...StudentPresenter::course($course),
                'lecturer_name' => $course->lecturer?->name,
                'materials_count' => (int) $course->files_count,
            ])->values(),
        ]);
    }

    public function course(Request $request, Course $course): JsonResponse
    {
        $this->ensureEnrolled($course, $request->user());

        $course->loadMissing('lecturer');

        $sections = $course->materialSections()
            ->where('is_visible', true)
            ->with(['files' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at')])
            ->get()
            ->filter(fn (CourseMaterialSection $section) => $section->files->isNotEmpty())
            ->values();

        return response()->json([
            'data' => [
                'course' => [
                    ...StudentPresenter::course($course),
                    'lecturer_name' => $course->lecturer?->name,
                ],
                'sections' => $sections->map(fn (CourseMaterialSection $section) => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'items_count' => $section->files->count(),
                    'items' => MaterialItemResource::collection($section->files),
                ])->values(),
            ],
        ]);
    }

    public function download(Request $request, Course $course, CourseFile $file): mixed
    {
        if ((int) $file->course_id !== $course->id) {
            abort(404);
        }

        $user = $request->user();
        $tenant = app('current_tenant');

        $isLecturer = $course->lecturer_id === $user->id
            || $user->hasRoleInTenant($tenant->id, ['admin'])
            || Section::where('course_id', $course->id)
                ->whereHas('lecturers', fn ($q) => $q->where('user_id', $user->id))
                ->exists();

        $isStudent = ! $isLecturer && SectionStudent::whereIn('section_id', $course->sections()->pluck('id'))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $isLecturer && ! $isStudent) {
            abort(403, 'You do not have access to this file.');
        }

        if ($file->isDriveFile() && $file->url) {
            return redirect()->away($file->url);
        }

        if ($file->isLink() || ! $file->storage_path || ! Storage::disk('local')->exists($file->storage_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->download($file->storage_path, $file->file_name);
    }
}
