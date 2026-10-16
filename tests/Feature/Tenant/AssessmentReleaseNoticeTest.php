<?php

namespace Tests\Feature\Tenant;

use App\Notifications\AssessmentPublished;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Student\BuildsStudentFixtures;

/**
 * Releasing work students must hand in tells them once, in the app's list and
 * as a phone push, unless the lecturer turned the notice off.
 */
class AssessmentReleaseNoticeTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    public function test_activating_a_submission_assessment_notifies_enrolled_students_once(): void
    {
        Notification::fake();
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $outsider = $this->createMember($tenant, 'student');
        $assessment = $this->createAssessment($course, ['title' => 'Lab 1', 'status' => 'draft', 'requires_submission' => true]);

        Notification::assertNothingSent();

        $this->actingAs($lecturer)->put("/{$tenant->slug}/courses/{$course->id}/assessments/{$assessment->id}", [
            'title' => 'Lab 1',
            'type' => 'lab',
            'weightage' => 10,
            'total_marks' => 100,
            'status' => 'active',
            'requires_submission' => 1,
            'notify_students' => 1,
        ])->assertRedirect();

        Notification::assertSentToTimes($student, AssessmentPublished::class, 1);
        Notification::assertSentTo($student, AssessmentPublished::class, function (AssessmentPublished $notice, array $channels) use ($student, $assessment) {
            $data = $notice->toArray($student);

            return in_array(FcmChannel::class, $channels, true)
                && $data['type'] === 'assessment_published'
                && $data['assessment_id'] === $assessment->id
                && $data['course_id'] === $assessment->course_id;
        });
        Notification::assertNotSentTo($outsider, AssessmentPublished::class);
        $this->assertNotNull($assessment->fresh()->announced_at);

        // Saving it again later never repeats the notice.
        $assessment->fresh()->update(['title' => 'Lab 1 (revised)']);
        Notification::assertSentToTimes($student, AssessmentPublished::class, 1);
    }

    public function test_no_notice_when_turned_off_or_not_released(): void
    {
        Notification::fake();
        [, , $student, $course] = $this->enrolledStudent();

        $this->createAssessment($course, ['status' => 'draft', 'requires_submission' => true]);
        $this->createAssessment($course, ['status' => 'active', 'requires_submission' => false]);
        $this->createAssessment($course, ['status' => 'active', 'requires_submission' => true, 'notify_students' => false]);

        Notification::assertNotSentTo($student, AssessmentPublished::class);
    }

    public function test_creating_an_active_submission_assessment_notifies_at_once(): void
    {
        Notification::fake();
        [, , $student, $course] = $this->enrolledStudent();

        $this->createAssessment($course, ['status' => 'active', 'requires_submission' => true]);

        Notification::assertSentToTimes($student, AssessmentPublished::class, 1);
    }
}
