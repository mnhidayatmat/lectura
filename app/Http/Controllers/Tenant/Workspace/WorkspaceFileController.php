<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Workspace;

use App\Http\Controllers\Controller;
use App\Models\StudentGroup;
use App\Models\StudentGroupFile;
use App\Models\StudentGroupFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorkspaceFileController extends Controller
{
    /**
     * Upload a file to the workspace (optionally into a folder).
     */
    public function store(Request $request, string $tenantSlug, StudentGroup $group): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            abort(403);
        }

        $maxMb = config('lectura.uploads.max_file_size_mb', 25);
        $allowed = implode(',', config('lectura.uploads.allowed_types', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'pptx']));

        $request->validate([
            'file' => ['required', 'file', "max:{$maxMb}000", "mimes:{$allowed}"],
            'folder_id' => ['nullable', 'integer', 'exists:student_group_folders,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // Verify folder belongs to this group
        if ($request->filled('folder_id')) {
            $folder = StudentGroupFolder::findOrFail($request->folder_id);
            if ($folder->student_group_id !== $group->id) {
                abort(403);
            }
        }

        $file = $request->file('file');
        $path = $file->store("workspace/{$group->id}", 'local');

        StudentGroupFile::create([
            'student_group_id' => $group->id,
            'folder_id' => $request->folder_id,
            'uploaded_by' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientOriginalExtension(),
            'file_size_bytes' => $file->getSize(),
            'storage_path' => $path,
            'description' => $request->description,
        ]);

        return back()->with('success', 'File uploaded.');
    }

    /**
     * Delete a file (uploader or group leader).
     */
    public function destroy(string $tenantSlug, StudentGroup $group, StudentGroupFile $file): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id) || $file->student_group_id !== $group->id) {
            abort(403);
        }

        $isLeader = $group->members()->where('user_id', $user->id)->where('role', 'leader')->exists();

        if ($file->uploaded_by !== $user->id && ! $isLeader) {
            return back()->with('error', 'Only the uploader or group leader can delete files.');
        }

        Storage::disk('local')->delete($file->storage_path);
        $file->delete();

        return back()->with('success', 'File deleted.');
    }

    /**
     * Download a workspace file.
     */
    public function download(string $tenantSlug, StudentGroup $group, StudentGroupFile $file): mixed
    {
        $user = auth()->user();

        if (! $group->isMember($user->id) || $file->student_group_id !== $group->id) {
            abort(403);
        }

        return Storage::disk('local')->download($file->storage_path, $file->file_name);
    }

    /**
     * Create a folder in the workspace.
     */
    public function storeFolder(Request $request, string $tenantSlug, StudentGroup $group): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        StudentGroupFolder::create([
            'student_group_id' => $group->id,
            'name' => $request->name,
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Folder created.');
    }

    /**
     * Delete a folder (only if empty).
     */
    public function destroyFolder(string $tenantSlug, StudentGroup $group, StudentGroupFolder $folder): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id) || $folder->student_group_id !== $group->id) {
            abort(403);
        }

        if ($folder->files()->exists()) {
            return back()->with('error', 'Delete all files in this folder first.');
        }

        $folder->delete();

        return back()->with('success', 'Folder deleted.');
    }
}
