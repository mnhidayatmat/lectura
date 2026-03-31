<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicTermController extends Controller
{
    public function index(): View
    {
        $terms = AcademicTerm::withCount(['courses', 'sections'])
            ->orderByDesc('start_date')
            ->get();

        return view('tenant.academic-terms.index', compact('terms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $tenant = app('current_tenant');

        // If setting as default, unset others
        if ($request->boolean('is_default')) {
            AcademicTerm::where('is_default', true)->update(['is_default' => false]);
        }

        AcademicTerm::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'code' => $request->code,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_default' => $request->boolean('is_default'),
        ]);

        return redirect()->route('tenant.academic-terms.index', $tenant->slug)
            ->with('success', 'Semester created successfully.');
    }

    public function update(Request $request, string $tenantSlug, AcademicTerm $term): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $tenant = app('current_tenant');

        if ($request->boolean('is_default') && ! $term->is_default) {
            AcademicTerm::where('is_default', true)->update(['is_default' => false]);
        }

        $term->update([
            'name' => $request->name,
            'code' => $request->code,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_default' => $request->boolean('is_default'),
        ]);

        return redirect()->route('tenant.academic-terms.index', $tenant->slug)
            ->with('success', 'Semester updated successfully.');
    }

    public function destroy(string $tenantSlug, AcademicTerm $term): RedirectResponse
    {
        $tenant = app('current_tenant');

        if ($term->courses()->exists() || $term->sections()->exists()) {
            return redirect()->route('tenant.academic-terms.index', $tenant->slug)
                ->with('error', 'Cannot delete a semester that has courses or sections assigned to it.');
        }

        $term->delete();

        return redirect()->route('tenant.academic-terms.index', $tenant->slug)
            ->with('success', 'Semester deleted successfully.');
    }
}
