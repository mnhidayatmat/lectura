<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class PasswordResetApiTest extends ApiTestCase
{
    private function requestCode(User $user): string
    {
        $code = null;
        Notification::fake();

        $this->postJson('/api/v1/auth/password/code', ['email' => strtoupper($user->email)])->assertOk();

        Notification::assertSentTo($user, PasswordResetCode::class, function (PasswordResetCode $notification) use (&$code, $user) {
            preg_match('/\*\*(\d{6})\*\*/', implode("\n", $notification->toMail($user)->introLines), $matches);
            $code = $matches[1] ?? null;

            return $code !== null;
        });

        return $code;
    }

    public function test_an_unknown_email_gets_the_same_answer_and_no_mail(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/code', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account uses that email, a reset code is on its way.');

        Notification::assertNothingSent();
    }

    public function test_the_emailed_code_resets_the_password_signs_other_devices_out_and_signs_in(): void
    {
        $user = User::factory()->create(['password' => bcrypt('old-password')]);
        $user->createToken('old phone');
        $code = $this->requestCode($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
            'device_name' => 'iPhone',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'old phone']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'iPhone']);

        // Single use.
        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_a_code_stops_working_after_five_wrong_guesses(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/password/reset', [
                'email' => $user->email,
                'code' => $wrong,
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])->assertStatus(422)->assertJsonValidationErrors('code');
        }

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422)->assertJsonPath('errors.code.0', 'This code has expired. Ask for a new one.');
    }
}
