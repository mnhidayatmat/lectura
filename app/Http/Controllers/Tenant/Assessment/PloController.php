<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Assessment;

use App\Http\Controllers\Controller;
use App\Models\Programme;
use App\Models\ProgrammeLearningOutcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PloController extends Controller
{
    public function index(string $tenantSlug, Programme $programme): View
    {
        $tenant = app('current_tenant');
        $plos = $programme->learningOutcomes;

        return view('tenant.plos.index', compact('tenant', 'programme', 'plos'));
    }

    public function store(Request $request, string $tenantSlug, Programme $programme): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:2000'],
            'domain' => ['nullable', 'string', 'in:' . implode(',', ProgrammeLearningOutcome::DOMAINS)],
        ]);

        ProgrammeLearningOutcome::create([
            'tenant_id' => app('current_tenant')->id,
            'programme_id' => $programme->id,
            'code' => $request->code,
            'description' => $request->description,
            'domain' => $request->domain,
            'sort_order' => $programme->learningOutcomes()->count(),
        ]);

        return back()->with('success', 'PLO added.');
    }

    public function update(Request $request, string $tenantSlug, Programme $programme, ProgrammeLearningOutcome $plo): RedirectResponse
    {
        if ($plo->programme_id !== $programme->id) {
            abort(404);
        }

        $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:2000'],
            'domain' => ['nullable', 'string', 'in:' . implode(',', ProgrammeLearningOutcome::DOMAINS)],
        ]);

        $plo->update($request->only('code', 'description', 'domain'));

        return back()->with('success', 'PLO updated.');
    }

    public function destroy(string $tenantSlug, Programme $programme, ProgrammeLearningOutcome $plo): RedirectResponse
    {
        if ($plo->programme_id !== $programme->id) {
            abort(404);
        }

        $plo->delete();

        return back()->with('success', 'PLO deleted.');
    }
}
