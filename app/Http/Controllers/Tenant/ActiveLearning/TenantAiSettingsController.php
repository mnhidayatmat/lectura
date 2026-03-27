<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\ActiveLearning;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Services\ActiveLearning\TierGateService;
use App\Services\AI\AiServiceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantAiSettingsController extends Controller
{
    public function __construct(
        protected TierGateService $tierGate,
    ) {}

    public function edit(string $tenantSlug): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        if (! $user->hasRoleInTenant($tenant->id, ['admin', 'coordinator'])) {
            abort(403);
        }

        $this->tierGate->assertProFeature(auth()->user(), __('active_learning.ai_settings'));

        $providers = ['claude', 'openai', 'gemini'];
        $keyStatus = [];
        foreach ($providers as $provider) {
            $keyStatus[$provider] = $tenant->getAiApiKey($provider) !== null;
        }

        $currentProvider = $tenant->getAiProvider();

        $usageStats = AiUsageLog::where('tenant_id', $tenant->id)
            ->selectRaw('module, COUNT(*) as total_calls, SUM(input_tokens) as total_input_tokens, SUM(output_tokens) as total_output_tokens')
            ->groupBy('module')
            ->get();

        return view('tenant.admin.ai-settings', compact('tenant', 'providers', 'keyStatus', 'currentProvider', 'usageStats'));
    }

    public function update(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        if (! $user->hasRoleInTenant($tenant->id, ['admin', 'coordinator'])) {
            abort(403);
        }

        $this->tierGate->assertProFeature(auth()->user(), __('active_learning.ai_settings'));

        $request->validate([
            'provider' => ['required', 'in:claude,openai,gemini'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'remove_key' => ['nullable', 'in:claude,openai,gemini'],
        ]);

        // Update default provider
        $tenant->updateSetting('ai.provider', $request->input('provider'));

        // Handle key removal
        if ($request->filled('remove_key')) {
            $tenant->setAiApiKey($request->input('remove_key'), null);

            return back()->with('success', __('active_learning.ai_key_removed'));
        }

        // Handle key update
        if ($request->filled('api_key')) {
            $tenant->setAiApiKey($request->input('provider'), $request->input('api_key'));
        }

        return back()->with('success', __('active_learning.ai_settings_updated'));
    }

    public function testConnection(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        if (! $user->hasRoleInTenant($tenant->id, ['admin', 'coordinator'])) {
            abort(403);
        }

        $this->tierGate->assertProFeature(auth()->user(), __('active_learning.ai_settings'));

        try {
            $ai = app(AiServiceManager::class);
            $ai->resetProvider();
            $result = $ai->complete('Respond with exactly: OK', [
                'module' => 'connection_test',
            ]);

            return back()->with('success', __('active_learning.ai_connection_success'));
        } catch (\Throwable $e) {
            return back()->with('error', __('active_learning.ai_connection_failed', ['error' => $e->getMessage()]));
        }
    }
}
