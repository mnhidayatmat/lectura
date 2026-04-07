<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseFolder;
use App\Models\CourseMaterialSection;
use App\Models\SectionStudent;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseMaterialController extends Controller
{
    use AuthorizesCourseAccess;

    // ── Lecturer ──

    public function index(): View
    {
        $tenant = app('current_tenant');
        $courses = Course::whereIn('id', $this->accessibleCourseIds())
            ->withCount('files')
            ->with('sections')
            ->get();

        return view('tenant.materials.index', compact('tenant', 'courses'));
    }

    public function manage(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourseAccess($course);

        $tenant = app('current_tenant');

        $sections = $course->materialSections()
            ->with(['files' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at')])
            ->get();

        return view('tenant.materials.manage', compact('tenant', 'course', 'sections'));
    }

    public function storeSection(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $maxOrder = $course->materialSections()->max('sort_order') ?? -1;

        CourseMaterialSection::create([
            'course_id' => $course->id,
            'title'     => $request->input('title'),
            'sort_order' => $maxOrder + 1,
            'is_visible' => true,
        ]);

        return back()->with('success', 'Section created.');
    }

    public function updateSection(Request $request, string $tenantSlug, Course $course, CourseMaterialSection $section): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($section->course_id !== $course->id) {
            abort(403);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $section->update(['title' => $request->input('title')]);

        return back()->with('success', 'Section renamed.');
    }

    public function destroySection(string $tenantSlug, Course $course, CourseMaterialSection $section): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($section->course_id !== $course->id) {
            abort(403);
        }

        foreach ($section->files as $file) {
            $this->deleteFileStorage($file);
            $file->forceDelete();
        }

        $section->delete();

        return back()->with('success', 'Section deleted.');
    }

    public function moveSection(Request $request, string $tenantSlug, Course $course, CourseMaterialSection $section): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($section->course_id !== $course->id) {
            abort(403);
        }

        $direction = $request->input('direction');
        $ids = $course->materialSections()->pluck('id')->toArray();
        $pos = array_search($section->id, $ids, true);

        if ($direction === 'up' && $pos > 0) {
            [$ids[$pos - 1], $ids[$pos]] = [$ids[$pos], $ids[$pos - 1]];
        } elseif ($direction === 'down' && $pos < count($ids) - 1) {
            [$ids[$pos], $ids[$pos + 1]] = [$ids[$pos + 1], $ids[$pos]];
        } else {
            return back();
        }

        foreach ($ids as $order => $id) {
            CourseMaterialSection::where('id', $id)->update(['sort_order' => $order]);
        }

        return back();
    }

    public function upload(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $request->validate([
            'material_section_id' => ['required', 'integer', 'exists:course_material_sections,id'],
            'files'               => ['required', 'array', 'min:1'],
            'files.*'             => ['file', 'max:25600'],
            'display_name'        => ['nullable', 'string', 'max:255'],
            'description'         => ['nullable', 'string', 'max:500'],
        ]);

        $sectionId = (int) $request->input('material_section_id');
        $user      = auth()->user();

        if ($user->isDriveConnected()) {
            return $this->uploadToDrive($request, $course, $sectionId, $user);
        }

        return $this->uploadToLocal($request, $course, $sectionId);
    }

    private function uploadToDrive(Request $request, Course $course, int $sectionId, $user): RedirectResponse
    {
        $section     = CourseMaterialSection::findOrFail($sectionId);
        $driveService = app(GoogleDriveService::class);

        try {
            $courseFolderId  = $driveService->findOrCreateFolder($user, "{$course->code} — {$course->title}");
            $sectionFolderId = $driveService->findOrCreateFolder($user, $section->title, $courseFolderId);
        } catch (\Throwable $e) {
            return back()->withErrors(['files' => 'Could not create folder on Google Drive: ' . $e->getMessage()]);
        }

        $uploaded = 0;
        $files = $request->file('files');
        $isSingle = count($files) === 1;
        $displayName = $request->input('display_name');

        foreach ($files as $file) {
            try {
                $result = $driveService->uploadFile(
                    $user,
                    $file->getPathname(),
                    $file->getClientOriginalName(),
                    $file->getMimeType() ?? 'application/octet-stream',
                    $sectionFolderId
                );

                CourseFile::create([
                    'course_id'           => $course->id,
                    'uploaded_by'         => auth()->id(),
                    'material_type'       => 'drive',
                    'file_name'           => ($isSingle && $displayName) ? $displayName : $file->getClientOriginalName(),
                    'file_type'           => $file->getMimeType(),
                    'file_size_bytes'     => $file->getSize(),
                    'url'                 => $result['web_view_link'],
                    'drive_file_id'       => $result['id'],
                    'description'         => $request->input('description'),
                    'material_section_id' => $sectionId,
                    'sort_order'          => CourseFile::where('material_section_id', $sectionId)->count(),
                ]);

                $uploaded++;
            } catch (\Throwable $e) {
                return back()->withErrors(['files' => 'Google Drive upload failed: ' . $e->getMessage()]);
            }
        }

        return back()->with('success', $uploaded . ' ' . Str::plural('file', $uploaded) . ' uploaded to Google Drive.');
    }

    private function uploadToLocal(Request $request, Course $course, int $sectionId): RedirectResponse
    {
        $folder = CourseFolder::firstOrCreate(
            ['course_id' => $course->id, 'name' => 'Weekly Materials', 'parent_id' => null],
            ['sort_order' => 2]
        );

        $files = $request->file('files');
        $isSingle = count($files) === 1;
        $displayName = $request->input('display_name');

        foreach ($files as $file) {
            $path = $file->store("course-files/{$course->id}/{$folder->id}", 'local');

            CourseFile::create([
                'course_folder_id'    => $folder->id,
                'course_id'           => $course->id,
                'uploaded_by'         => auth()->id(),
                'material_type'       => 'file',
                'file_name'           => ($isSingle && $displayName) ? $displayName : $file->getClientOriginalName(),
                'file_type'           => $file->getMimeType(),
                'file_size_bytes'     => $file->getSize(),
                'storage_path'        => $path,
                'description'         => $request->input('description'),
                'material_section_id' => $sectionId,
                'sort_order'          => CourseFile::where('material_section_id', $sectionId)->count(),
            ]);
        }

        return back()->with('success', 'Files uploaded successfully.');
    }

    public function storeLink(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $request->validate([
            'material_section_id' => ['required', 'integer', 'exists:course_material_sections,id'],
            'title'               => ['required', 'string', 'max:255'],
            'url'                 => ['required', 'url', 'max:2048'],
            'description'         => ['nullable', 'string', 'max:500'],
        ]);

        $sectionId = (int) $request->input('material_section_id');

        CourseFile::create([
            'course_id'           => $course->id,
            'uploaded_by'         => auth()->id(),
            'material_type'       => 'link',
            'file_name'           => $request->input('title'),
            'url'                 => $request->input('url'),
            'description'         => $request->input('description'),
            'material_section_id' => $sectionId,
            'sort_order'          => CourseFile::where('material_section_id', $sectionId)->count(),
        ]);

        return back()->with('success', 'Link added successfully.');
    }

    public function updateMaterial(Request $request, string $tenantSlug, Course $course, CourseFile $file): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        if ($file->isLink()) {
            $request->validate([
                'title'       => ['required', 'string', 'max:255'],
                'url'         => ['required', 'url', 'max:2048'],
                'description' => ['nullable', 'string', 'max:500'],
            ]);

            $file->update([
                'file_name'   => $request->input('title'),
                'url'         => $request->input('url'),
                'description' => $request->input('description'),
            ]);
        } else {
            $request->validate([
                'title'       => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:500'],
            ]);

            $file->update([
                'file_name'   => $request->input('title'),
                'description' => $request->input('description'),
            ]);
        }

        return back()->with('success', 'Material updated.');
    }

    public function destroy(string $tenantSlug, Course $course, CourseFile $file): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $this->deleteFileStorage($file);
        $file->delete();

        return back()->with('success', 'Material removed.');
    }

    public function download(string $tenantSlug, Course $course, CourseFile $file): mixed
    {
        $isLecturer = $this->isCourseOwner($course) || \App\Models\Section::where('course_id', $course->id)->whereHas('lecturers', fn ($q) => $q->where('user_id', auth()->id()))->exists();
        $isStudent  = ! $isLecturer && SectionStudent::whereIn('section_id', $course->sections()->pluck('id'))
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->exists();

        if (! $isLecturer && ! $isStudent) {
            abort(403);
        }

        if ($file->isDriveFile() && $file->url) {
            return redirect($file->url);
        }

        if ($file->isLink() || ! $file->storage_path) {
            abort(404);
        }

        return Storage::disk('local')->download($file->storage_path, $file->file_name);
    }

    private function deleteFileStorage(CourseFile $file): void
    {
        if ($file->isDriveFile() && $file->drive_file_id) {
            try {
                $uploader = \App\Models\User::find($file->uploaded_by);
                if ($uploader?->isDriveConnected()) {
                    app(GoogleDriveService::class)->deleteFile($uploader, $file->drive_file_id);
                }
            } catch (\Throwable) {
                // Drive delete failure should not block the record delete
            }
        } elseif ($file->storage_path) {
            Storage::disk('local')->delete($file->storage_path);
        }
    }

    // ── Student ──

    public function studentIndex(): View
    {
        $tenant = app('current_tenant');
        $user   = auth()->user();

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
        $user   = auth()->user();

        $isEnrolled = SectionStudent::whereIn('section_id', $course->sections()->pluck('id'))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $isEnrolled) {
            abort(403);
        }

        $sections = $course->materialSections()
            ->where('is_visible', true)
            ->with(['files' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at')])
            ->get()
            ->filter(fn ($s) => $s->files->isNotEmpty());

        return view('tenant.materials.student-course', compact('tenant', 'course', 'sections'));
    }
}
