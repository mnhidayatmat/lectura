<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorImageController extends Controller
{
    public function upload(Request $request, GoogleDriveService $driveService): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $user = $request->user();
        $file = $request->file('image');

        // If lecturer has Google Drive connected, upload there
        if ($user->isDriveConnected()) {
            try {
                $folderId = $driveService->findOrCreateFolder($user, 'Editor Images');

                $result = $driveService->uploadFile(
                    $user,
                    $file->getRealPath(),
                    $file->getClientOriginalName(),
                    $file->getClientMimeType(),
                    $folderId
                );

                // Make the file publicly viewable so it can be embedded as <img>
                $drive = $driveService->getDriveService($user);
                $drive->permissions->create($result['id'], new \Google\Service\Drive\Permission([
                    'type' => 'anyone',
                    'role' => 'reader',
                ]));

                // Use the direct thumbnail/content link for embedding
                $url = 'https://drive.google.com/thumbnail?id=' . $result['id'] . '&sz=w1200';

                return response()->json(['url' => $url]);
            } catch (\Throwable $e) {
                // Fall through to local storage if Drive fails
                report($e);
            }
        }

        // Fallback: store locally
        $path = $file->store('editor-images/' . date('Y/m'), 'public');

        return response()->json([
            'url' => asset('storage/' . $path),
        ]);
    }
}
