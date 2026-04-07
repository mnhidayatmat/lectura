<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\ActiveLearning;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActiveLearning\StoreActivityRequest;
use App\Http\Requests\ActiveLearning\UpdateActivityRequest;
use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPlan;
use App\Models\Course;
use App\Services\ActiveLearning\ActiveLearningPlanService;
use App\Services\ActiveLearning\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveLearningActivityController extends Controller
{
    use AuthorizesCourseAccess;
    public function __construct(
        protected ActivityService $activityService,
        protected ActiveLearningPlanService $planService,
    ) {}

    public function store(StoreActivityRequest $request, string $tenantSlug, Course $course, ActiveLearningPlan $plan): RedirectResponse
    {
        $this->authorizeAndValidate($course, $plan);

        $this->activityService->addActivity($plan, $request->validated());

        return back()->with('success', __('active_learning.activity_added'));
    }

    public function update(UpdateActivityRequest $request, string $tenantSlug, Course $course, ActiveLearningPlan $plan, ActiveLearningActivity $activity): RedirectResponse
    {
        $this->authorizeAndValidate($course, $plan);
        $this->assertActivityBelongsToPlan($activity, $plan);

        $this->activityService->updateActivity($activity, $request->validated());

        return back()->with('success', __('active_learning.activity_updated'));
    }

    public function destroy(string $tenantSlug, Course $course, ActiveLearningPlan $plan, ActiveLearningActivity $activity): RedirectResponse
    {
        $this->authorizeAndValidate($course, $plan);
        $this->assertActivityBelongsToPlan($activity, $plan);

        $this->activityService->removeActivity($activity);

        return back()->with('success', __('active_learning.activity_removed'));
    }

    public function reorder(Request $request, string $tenantSlug, Course $course, ActiveLearningPlan $plan): JsonResponse
    {
        $this->authorizeAndValidate($course, $plan);

        $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        $this->planService->reorderActivities($plan, $request->input('ordered_ids'));

        return response()->json(['success' => true]);
    }

    protected function authorizeAndValidate(Course $course, ActiveLearningPlan $plan): void
    {
        $this->authorizeCourseAccess($course);

        if ($plan->course_id !== $course->id) {
            abort(404);
        }
    }

    protected function assertActivityBelongsToPlan(ActiveLearningActivity $activity, ActiveLearningPlan $plan): void
    {
        if ($activity->active_learning_plan_id !== $plan->id) {
            abort(404);
        }
    }
}
