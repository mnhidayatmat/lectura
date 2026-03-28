<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;

class GoogleDriveService
{
    protected GoogleClient $client;

    public function __construct()
    {
        $this->client = new GoogleClient;
        $this->client->setClientId(config('services.google_drive.client_id'));
        $this->client->setClientSecret(config('services.google_drive.client_secret'));
        $this->client->setRedirectUri(config('services.google_drive.redirect'));
        $this->client->addScope(GoogleDrive::DRIVE_FILE);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function handleCallback(string $code, User $user): void
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException($token['error_description'] ?? $token['error']);
        }

        if (! isset($token['access_token'])) {
            throw new \RuntimeException('No access token received from Google. Please try again.');
        }

        $user->update([
            'drive_access_token' => json_encode($token),
            'drive_refresh_token' => $token['refresh_token'] ?? $user->drive_refresh_token,
            'drive_token_expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
        ]);
    }

    public function getClient(User $user): GoogleClient
    {
        $this->client->setAccessToken($user->drive_access_token);

        if ($this->client->isAccessTokenExpired() && $user->drive_refresh_token) {
            $token = $this->client->fetchAccessTokenWithRefreshToken($user->drive_refresh_token);

            if (isset($token['error']) || ! isset($token['access_token'])) {
                throw new \RuntimeException($token['error_description'] ?? $token['error'] ?? 'Failed to refresh Google Drive token. Please reconnect.');
            }

            $user->update([
                'drive_access_token' => json_encode($token),
                'drive_token_expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
            ]);
        }

        return $this->client;
    }

    public function getDriveService(User $user): GoogleDrive
    {
        return new GoogleDrive($this->getClient($user));
    }

    public function ensureRootFolder(User $user): string
    {
        if ($user->drive_root_folder_id) {
            return $user->drive_root_folder_id;
        }

        $drive = $this->getDriveService($user);

        $folder = new DriveFile([
            'name' => 'Lectura',
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        $created = $drive->files->create($folder, ['fields' => 'id']);

        $user->update(['drive_root_folder_id' => $created->id]);

        return $created->id;
    }

    /**
     * Validate that a folder ID exists and is accessible, then return its name.
     */
    public function getFolderInfo(User $user, string $folderId): array
    {
        $drive = $this->getDriveService($user);

        $file = $drive->files->get($folderId, ['fields' => 'id,name,mimeType']);

        if ($file->getMimeType() !== 'application/vnd.google-apps.folder') {
            throw new \RuntimeException('The specified ID is not a folder.');
        }

        return [
            'id' => $file->getId(),
            'name' => $file->getName(),
        ];
    }

    /**
     * Extract a Google Drive folder ID from a URL or return the raw ID.
     */
    public static function extractFolderId(string $input): string
    {
        $input = trim($input);

        // Match: https://drive.google.com/drive/folders/{ID}
        if (preg_match('#drive\.google\.com/drive/(?:u/\d+/)?folders/([a-zA-Z0-9_-]+)#', $input, $matches)) {
            return $matches[1];
        }

        // Match: https://drive.google.com/open?id={ID}
        if (preg_match('#drive\.google\.com/open\?id=([a-zA-Z0-9_-]+)#', $input, $matches)) {
            return $matches[1];
        }

        // Assume raw folder ID (alphanumeric, hyphens, underscores)
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $input)) {
            return $input;
        }

        throw new \RuntimeException('Could not extract a valid folder ID from the input.');
    }

    public function findOrCreateFolder(User $user, string $name, ?string $parentId = null): string
    {
        $drive = $this->getDriveService($user);
        $parent = $parentId ?? $this->ensureRootFolder($user);

        // Check if folder exists
        $query = "name='".addcslashes($name, "'")."' and mimeType='application/vnd.google-apps.folder' and '{$parent}' in parents and trashed=false";
        $results = $drive->files->listFiles(['q' => $query, 'fields' => 'files(id,name)', 'spaces' => 'drive']);

        if (count($results->getFiles()) > 0) {
            return $results->getFiles()[0]->getId();
        }

        $folder = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parent],
        ]);

        $created = $drive->files->create($folder, ['fields' => 'id']);

        return $created->id;
    }

    public function uploadFile(User $user, string $filePath, string $fileName, string $mimeType, ?string $folderId = null): string
    {
        $drive = $this->getDriveService($user);
        $parent = $folderId ?? $this->ensureRootFolder($user);

        $file = new DriveFile([
            'name' => $fileName,
            'parents' => [$parent],
        ]);

        $content = file_get_contents($filePath);

        $uploaded = $drive->files->create($file, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id,webViewLink',
        ]);

        return $uploaded->id;
    }

    public function getStorageQuota(User $user): array
    {
        $drive = $this->getDriveService($user);
        $about = $drive->about->get(['fields' => 'storageQuota,user']);

        $quota = $about->getStorageQuota();
        $driveUser = $about->getUser();

        return [
            'email' => $driveUser->getEmailAddress(),
            'display_name' => $driveUser->getDisplayName(),
            'photo' => $driveUser->getPhotoLink(),
            'total' => (int) ($quota->getLimit() ?? 0),
            'used' => (int) ($quota->getUsage() ?? 0),
            'used_in_drive' => (int) ($quota->getUsageInDrive() ?? 0),
        ];
    }

    public function disconnect(User $user): void
    {
        try {
            $this->client->setAccessToken($user->drive_access_token);
            $this->client->revokeToken();
        } catch (\Throwable) {
            // Token may already be revoked
        }

        $user->update([
            'drive_access_token' => null,
            'drive_refresh_token' => null,
            'drive_token_expires_at' => null,
            'drive_root_folder_id' => null,
        ]);
    }
}
