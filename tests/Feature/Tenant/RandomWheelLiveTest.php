<?php

namespace Tests\Feature\Tenant;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use Illuminate\Support\Str;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * The web wheel shares each spin, and students watch it on the Live Wheel page.
 */
class RandomWheelLiveTest extends ApiTestCase
{
    public function test_a_spin_from_the_web_wheel_reaches_the_students_live_wheel(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $section = $this->createSection($this->createCourse($tenant, $lecturer, ['code' => 'BTD2232']));
        $session = AttendanceSession::create([
            'tenant_id' => $tenant->id,
            'section_id' => $section->id,
            'lecturer_id' => $lecturer->id,
            'session_type' => 'lecture',
            'qr_secret' => Str::random(64),
            'qr_mode' => 'rotating',
            'qr_rotation_seconds' => 30,
            'late_threshold_minutes' => 15,
            'status' => 'active',
            'started_at' => now()->subMinutes(5),
        ]);
        $student = $this->createMember($tenant, 'student', ['name' => 'Aina']);
        $this->enroll($section, $student);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'user_id' => $student->id, 'status' => 'present', 'checked_in_at' => now(), 'method' => 'qr_scan']);

        $this->actingAs($lecturer)
            ->postJson("/{$tenant->slug}/random-wheel/spins", [
                'session_id' => $session->id,
                'candidate_ids' => [$student->id],
                'winner_id' => $student->id,
                'turns' => 5,
                'duration_ms' => 4800,
            ])
            ->assertOk();

        $this->actingAs($student)
            ->get("/{$tenant->slug}/live-wheel")
            ->assertOk()
            ->assertSee(__('random_wheel.live_title'));

        $this->getJson("/{$tenant->slug}/live-wheel/state")
            ->assertOk()
            ->assertJsonPath('spin.winner.name', 'Aina')
            ->assertJsonPath('spin.course.code', 'BTD2232')
            ->assertJsonPath('spin.is_me', true);
    }
}
