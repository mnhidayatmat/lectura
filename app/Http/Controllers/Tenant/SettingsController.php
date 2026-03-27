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

        if ($user->isDriveConnected()) {
            try {
                $driveInfo = $this->driveService->getStorageQuota($user);
            } catch (\Throwable $e) {
                // Token expired or revoked externally
                $driveInfo = ['error' => 'Unable to connect. Please reconnect your Google Drive.'];
            }
        }

        return view('tenant.settings.index', compact('tenant', 'driveInfo'));
    }

    public function connectDrive(): RedirectResponse
    {
        $url = $this->driveService->getAuthUrl();

        // Store return URL in session
        session(['drive_return_url' => url()->previous()]);

        return redirect($url);
    }

    public function driveCallback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()->route('tenant.settings', app('current_tenant')->slug)
                ->with('error', 'Google Drive authorization was cancelled.');
        }

        try {
            $this->driveService->handleCallback($request->code, auth()->user());

            // Create root Lectura folder
            $this->driveService->ensureRootFolder(auth()->user());

            return redirect()->route('tenant.settings', app('current_tenant')->slug)
                ->with('success', 'Google Drive connected successfully! A "Lectura" folder has been created in your Drive.');
        } catch (\Throwable $e) {
            return redirect()->route('tenant.settings', app('current_tenant')->slug)
                ->with('error', 'Failed to connect Google Drive: ' . $e->getMessage());
        }
    }

    public function disconnectDrive(): RedirectResponse
    {
        $this->driveService->disconnect(auth()->user());

        return redirect()->route('tenant.settings', app('current_tenant')->slug)
            ->with('success', 'Google Drive disconnected.');
    }
}
