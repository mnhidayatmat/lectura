<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Workspace\GroupFileResource;
use App\Http\Resources\Api\V1\Workspace\GroupFolderResource;
use App\Models\StudentGroup;
use App\Models\StudentGroupFile;
use App\Models\StudentGroupFolder;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceFileController extends Controller
{
    use AuthorizesGroupMembership;

    /**
     * Root listing (folders + unfiled files) or, with `folder_id`, one folder's files.
     */
    public function index(Request $request, StudentGroup $group): JsonResponse
    {
        $this->authorizeMember($group, $request->user());

        $request->validate(['folder_id' => ['nullable', 'integer']]);

        $group->load('members');

        $folder = null;
        if ($request->filled('folder_id')) {
            $folder = StudentGroupFolder::where('student_group_id', $group->id)
                ->withCount('files')
                ->with('creator:id,name')
                ->find($request->integer('folder_id'));

            if (! $folder) {
                abort(404, 'Folder not found.');
            }
        }

        $files = StudentGroupFile::where('student_group_id', $group->id)
            ->where('folder_id', $folder?->id)
            ->with('uploader:id,name')
            ->latest()
            ->get()
            ->each(fn (StudentGroupFile $file) => $file->setRelation('group', $group));

        $folders = $folder
            ? collect()
            : StudentGroupFolder::where('student_group_id', $group->id)
                ->withCount('files')
                ->with('creator:id,name')
                ->orderBy('name')
                ->get();

        return response()->json([
            'data' => [
                'folder' => $folder ? new GroupFolderResource($folder) : null,
                'folders' => GroupFolderResource::collection($folders),
                'files' => GroupFileResource::collection($files),
            ],
        ]);
    }

    /**
     * Upload a file. Stored in the uploader's Google Drive when connected, otherwise on local disk.
     */
    public function store(Request $request, StudentGroup $group): JsonResponse
    {
        $user = $request->user();
        $this->authorizeMember($group, $user);

        $appFolder = null;
        if ($request->filled('folder_id')) {
            $appFolder = StudentGroupFolder::find($request->folder_id);
            if (! $appFolder) {
                abort(404, 'Folder not found.');
            }
            if ((int) $appFolder->student_group_id !== $group->id) {
                abort(403, 'This folder belongs to another group.');
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
            try {
                [$driveFileId, $driveWebLink] = $this->uploadToDrive($user, $group, $appFolder, $uploadedFile);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'file' => 'Google Drive upload failed: '.$e->getMessage().'. Please check your Drive connection.',
                ]);
            }
        } else {
            $storagePath = $uploadedFile->store("workspace/{$group->id}", 'uploads');
        }

        $file = StudentGroupFile::create([
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

        $group->load('members');
        $file->load('uploader:id,name')->setRelation('group', $group);

        return response()->json([
            'message' => 'File uploaded'.($driveFileId ? ' to Google Drive' : '').'.',
            'data' => new GroupFileResource($file),
        ], 201);
    }

    /**
     * Delete a file (uploader or group leader), from Drive or local disk.
     */
    public function destroy(Request $request, StudentGroup $group, StudentGroupFile $file): JsonResponse
    {
        $user = $request->user();
        $this->authorizeFileAccess($group, $user, $file);

        if ((int) $file->uploaded_by !== (int) $user->id && ! $this->isLeader($group, $user)) {
            abort(403, 'Only the uploader or group leader can delete files.');
        }

        if ($file->isDriveFile()) {
            $uploader = $file->uploader;
            if ($uploader?->isDriveConnected()) {
                app(GoogleDriveService::class)->deleteFile($uploader, $file->drive_file_id);
            }
        } elseif ($file->storage_path) {
            Storage::disk('uploads')->delete($file->storage_path);
        }

        $file->delete();

        return response()->json([
            'message' => 'File deleted.',
            'data' => ['id' => $file->id],
        ]);
    }

    /**
     * Local files stream as a download; Drive files return their web link as `external_url`.
     */
    public function download(Request $request, StudentGroup $group, StudentGroupFile $file): JsonResponse|StreamedResponse
    {
        $this->authorizeFileAccess($group, $request->user(), $file);

        if ($file->isDriveFile()) {
            if (! $file->drive_web_link) {
                abort(404, 'Drive link not available for this file.');
            }

            return response()->json(['data' => ['external_url' => $file->drive_web_link]]);
        }

        if (! $file->storage_path || ! Storage::disk('uploads')->exists($file->storage_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('uploads')->download($file->storage_path, $file->file_name);
    }

    /**
     * Create a logical folder; mirrored in the creator's Drive when connected (best-effort).
     */
    public function storeFolder(Request $request, StudentGroup $group): JsonResponse
    {
        $user = $request->user();
        $this->authorizeMember($group, $user);

        $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $driveFolderId = null;

        if ($user->isDriveConnected()) {
            try {
                $drive = app(GoogleDriveService::class);
                $groupDriveFolderId = $this->ensureGroupDriveFolder($user, $group);
                $driveFolderId = $drive->findOrCreateFolder($user, $request->name, $groupDriveFolderId);
            } catch (\Throwable) {
                // Drive folder creation is best-effort — don't block the app folder creation
            }
        }

        $folder = StudentGroupFolder::create([
            'student_group_id' => $group->id,
            'name' => $request->name,
            'drive_folder_id' => $driveFolderId,
            'created_by' => $user->id,
        ]);

        $folder->load('creator:id,name')->loadCount('files');

        return response()->json([
            'message' => 'Folder created.',
            'data' => new GroupFolderResource($folder),
        ], 201);
    }

    /**
     * Delete a logical folder (only if it has no files).
     */
    public function destroyFolder(Request $request, StudentGroup $group, StudentGroupFolder $folder): JsonResponse
    {
        $this->ensureGroupInTenant($group);

        if (! $group->isMember($request->user()->id) || (int) $folder->student_group_id !== $group->id) {
            abort(403, 'You cannot manage this folder.');
        }

        if ($folder->files()->exists()) {
            throw ValidationException::withMessages([
                'folder' => 'Delete all files in this folder first.',
            ]);
        }

        $folder->delete();

        return response()->json([
            'message' => 'Folder deleted.',
            'data' => ['id' => $folder->id],
        ]);
    }

    private function authorizeFileAccess(StudentGroup $group, User $user, StudentGroupFile $file): void
    {
        $this->ensureGroupInTenant($group);

        if (! $group->isMember($user->id) || (int) $file->student_group_id !== $group->id) {
            abort(403, 'You cannot access this file.');
        }
    }

    /**
     * Upload to the student's Drive: Lectura/Workspace/{Group Name}/{Folder Name?}/file
     *
     * @return array{0: string, 1: string}
     */
    private function uploadToDrive(User $user, StudentGroup $group, ?StudentGroupFolder $appFolder, UploadedFile $file): array
    {
        $drive = app(GoogleDriveService::class);
        $groupFolderId = $this->ensureGroupDriveFolder($user, $group);
        $targetFolderId = $groupFolderId;

        if ($appFolder) {
            if ($appFolder->drive_folder_id) {
                $targetFolderId = $appFolder->drive_folder_id;
            } else {
                $targetFolderId = $drive->findOrCreateFolder($user, $appFolder->name, $groupFolderId);
                $appFolder->update(['drive_folder_id' => $targetFolderId]);
            }
        }

        $result = $drive->uploadFile(
            $user,
            $file->getRealPath(),
            $file->getClientOriginalName(),
            $file->getMimeType() ?? 'application/octet-stream',
            $targetFolderId,
        );

        return [$result['id'], $result['web_view_link']];
    }

    private function ensureGroupDriveFolder(User $user, StudentGroup $group): string
    {
        $drive = app(GoogleDriveService::class);
        $rootId = $drive->ensureRootFolder($user);
        $workspaceFolderId = $drive->findOrCreateFolder($user, 'Workspace', $rootId);

        return $drive->findOrCreateFolder($user, $group->name, $workspaceFolderId);
    }
}
