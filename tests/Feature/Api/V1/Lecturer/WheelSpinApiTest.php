<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\DeviceToken;
use App\Models\RandomWheelSpin;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WheelSpinApiTest extends LecturerApiTestCase
{
    private const FCM_SEND = 'https://fcm.googleapis.com/v1/projects/lectura-test/messages:send';

    public function test_enrolled_students_see_the_spin_their_lecturer_shared(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $section = $this->createSection($this->createCourse($tenant, $lecturer, ['code' => 'BTG3333']));
        $session = $this->startAttendance($section, $lecturer);
        $aina = $this->createMember($tenant, 'student', ['name' => 'Aina']);
        $badrul = $this->createMember($tenant, 'student', ['name' => 'Badrul']);
        foreach ([$aina, $badrul] as $student) {
            $this->enroll($section, $student);
            $this->markAttendance($session, $student);
        }
        $elsewhere = $this->createMember($tenant);
        $this->enroll($this->createSection($this->createCourse($tenant, $lecturer)), $elsewhere);

        $this->actingAsApi($lecturer)
            ->postJson($this->tenantApi($tenant, 'lecturer/wheel/spins'), [
                'session_id' => $session->id,
                'candidate_ids' => [$aina->id, $badrul->id],
                'winner_id' => $badrul->id,
                'turns' => 6,
                'duration_ms' => 5000,
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'data' => ['id']]);

        $this->actingAsApi($badrul)->getJson($this->tenantApi($tenant, 'student/wheel'))
            ->assertOk()
            ->assertJsonPath('data.course.code', 'BTG3333')
            ->assertJsonPath('data.candidates.0.name', 'Aina')
            ->assertJsonPath('data.winner.name', 'Badrul')
            ->assertJsonPath('data.winner_index', 1)
            ->assertJsonPath('data.turns', 6)
            ->assertJsonPath('data.duration_ms', 5000)
            ->assertJsonPath('data.is_me', true);

        $this->actingAsApi($aina)->getJson($this->tenantApi($tenant, 'student/wheel'))
            ->assertJsonPath('data.is_me', false);

        $this->actingAsApi($elsewhere)->getJson($this->tenantApi($tenant, 'student/wheel'))
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_an_old_spin_is_no_longer_shown(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $section = $this->createSection($this->createCourse($tenant, $lecturer));
        $session = $this->startAttendance($section, $lecturer);
        $student = $this->createMember($tenant);
        $this->enroll($section, $student);
        RandomWheelSpin::create([
            'tenant_id' => $tenant->id,
            'attendance_session_id' => $session->id,
            'section_id' => $section->id,
            'winner_id' => $student->id,
            'candidates' => [['id' => $student->id, 'name' => $student->name]],
            'winner_index' => 0,
            'turns' => 5,
            'duration_ms' => 5000,
            'spun_at' => now()->subHours(4),
        ]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/wheel'))
            ->assertJsonPath('data', null);
    }

    public function test_only_checked_in_students_can_be_put_on_a_shared_wheel(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $section = $this->createSection($this->createCourse($tenant, $lecturer));
        $session = $this->startAttendance($section, $lecturer);
        $present = $this->createMember($tenant);
        $absent = $this->createMember($tenant);
        $this->markAttendance($session, $present);
        $this->markAttendance($session, $absent, 'absent');

        $this->actingAsApi($lecturer)
            ->postJson($this->tenantApi($tenant, 'lecturer/wheel/spins'), [
                'session_id' => $session->id,
                'candidate_ids' => [$present->id, $absent->id],
                'winner_id' => $present->id,
                'turns' => 5,
                'duration_ms' => 5000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('candidate_ids');

        $this->postJson($this->tenantApi($tenant, 'lecturer/wheel/spins'), [
            'session_id' => $session->id,
            'candidate_ids' => [$present->id],
            'winner_id' => $absent->id,
            'turns' => 5,
            'duration_ms' => 5000,
        ])->assertStatus(422)->assertJsonValidationErrors('winner_id');

        $this->assertSame(0, RandomWheelSpin::count());
    }

    public function test_unrelated_lecturer_cannot_share_a_spin(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $section = $this->createSection($this->createCourse($tenant, $lecturer));
        $session = $this->startAttendance($section, $lecturer);
        $student = $this->createMember($tenant);
        $this->markAttendance($session, $student);

        $this->actingAsApi($this->createMember($tenant, 'lecturer'))
            ->postJson($this->tenantApi($tenant, 'lecturer/wheel/spins'), [
                'session_id' => $session->id,
                'candidate_ids' => [$student->id],
                'winner_id' => $student->id,
                'turns' => 5,
                'duration_ms' => 5000,
            ])
            ->assertForbidden();
    }

    public function test_the_class_phones_are_alerted_with_who_was_picked(): void
    {
        $this->configureFcm();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access']),
            self::FCM_SEND => Http::response(['name' => 'projects/lectura-test/messages/1']),
        ]);

        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $section = $this->createSection($this->createCourse($tenant, $lecturer, ['code' => 'BTG3333']));
        $session = $this->startAttendance($section, $lecturer);
        $winner = $this->createMember($tenant, 'student', ['name' => 'Aina']);
        $classmate = $this->createMember($tenant, 'student', ['name' => 'Badrul']);
        foreach ([$winner, $classmate] as $student) {
            $this->enroll($section, $student);
            $this->markAttendance($session, $student);
        }
        DeviceToken::create(['user_id' => $winner->id, 'token' => 'winner-phone', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $classmate->id, 'token' => 'classmate-phone', 'platform' => 'ios']);

        $this->actingAsApi($lecturer)
            ->postJson($this->tenantApi($tenant, 'lecturer/wheel/spins'), [
                'session_id' => $session->id,
                'candidate_ids' => [$winner->id, $classmate->id],
                'winner_id' => $winner->id,
                'turns' => 5,
                'duration_ms' => 5000,
            ])
            ->assertOk();

        $sends = Http::recorded(fn (Request $request) => $request->url() === self::FCM_SEND)
            ->mapWithKeys(fn ($pair) => [$pair[0]['message']['token'] => $pair[0]['message']]);

        $this->assertSame("You've been picked!", $sends['winner-phone']['notification']['title']);
        $this->assertSame('1', ((array) $sends['winner-phone']['data'])['is_winner']);
        $this->assertSame('random_wheel_pick', ((array) $sends['winner-phone']['data'])['kind']);
        $this->assertSame('random_wheel', $sends['winner-phone']['android']['notification']['channel_id']);
        $this->assertSame('Aina was picked in BTG3333.', $sends['classmate-phone']['notification']['body']);
        $this->assertSame('0', ((array) $sends['classmate-phone']['data'])['is_winner']);

        // Only the picked student keeps it in their notification list.
        $this->assertSame(1, $winner->notifications()->count());
        $this->assertSame(0, $classmate->notifications()->count());
    }

    private function configureFcm(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, json_encode([
            'type' => 'service_account',
            'project_id' => 'lectura-test',
            'client_email' => 'push@lectura-test.iam.gserviceaccount.com',
            'private_key' => $pem,
        ]));
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        config(['services.fcm.credentials_path' => $path]);
        Cache::flush();
    }
}
