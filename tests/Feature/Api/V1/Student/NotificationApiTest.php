<?php

namespace Tests\Feature\Api\V1\Student;

use App\Notifications\AttendanceWarningNotification;
use Tests\Feature\Api\V1\ApiTestCase;

class NotificationApiTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    public function test_lists_notifications_with_unread_meta(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $student->notify(new AttendanceWarningNotification($course, 2, 'Warning 2', 25.0, 4));

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'notifications'))
            ->assertOk()
            ->assertJsonPath('data.0.type', 'AttendanceWarningNotification')
            ->assertJsonPath('data.0.kind', 'attendance_warning')
            ->assertJsonPath('data.0.title', 'Attendance Warning 2')
            ->assertJsonPath('data.0.color', 'amber')
            ->assertJsonPath('data.0.related.course_id', $course->id)
            ->assertJsonPath('data.0.related.level', 2)
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_unread_count_has_the_shape_the_app_expects(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $student->notify(new AttendanceWarningNotification($course, 1, 'Warning 1', 20.0, 3));

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'notifications/unread-count'))
            ->assertOk()
            ->assertExactJson(['data' => ['count' => 1]]);
    }

    public function test_marks_one_and_then_all_notifications_as_read(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $student->notify(new AttendanceWarningNotification($course, 1, 'Warning 1', 20.0, 3));
        $student->notify(new AttendanceWarningNotification($course, 2, 'Warning 2', 25.0, 4));
        $first = $student->notifications()->first();

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "notifications/{$first->id}/read"))
            ->assertOk()
            ->assertJsonPath('data.id', $first->id)
            ->assertJsonPath('data.unread_count', 1);

        $this->postJson($this->tenantApi($tenant, 'notifications/read-all'))
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, $student->unreadNotifications()->count());
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $lecturer->notify(new AttendanceWarningNotification($course, 1, 'Warning 1', 20.0, 3));
        $theirs = $lecturer->notifications()->first();

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "notifications/{$theirs->id}/read"))
            ->assertNotFound();
    }

    public function test_lecturers_can_use_notifications_too(): void
    {
        [$tenant, $lecturer] = $this->enrolledStudent();

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'notifications'))
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.unread_count', 0);
    }
}
