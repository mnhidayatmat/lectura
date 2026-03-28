<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        protected GoogleDriveService $driveService,
    ) {}

    public function index(): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();
        $driveInfo = null;

        $rootFolderInfo = null;

        if ($user->isDriveConnected()) {
            try {
                $driveInfo = $this->driveService->getStorageQuota($user);

                if ($user->drive_root_folder_id) {
                    $rootFolderInfo = $this->driveService->getFolderInfo($user, $user->drive_root_folder_id);
                }
            } catch (\Throwable $e) {
                // Token expired or revoked externally
                $driveInfo = ['error' => 'Unable to connect. Please reconnect your Google Drive.'];
            }
        }

        return view('tenant.settings.index', compact('tenant', 'driveInfo', 'rootFolderInfo'));
    }

    public function connectDrive(): RedirectResponse
    {
        $url = $this->driveService->getAuthUrl();

        // Store tenant slug and return URL in session for the callback
        session([
            'drive_tenant_slug' => app('current_tenant')->slug,
            'drive_return_url' => url()->previous(),
        ]);

        return redirect($url);
    }

    public function driveCallback(Request $request): RedirectResponse
    {
        $tenantSlug = session('drive_tenant_slug');

        if (! $tenantSlug) {
            return redirect('/')->with('error', 'Session expired. Please try connecting Google Drive again.');
        }

        if ($request->has('error')) {
            return redirect()->route('tenant.settings', $tenantSlug)
                ->with('error', 'Google Drive authorization was cancelled.');
        }

        try {
            $this->driveService->handleCallback($request->code, auth()->user());

            // Create root Lectura folder
            $this->driveService->ensureRootFolder(auth()->user());

            session()->forget('drive_tenant_slug');

            return redirect()->route('tenant.settings', $tenantSlug)
                ->with('success', 'Google Drive connected successfully! A "Lectura" folder has been created in your Drive.');
        } catch (\Throwable $e) {
            return redirect()->route('tenant.settings', $tenantSlug)
                ->with('error', 'Failed to connect Google Drive: '.$e->getMessage());
        }
    }

    public function updateDriveFolder(Request $request): RedirectResponse
    {
        $request->validate([
            'folder_input' => ['required', 'string', 'max:500'],
        ]);

        $user = auth()->user();
        $tenantSlug = app('current_tenant')->slug;

        try {
            $folderId = GoogleDriveService::extractFolderId($request->folder_input);
            $folderInfo = $this->driveService->getFolderInfo($user, $folderId);

            $user->update(['drive_root_folder_id' => $folderInfo['id']]);

            return redirect()->route('tenant.settings', $tenantSlug)
                ->with('success', 'Root folder set to "'.$folderInfo['name'].'".');
        } catch (\Throwable $e) {
            return redirect()->route('tenant.settings', $tenantSlug)
                ->with('error', 'Could not set folder: '.$e->getMessage());
        }
    }

    public function resetDriveFolder(): RedirectResponse
    {
        $user = auth()->user();
        $tenantSlug = app('current_tenant')->slug;

        try {
            // Create a fresh "Lectura" folder
            $user->update(['drive_root_folder_id' => null]);
            $this->driveService->ensureRootFolder($user);

            return redirect()->route('tenant.settings', $tenantSlug)
                ->with('success', 'Root folder reset to default "Lectura" folder.');
        } catch (\Throwable $e) {
            return redirect()->route('tenant.settings', $tenantSlug)
                ->with('error', 'Could not reset folder: '.$e->getMessage());
        }
    }

    public function disconnectDrive(): RedirectResponse
    {
        $this->driveService->disconnect(auth()->user());

        return redirect()->route('tenant.settings', app('current_tenant')->slug)
            ->with('success', 'Google Drive disconnected.');
    }
}
