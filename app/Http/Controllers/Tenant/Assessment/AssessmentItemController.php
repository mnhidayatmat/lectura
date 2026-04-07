<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Assessment;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\QuizSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssessmentItemController extends Controller
{
    use AuthorizesCourseAccess;
    public function store(Request $request, string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $request->validate([
            'assessable' => ['required', 'string'],
        ]);

        [$type, $id] = explode(':', $request->assessable);

        $assessableType = match ($type) {
            'assignment' => Assignment::class,
            'quiz' => QuizSession::class,
            default => abort(422, 'Invalid item type'),
        };

        // Verify the item exists
        $assessableType::findOrFail($id);

        AssessmentItem::firstOrCreate([
            'assessment_id' => $assessment->id,
            'assessable_type' => $assessableType,
            'assessable_id' => $id,
        ], [
            'sort_order' => $assessment->items()->count(),
        ]);

        return back()->with('success', 'Item linked to assessment.');
    }

    public function destroy(string $tenantSlug, Course $course, Assessment $assessment, AssessmentItem $item): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id || $item->assessment_id !== $assessment->id) {
            abort(403);
        }

        $item->delete();

        return back()->with('success', 'Item unlinked.');
    }
}
