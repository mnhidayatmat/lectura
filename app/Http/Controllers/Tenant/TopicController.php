<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    use AuthorizesCourseAccess;

    public function store(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $request->validate([
            'week_number' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'clo_ids' => ['nullable', 'array'],
            'clo_ids.*' => ['integer'],
        ]);

        CourseTopic::create([
            'course_id' => $course->id,
            'week_number' => $request->week_number,
            'title' => $request->title,
            'clo_ids' => $this->courseCloIds($course, $request->input('clo_ids', [])),
            'sort_order' => $request->week_number,
        ]);

        return back()->with('success', "Week {$request->week_number} topic added.");
    }

    /**
     * Change which CLOs a week's topic is linked to.
     */
    public function update(Request $request, string $tenantSlug, Course $course, CourseTopic $topic): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        if ($topic->course_id !== $course->id) {
            abort(404);
        }

        $request->validate([
            'clo_ids' => ['nullable', 'array'],
            'clo_ids.*' => ['integer'],
        ]);

        $topic->update([
            'clo_ids' => $this->courseCloIds($course, $request->input('clo_ids', [])),
        ]);

        return back()->with('success', "Week {$topic->week_number} CLOs updated.");
    }

    public function destroy(string $tenantSlug, Course $course, CourseTopic $topic): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        if ($topic->course_id !== $course->id) {
            abort(404);
        }

        $topic->delete();

        return back()->with('success', 'Topic removed.');
    }

    /**
     * Keeps only ids of CLOs that belong to this course.
     */
    private function courseCloIds(Course $course, array $cloIds): ?array
    {
        $ids = $course->learningOutcomes()
            ->whereIn('id', array_map('intval', $cloIds))
            ->pluck('id')
            ->all();

        return $ids ?: null;
    }
}
