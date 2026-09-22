<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\AttendanceAlert;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PushNotificationApiTest extends ApiTestCase
{
    private const FCM_SEND = 'https://fcm.googleapis.com/v1/projects/lectura-test/messages:send';

    public function test_app_registers_its_device_token(): void
    {
        $user = User::factory()->create();

        $this->actingAsApi($user)
            ->postJson('/api/v1/devices', ['token' => 'fcm-token-1', 'platform' => 'android'])
            ->assertOk()
            ->assertJsonPath('data.token', 'fcm-token-1');

        $this->assertDatabaseHas('device_tokens', ['user_id' => $user->id, 'token' => 'fcm-token-1', 'platform' => 'android']);
    }

    public function test_a_token_moves_to_whoever_signed_in_last_on_the_device(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAsApi($first)->postJson('/api/v1/devices', ['token' => 'shared', 'platform' => 'ios'])->assertOk();
        $this->actingAsApi($second)->postJson('/api/v1/devices', ['token' => 'shared', 'platform' => 'ios'])->assertOk();

        $this->assertSame(1, DeviceToken::count());
        $this->assertDatabaseHas('device_tokens', ['user_id' => $second->id, 'token' => 'shared']);
    }

    public function test_registration_is_validated(): void
    {
        $this->actingAsApi(User::factory()->create())
            ->postJson('/api/v1/devices', ['token' => 'x', 'platform' => 'windows'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('platform');
    }

    public function test_app_can_remove_only_its_own_token(): void
    {
        $owner = User::factory()->create();
        DeviceToken::create(['user_id' => $owner->id, 'token' => 'mine', 'platform' => 'android']);

        $this->actingAsApi(User::factory()->create())
            ->deleteJson('/api/v1/devices', ['token' => 'mine'])
            ->assertOk();
        $this->assertDatabaseHas('device_tokens', ['token' => 'mine']);

        $this->actingAsApi($owner)->deleteJson('/api/v1/devices', ['token' => 'mine'])->assertOk();
        $this->assertDatabaseMissing('device_tokens', ['token' => 'mine']);
    }

    public function test_signing_out_stops_pushes_to_that_device(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Lectura Go (android)')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/devices', ['token' => 'fcm-token-1', 'platform' => 'android'])->assertOk();
        $this->assertDatabaseHas('device_tokens', ['token' => 'fcm-token-1']);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-token-1']);
    }

    public function test_lecturer_notification_is_pushed_to_each_device(): void
    {
        $this->configureFcm();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access', 'expires_in' => 3600]),
            self::FCM_SEND => Http::response(['name' => 'projects/lectura-test/messages/1']),
        ]);

        $lecturer = User::factory()->create();
        $student = User::factory()->create(['name' => 'Aina']);
        DeviceToken::create(['user_id' => $lecturer->id, 'token' => 'phone', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $lecturer->id, 'token' => 'tablet', 'platform' => 'ios']);

        $lecturer->notify(new AttendanceAlert($student, 'SKMM1203', 3));

        $notificationId = $lecturer->notifications()->firstOrFail()->id;
        $sends = Http::recorded(fn (Request $request) => $request->url() === self::FCM_SEND);
        $this->assertCount(2, $sends);

        [$request] = $sends->first();
        $this->assertSame('Bearer google-access', $request->header('Authorization')[0]);
        $this->assertSame('phone', $request['message']['token']);
        $this->assertSame('Absence Alert', $request['message']['notification']['title']);
        $this->assertSame('Aina has missed 3 sessions in SKMM1203', $request['message']['notification']['body']);
        $this->assertSame(['notification_id' => $notificationId, 'kind' => 'attendance_alert'], (array) $request['message']['data']);
    }

    public function test_tokens_fcm_no_longer_recognises_are_dropped(): void
    {
        $this->configureFcm();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access']),
            self::FCM_SEND => Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);

        $lecturer = User::factory()->create();
        DeviceToken::create(['user_id' => $lecturer->id, 'token' => 'uninstalled', 'platform' => 'android']);

        $lecturer->notify(new AttendanceAlert(User::factory()->create(), 'SKMM1203', 3));

        $this->assertDatabaseMissing('device_tokens', ['token' => 'uninstalled']);
    }

    public function test_a_failing_push_still_stores_the_notification(): void
    {
        $this->configureFcm();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access']),
            self::FCM_SEND => Http::response(['error' => ['code' => 500]], 500),
        ]);

        $lecturer = User::factory()->create();
        DeviceToken::create(['user_id' => $lecturer->id, 'token' => 'phone', 'platform' => 'android']);

        $lecturer->notify(new AttendanceAlert(User::factory()->create(), 'SKMM1203', 3));

        $this->assertSame(1, $lecturer->notifications()->count());
        $this->assertDatabaseHas('device_tokens', ['token' => 'phone']);
    }

    public function test_nothing_is_pushed_without_a_service_account(): void
    {
        config(['services.fcm.credentials_path' => null]);
        Http::fake();

        $lecturer = User::factory()->create();
        DeviceToken::create(['user_id' => $lecturer->id, 'token' => 'phone', 'platform' => 'android']);

        $lecturer->notify(new AttendanceAlert(User::factory()->create(), 'SKMM1203', 3));

        Http::assertNothingSent();
        $this->assertSame(1, $lecturer->notifications()->count());
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
