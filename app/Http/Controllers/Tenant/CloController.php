<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLearningOutcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CloController extends Controller
{
    public function store(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $maxOrder = $course->learningOutcomes()->max('sort_order') ?? -1;

        CourseLearningOutcome::create([
            'course_id' => $course->id,
            'code' => $request->code,
            'description' => $request->description,
            'sort_order' => $maxOrder + 1,
        ]);

        return back()->with('success', "CLO {$request->code} added.");
    }

    public function destroy(string $tenantSlug, Course $course, CourseLearningOutcome $clo): RedirectResponse
    {
        if ($clo->course_id !== $course->id) {
            abort(404);
        }

        $clo->delete();

        return back()->with('success', 'CLO removed.');
    }
}
