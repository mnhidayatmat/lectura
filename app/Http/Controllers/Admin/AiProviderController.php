<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiProviderController extends Controller
{
    private function authorize(): void
    {
        if (! auth()->user()?->is_super_admin) {
            abort(403);
        }
    }

    public function index(): View
    {
        $this->authorize();
        $providers = AiProvider::orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('admin.ai-settings', [
            'providers' => $providers,
            'providerTypes' => AiProvider::getProviderTypes(),
            'defaultModels' => AiProvider::getDefaultModels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'provider_type' => ['required', 'string', Rule::in(array_keys(AiProvider::getProviderTypes()))],
            'api_key' => ['nullable', 'string', 'max:500'],
            'api_base_url' => ['nullable', 'url', 'max:500'],
            'model' => ['required', 'string', 'max:100'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:200000'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'top_p' => ['required', 'numeric', 'min:0', 'max:1'],
            'timeout_seconds' => ['required', 'integer', 'min:10', 'max:600'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Ensure unique slug
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (AiProvider::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
        }

        // If setting as default, unset other defaults
        if (! empty($validated['is_default'])) {
            AiProvider::where('is_default', true)->update(['is_default' => false]);
        }

        AiProvider::create($validated);

        return redirect()->route('admin.ai-settings')
            ->with('success', 'AI provider "' . $validated['name'] . '" has been added.');
    }

    public function update(Request $request, AiProvider $aiProvider): RedirectResponse
    {
        $this->authorize();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'provider_type' => ['required', 'string', Rule::in(array_keys(AiProvider::getProviderTypes()))],
            'api_key' => ['nullable', 'string', 'max:500'],
            'api_base_url' => ['nullable', 'url', 'max:500'],
            'model' => ['required', 'string', 'max:100'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:200000'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'top_p' => ['required', 'numeric', 'min:0', 'max:1'],
            'timeout_seconds' => ['required', 'integer', 'min:10', 'max:600'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        // If API key field is empty, keep the existing one
        if (empty($validated['api_key'])) {
            unset($validated['api_key']);
        }

        // If setting as default, unset other defaults
        if (! empty($validated['is_default'])) {
            AiProvider::where('is_default', true)
                ->where('id', '!=', $aiProvider->id)
                ->update(['is_default' => false]);
        }

        $aiProvider->update($validated);

        return redirect()->route('admin.ai-settings')
            ->with('success', 'AI provider "' . $aiProvider->name . '" has been updated.');
    }

    public function destroy(AiProvider $aiProvider): RedirectResponse
    {
        $this->authorize();

        $name = $aiProvider->name;

        if ($aiProvider->is_default) {
            return back()->with('error', 'Cannot delete the default provider. Set another provider as default first.');
        }

        $aiProvider->delete();

        return redirect()->route('admin.ai-settings')
            ->with('success', 'AI provider "' . $name . '" has been deleted.');
    }

    public function testConnection(AiProvider $aiProvider): RedirectResponse
    {
        $this->authorize();

        if (! $aiProvider->hasApiKey()) {
            return back()->with('error', 'No API key configured for "' . $aiProvider->name . '". Please add an API key first.');
        }

        // For now, validate the key format based on provider type
        $key = $aiProvider->getDecryptedApiKey();
        $valid = match ($aiProvider->provider_type) {
            'anthropic' => str_starts_with($key, 'sk-ant-'),
            'openai' => str_starts_with($key, 'sk-'),
            'google' => strlen($key) > 10,
            default => strlen($key) > 5,
        };

        if ($valid) {
            return back()->with('success', 'API key format for "' . $aiProvider->name . '" looks valid. Full connection test will be available when providers are connected.');
        }

        return back()->with('error', 'API key format for "' . $aiProvider->name . '" does not match expected pattern for ' . $aiProvider->provider_type . '.');
    }
}
