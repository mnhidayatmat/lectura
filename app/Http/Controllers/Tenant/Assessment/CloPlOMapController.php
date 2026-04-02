<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Assessment;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CloPlOMapController extends Controller
{
    public function edit(string $tenantSlug, Course $course): View
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $course->load(['learningOutcomes.programmeLearningOutcomes', 'programme.learningOutcomes']);

        $clos = $course->learningOutcomes;
        $plos = $course->programme?->learningOutcomes ?? collect();

        // Build existing mapping matrix
        $mappings = [];
        foreach ($clos as $clo) {
            foreach ($clo->programmeLearningOutcomes as $plo) {
                $mappings[$clo->id][$plo->id] = $plo->pivot->mapping_level;
            }
        }

        return view('tenant.clo-plo.edit', compact('tenant', 'course', 'clos', 'plos', 'mappings'));
    }

    public function update(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'mappings' => ['nullable', 'array'],
            'mappings.*' => ['array'],
            'mappings.*.*' => ['nullable', 'string', 'in:primary,secondary'],
        ]);

        $course->load('learningOutcomes');
        $mappingData = $request->input('mappings', []);

        foreach ($course->learningOutcomes as $clo) {
            $syncData = [];
            $cloMappings = $mappingData[$clo->id] ?? [];

            foreach ($cloMappings as $ploId => $level) {
                if ($level) {
                    $syncData[$ploId] = ['mapping_level' => $level];
                }
            }

            $clo->programmeLearningOutcomes()->sync($syncData);
        }

        return back()->with('success', 'CLO-PLO mapping saved.');
    }
}
