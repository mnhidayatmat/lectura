<?php

declare(strict_types=1);

namespace App\Services\Performance;

use App\Models\Course;
use App\Models\User;
use App\Services\AI\AiServiceManager;

class PerformanceAiService
{
    public function __construct(
        protected AiServiceManager $ai,
        protected PerformanceAggregatorService $aggregator,
    ) {}

    public function generateCourseSuggestions(Course $course): array
    {
        $data = $this->aggregator->getCoursePerformance($course);
        $prompt = $this->buildCoursePrompt($course, $data);

        $result = $this->ai->complete($prompt, [
            'module' => 'performance_analysis',
            'course_id' => $course->id,
        ]);

        return $this->parseResponse($result['content'] ?? '');
    }

    public function generateStudentSuggestions(User $student, Course $course): array
    {
        $data = $this->aggregator->getStudentCoursePerformance($student, $course);
        $prompt = $this->buildStudentPrompt($student, $course, $data);

        $result = $this->ai->complete($prompt, [
            'module' => 'performance_analysis',
            'course_id' => $course->id,
        ]);

        return $this->parseResponse($result['content'] ?? '');
    }

    protected function buildCoursePrompt(Course $course, array $data): string
    {
        $clos = $course->learningOutcomes
            ->pluck('description', 'code')
            ->map(fn ($d, $c) => "{$c}: {$d}")
            ->implode("\n");

        $cloAttainment = collect($data['clo_attainment'])
            ->map(fn ($c) => "{$c['code']}: " . ($c['avg'] !== null ? "{$c['avg']}% (n={$c['count']})" : 'No data'))
            ->implode("\n");

        $assignmentSummary = $data['assignment_stats']
            ->map(fn ($a) => "{$a['title']}: avg={$a['avg']}%, min={$a['min']}%, max={$a['max']}%")
            ->implode("\n");

        $atRiskList = $data['at_risk_students']
            ->map(fn ($s) => "{$s['user']->name}: composite={$s['composite_score']}%, marks={$s['avg_mark']}%, attendance={$s['attendance_rate']}%")
            ->implode("\n");

        return <<<PROMPT
You are an educational quality analyst helping a lecturer improve their course through CQI (Continuous Quality Improvement).

Course: {$course->code} — {$course->title}
Total Students: {$data['total_students']}
Average Mark: {$data['avg_mark']}%
Average Quiz Score: {$data['avg_quiz']}
Attendance Rate: {$data['attendance_rate']}%
At-Risk Students: {$data['at_risk_count']}

Course Learning Outcomes (CLOs):
{$clos}

CLO Attainment:
{$cloAttainment}

Assignment Performance:
{$assignmentSummary}

At-Risk Students:
{$atRiskList}

Analyse this data and respond ONLY with a JSON object (no other text):
{
  "overall_assessment": "2-3 sentence summary of course health",
  "strengths": ["strength 1", "strength 2", ...],
  "weaknesses": [
    {"area": "description", "clo_codes": ["CLO1"], "severity": "high|medium|low", "evidence": "specific data point"}
  ],
  "recommendations": [
    {"action": "specific action", "target": "who/what", "priority": "high|medium|low", "rationale": "why"}
  ],
  "at_risk_interventions": [
    {"pattern": "common pattern among at-risk students", "intervention": "suggested action"}
  ],
  "cqi_actions": [
    {"area": "teaching|assessment|content|engagement", "action": "specific improvement", "expected_impact": "description"}
  ]
}
PROMPT;
    }

    protected function buildStudentPrompt(User $student, Course $course, array $data): string
    {
        $cloSummary = collect($data['clo_attainment'])
            ->map(fn ($c) => "{$c['code']}: " . ($c['avg'] !== null ? "{$c['avg']}%" : 'No data'))
            ->implode(', ');

        $marksList = $data['marks']
            ->map(fn ($m) => "{$m->assignment->title}: {$m->percentage}%")
            ->implode("\n");

        $quizList = $data['quiz_participations']
            ->map(fn ($p) => "{$p->quizSession->title}: {$p->total_score}")
            ->implode("\n");

        return <<<PROMPT
You are an educational advisor analysing an individual student's performance to suggest improvements.

Student: {$student->name}
Course: {$course->code} — {$course->title}
Average Mark: {$data['avg_mark']}%
Average Quiz Score: {$data['avg_quiz']}
Attendance Rate: {$data['attendance_rate']}%
Active Learning Responses: {$data['al_responses']}
Composite Score: {$data['composite_score']}%

CLO Attainment: {$cloSummary}

Assignment Marks:
{$marksList}

Quiz Scores:
{$quizList}

Analyse this student's performance and respond ONLY with a JSON object (no other text):
{
  "summary": "2-3 sentence overall assessment",
  "strengths": ["strength 1", "strength 2"],
  "weaknesses": [
    {"area": "description", "clo_codes": ["CLO1"], "evidence": "specific data"}
  ],
  "improvement_plan": [
    {"focus_area": "area", "action": "specific study strategy", "priority": "high|medium|low"}
  ],
  "study_tips": ["actionable tip 1", "actionable tip 2"],
  "risk_level": "low|medium|high|critical"
}
PROMPT;
    }

    protected function parseResponse(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $m)) {
            $content = trim($m[1]);
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : ['error' => 'Failed to parse AI response', 'raw' => mb_substr($content, 0, 500)];
    }
}
