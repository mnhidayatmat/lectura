<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Assessment;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\View\View;

class AssessmentReportController extends Controller
{
    use AuthorizesCourseAccess;
    public function courseReport(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourseAccess($course);

        $tenant = app('current_tenant');
        $course->load([
            'learningOutcomes',
            'assessments.clos',
            'assessments.items.assessable',
            'assessments.scores',
        ]);

        // Build TOS matrix data
        $tosMatrix = $course->assessments->map(fn ($a) => [
            'title' => $a->title,
            'type' => $a->type,
            'weightage' => $a->weightage,
            'bloom_level' => $a->bloom_level,
            'clo_ids' => $a->clos->pluck('id')->toArray(),
        ]);

        // CLO attainment (average percentage per CLO from assessment_clo_scores)
        $cloAttainment = [];
        foreach ($course->learningOutcomes as $clo) {
            $scores = \App\Models\AssessmentCloScore::where('course_learning_outcome_id', $clo->id)
                ->whereIn('assessment_id', $course->assessments->pluck('id'))
                ->get();

            $cloAttainment[$clo->id] = [
                'code' => $clo->code,
                'description' => $clo->description,
                'average' => $scores->isNotEmpty() ? round($scores->avg('percentage'), 1) : null,
                'count' => $scores->groupBy('user_id')->count(),
            ];
        }

        return view('tenant.assessments.reports.course', compact('tenant', 'course', 'tosMatrix', 'cloAttainment'));
    }
}
