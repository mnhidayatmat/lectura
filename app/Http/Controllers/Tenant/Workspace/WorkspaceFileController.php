<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Workspace;

use App\Http\Controllers\Controller;
use App\Models\StudentGroup;
use App\Models\StudentGroupFile;
use App\Models\StudentGroupFolder;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorkspaceFileController extends Controller
{
    public function __construct(
        protected GoogleDriveService $drive,
    ) {}

    /**
     * Upload a file. If the uploader has Google Drive connected, store it there.
     * Falls back to local disk when Drive is not connected.
     */
    public function store(Request $request, string $tenantSlug, StudentGroup $group): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            abort(403);
        }

        // Verify folder belongs to this group
        $appFolder = null;
        if ($request->filled('folder_id')) {
            $appFolder = StudentGroupFolder::findOrFail($request->folder_id);
            if ($appFolder->student_group_id !== $group->id) {
                abort(403);
            }
        }

        $maxMb = config('lectura.uploads.max_file_size_mb', 25);
        $allowed = implode(',', config('lectura.uploads.allowed_types', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'pptx']));

        $request->validate([
            'file' => ['required', 'file', "max:{$maxMb}000", "mimes:{$allowed}"],
            'folder_id' => ['nullable', 'integer', 'exists:student_group_folders,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $uploadedFile = $request->file('file');

        $driveFileId = null;
        $driveWebLink = null;
        $storagePath = null;

        if ($user->isDriveConnected()) {
            // ── Google Drive upload ──
            try {
                [$driveFileId, $driveWebLink] = $this->uploadToDrive($user, $group, $appFolder, $uploadedFile);
            } catch (\Throwable $e) {
                return back()->with('error', 'Google Drive upload failed: ' . $e->getMessage() . '. Please check your Drive connection.');
            }
        } else {
            // ── Local disk fallback ──
            $storagePath = $uploadedFile->store("workspace/{$group->id}", 'local');
        }

        StudentGroupFile::create([
            'student_group_id' => $group->id,
            'folder_id' => $appFolder?->id,
            'uploaded_by' => $user->id,
            'file_name' => $uploadedFile->getClientOriginalName(),
            'file_type' => $uploadedFile->getClientOriginalExtension(),
            'file_size_bytes' => $uploadedFile->getSize(),
            'storage_path' => $storagePath,
            'drive_file_id' => $driveFileId,
            'drive_web_link' => $driveWebLink,
            'description' => $request->description,
        ]);

        return back()->with('success', 'File uploaded' . ($driveFileId ? ' to Google Drive' : '') . '.');
    }

    /**
     * Delete a file (uploader or group leader).
     * Removes from Drive or local disk depending on storage type.
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

        if ($file->isDriveFile()) {
            // Delete from the uploader's Drive account
            $uploader = $file->uploader;
            if ($uploader->isDriveConnected()) {
                $this->drive->deleteFile($uploader, $file->drive_file_id);
            }
        } elseif ($file->storage_path) {
            Storage::disk('local')->delete($file->storage_path);
        }

        $file->delete();

        return back()->with('success', 'File deleted.');
    }

    /**
     * Download / view a file.
     * Drive files redirect to the Drive web view link.
     * Local files are served as a download response.
     */
    public function download(string $tenantSlug, StudentGroup $group, StudentGroupFile $file): mixed
    {
        $user = auth()->user();

        if (! $group->isMember($user->id) || $file->student_group_id !== $group->id) {
            abort(403);
        }

        if ($file->isDriveFile()) {
            if (! $file->drive_web_link) {
                return back()->with('error', 'Drive link not available for this file.');
            }
            return redirect()->away($file->drive_web_link);
        }

        if (! $file->storage_path) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->download($file->storage_path, $file->file_name);
    }

    /**
     * Create a logical folder in the workspace.
     * If the creating user has Drive connected, also creates the matching Drive folder
     * inside the group's workspace folder so it's ready for uploads.
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

        $driveFolderId = null;

        if ($user->isDriveConnected()) {
            try {
                $groupDriveFolderId = $this->ensureGroupDriveFolder($user, $group);
                $driveFolderId = $this->drive->findOrCreateFolder($user, $request->name, $groupDriveFolderId);
            } catch (\Throwable) {
                // Drive folder creation is best-effort — don't block the app folder creation
            }
        }

        StudentGroupFolder::create([
            'student_group_id' => $group->id,
            'name' => $request->name,
            'drive_folder_id' => $driveFolderId,
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Folder created.');
    }

    /**
     * Delete a logical folder (only if it has no files).
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

    // ── Private helpers ──

    /**
     * Upload a file to the student's Google Drive.
     * Structure: Lectura/Workspace/{Group Name}/{Folder Name?}/file
     *
     * @return array{0: string, 1: string} [drive_file_id, drive_web_link]
     */
    private function uploadToDrive(
        \App\Models\User $user,
        StudentGroup $group,
        ?StudentGroupFolder $appFolder,
        \Illuminate\Http\UploadedFile $file,
    ): array {
        $groupFolderId = $this->ensureGroupDriveFolder($user, $group);

        // If the file belongs to an app folder, use (or create) the matching Drive folder
        $targetFolderId = $groupFolderId;

        if ($appFolder) {
            if ($appFolder->drive_folder_id) {
                $targetFolderId = $appFolder->drive_folder_id;
            } else {
                $targetFolderId = $this->drive->findOrCreateFolder($user, $appFolder->name, $groupFolderId);
                // Cache the Drive folder ID on the app folder for future uploads
                $appFolder->update(['drive_folder_id' => $targetFolderId]);
            }
        }

        $result = $this->drive->uploadFile(
            $user,
            $file->getRealPath(),
            $file->getClientOriginalName(),
            $file->getMimeType() ?? 'application/octet-stream',
            $targetFolderId,
        );

        return [$result['id'], $result['web_view_link']];
    }

    /**
     * Ensure Lectura/Workspace/{Group Name} exists in the student's Drive.
     * Returns the group folder ID.
     */
    private function ensureGroupDriveFolder(\App\Models\User $user, StudentGroup $group): string
    {
        $rootId = $this->drive->ensureRootFolder($user);
        $workspaceFolderId = $this->drive->findOrCreateFolder($user, 'Workspace', $rootId);

        return $this->drive->findOrCreateFolder($user, $group->name, $workspaceFolderId);
    }
}
