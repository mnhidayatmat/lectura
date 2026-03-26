<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    public function store(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $request->validate([
            'week_number' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
        ]);

        CourseTopic::create([
            'course_id' => $course->id,
            'week_number' => $request->week_number,
            'title' => $request->title,
            'sort_order' => $request->week_number,
        ]);

        return back()->with('success', "Week {$request->week_number} topic added.");
    }

    public function destroy(string $tenantSlug, Course $course, CourseTopic $topic): RedirectResponse
    {
        if ($topic->course_id !== $course->id) {
            abort(404);
        }

        $topic->delete();

        return back()->with('success', 'Topic removed.');
    }
}
