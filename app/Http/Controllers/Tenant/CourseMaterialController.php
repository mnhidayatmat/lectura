<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseFolder;
use App\Models\SectionStudent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseMaterialController extends Controller
{
    // ── Lecturer ──

    public function index(): View
    {
        $tenant = app('current_tenant');
        $courses = Course::where('lecturer_id', auth()->id())
            ->withCount('files')
            ->with('sections')
            ->get();

        return view('tenant.materials.index', compact('tenant', 'courses'));
    }

    public function manage(string $tenantSlug, Course $course): View
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $topics = $course->topics()->get()->keyBy('week_number');

        $materials = $course->files()
            ->orderBy('week_number')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->groupBy('week_number');

        return view('tenant.materials.manage', compact('tenant', 'course', 'topics', 'materials'));
    }

    public function upload(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'week_number' => ['required', 'integer', 'min:1', 'max:52'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:25600'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $weekNumber = (int) $request->input('week_number');

        // Find or create "Weekly Materials" folder
        $folder = CourseFolder::firstOrCreate(
            ['course_id' => $course->id, 'name' => 'Weekly Materials', 'parent_id' => null],
            ['sort_order' => 2]
        );

        foreach ($request->file('files') as $file) {
            $path = $file->store("course-files/{$course->id}/{$folder->id}", 'local');

            CourseFile::create([
                'course_folder_id' => $folder->id,
                'course_id' => $course->id,
                'uploaded_by' => auth()->id(),
                'material_type' => 'file',
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getMimeType(),
                'file_size_bytes' => $file->getSize(),
                'storage_path' => $path,
                'description' => $request->input('description'),
                'week_number' => $weekNumber,
                'sort_order' => CourseFile::where('course_id', $course->id)->where('week_number', $weekNumber)->count(),
            ]);
        }

        return back()->with('success', 'Files uploaded successfully.');
    }

    public function storeLink(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'week_number' => ['required', 'integer', 'min:1', 'max:52'],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $weekNumber = (int) $request->input('week_number');

        CourseFile::create([
            'course_id' => $course->id,
            'uploaded_by' => auth()->id(),
            'material_type' => 'link',
            'file_name' => $request->input('title'),
            'url' => $request->input('url'),
            'description' => $request->input('description'),
            'week_number' => $weekNumber,
            'sort_order' => CourseFile::where('course_id', $course->id)->where('week_number', $weekNumber)->count(),
        ]);

        return back()->with('success', 'Link added successfully.');
    }

    public function destroy(string $tenantSlug, Course $course, CourseFile $file): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $file->delete();

        return back()->with('success', 'Material removed.');
    }

    public function download(string $tenantSlug, Course $course, CourseFile $file): StreamedResponse
    {
        // Allow lecturer or enrolled student
        $isLecturer = $course->lecturer_id === auth()->id();
        $isStudent = ! $isLecturer && SectionStudent::whereIn('section_id', $course->sections()->pluck('id'))
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->exists();

        if (! $isLecturer && ! $isStudent) {
            abort(403);
        }

        if ($file->isLink() || ! $file->storage_path) {
            abort(404);
        }

        return Storage::disk('local')->download($file->storage_path, $file->file_name);
    }

    // ── Student ──

    public function studentIndex(): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        $sectionIds = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('section_id');

        $courses = Course::whereHas('sections', fn ($q) => $q->whereIn('id', $sectionIds))
            ->withCount('files')
            ->with('lecturer')
            ->get();

        return view('tenant.materials.student-index', compact('tenant', 'courses'));
    }

    public function studentCourse(string $tenantSlug, Course $course): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        // Verify enrollment
        $isEnrolled = SectionStudent::whereIn('section_id', $course->sections()->pluck('id'))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $isEnrolled) {
            abort(403);
        }

        $topics = $course->topics()->get()->keyBy('week_number');

        $materials = $course->files()
            ->orderBy('week_number')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->groupBy('week_number');

        return view('tenant.materials.student-course', compact('tenant', 'course', 'topics', 'materials'));
    }
}
