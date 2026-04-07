<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseFolder;
use App\Models\FileTag;
use App\Services\CourseFile\FolderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseFileController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(
        protected FolderService $folderService,
    ) {}

    /**
     * Course file manager — select a course first.
     */
    public function index(): View
    {
        $courses = Course::whereIn('id', $this->accessibleCourseIds())
            ->withCount('sections')
            ->get();

        return view('tenant.files.index', compact('courses'));
    }

    /**
     * File manager for a specific course.
     */
    public function manage(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourseAccess($course);

        // Auto-create default folders if none exist
        if ($course->folders()->count() === 0) {
            $this->folderService->createDefaultFolders($course);
        }

        $folderTree = $this->folderService->getFolderTree($course);
        $folders = CourseFolder::where('course_id', $course->id)->whereNull('parent_id')->orderBy('sort_order')->get();

        // Get selected folder
        $selectedFolderId = request('folder');
        $selectedFolder = $selectedFolderId ? CourseFolder::find($selectedFolderId) : $folders->first();

        $files = $selectedFolder
            ? CourseFile::where('course_folder_id', $selectedFolder->id)->with('tags')->latest()->get()
            : collect();

        // File stats
        $totalFiles = CourseFile::where('course_id', $course->id)->count();
        $totalSize = CourseFile::where('course_id', $course->id)->sum('file_size_bytes');

        return view('tenant.files.manage', compact('course', 'folderTree', 'folders', 'selectedFolder', 'files', 'totalFiles', 'totalSize'));
    }

    /**
     * Create a new folder.
     */
    public function createFolder(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:course_folders,id'],
        ]);

        $maxOrder = CourseFolder::where('course_id', $course->id)
            ->where('parent_id', $request->parent_id)
            ->max('sort_order') ?? -1;

        CourseFolder::create([
            'course_id' => $course->id,
            'parent_id' => $request->parent_id,
            'name' => $request->name,
            'sort_order' => $maxOrder + 1,
        ]);

        return back()->with('success', "Folder '{$request->name}' created.");
    }

    /**
     * Rename a folder.
     */
    public function renameFolder(Request $request, string $tenantSlug, Course $course, CourseFolder $folder): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255']]);
        $folder->update(['name' => $request->name]);

        return back()->with('success', 'Folder renamed.');
    }

    /**
     * Delete a folder.
     */
    public function deleteFolder(string $tenantSlug, Course $course, CourseFolder $folder): RedirectResponse
    {
        $fileCount = $folder->files()->count();
        $childCount = $folder->children()->count();

        if ($fileCount > 0 || $childCount > 0) {
            return back()->with('error', "Cannot delete folder with {$fileCount} files and {$childCount} subfolders. Remove contents first.");
        }

        $folder->delete();

        return redirect()->route('tenant.files.manage', [
            'tenant' => app('current_tenant')->slug,
            'course' => $course->id,
        ])->with('success', 'Folder deleted.');
    }

    /**
     * Upload files to a folder.
     */
    public function upload(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $request->validate([
            'folder_id' => ['required', 'exists:course_folders,id'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:25600'],
            'tags' => ['nullable', 'string'],
        ]);

        $folder = CourseFolder::findOrFail($request->folder_id);

        foreach ($request->file('files') as $file) {
            $path = $file->store("course-files/{$course->id}/{$folder->id}", 'local');

            $courseFile = CourseFile::create([
                'course_folder_id' => $folder->id,
                'course_id' => $course->id,
                'uploaded_by' => auth()->id(),
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size_bytes' => $file->getSize(),
                'storage_path' => $path,
            ]);

            // Add tags
            if ($request->tags) {
                $tagPairs = explode(',', $request->tags);
                foreach ($tagPairs as $pair) {
                    $pair = trim($pair);
                    if (str_contains($pair, ':')) {
                        [$type, $value] = explode(':', $pair, 2);
                        FileTag::create([
                            'course_file_id' => $courseFile->id,
                            'tag_type' => trim($type),
                            'tag_value' => trim($value),
                        ]);
                    }
                }
            }
        }

        $count = count($request->file('files'));

        return back()->with('success', "{$count} file(s) uploaded.");
    }

    /**
     * Delete a file.
     */
    public function deleteFile(string $tenantSlug, Course $course, CourseFile $file): RedirectResponse
    {
        $file->delete();
        return back()->with('success', 'File deleted.');
    }

    /**
     * Add tags to a file.
     */
    public function addTag(Request $request, string $tenantSlug, Course $course, CourseFile $file): RedirectResponse
    {
        $request->validate([
            'tag_type' => ['required', 'string', 'in:week,clo,assessment_type,topic,evidence_type'],
            'tag_value' => ['required', 'string', 'max:100'],
        ]);

        FileTag::firstOrCreate([
            'course_file_id' => $file->id,
            'tag_type' => $request->tag_type,
            'tag_value' => $request->tag_value,
        ]);

        return back()->with('success', 'Tag added.');
    }
}
