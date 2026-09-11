<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Auth\GoogleController;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AuthApiTest extends ApiTestCase
{
    public function test_user_can_log_in_and_receives_a_token(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createMember($tenant, 'student', ['email' => 'aina@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'aina@example.com',
            'password' => 'password',
            'device_name' => 'iPhone',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.memberships.0.tenant.slug', $tenant->slug)
            ->assertJsonPath('data.user.memberships.0.roles', ['student']);

        $this->assertIsString($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'iPhone']);
    }

    public function test_login_rejects_an_invalid_password(): void
    {
        User::factory()->create(['email' => 'aina@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'aina@example.com', 'password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_google_only_account_cannot_log_in_with_a_password(): void
    {
        User::factory()->create(['email' => 'google@example.com', 'password' => null, 'google_id' => 'google-1']);

        $this->postJson('/api/v1/auth/login', ['email' => 'google@example.com', 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_register_and_starts_without_memberships(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'nur aina',
            'email' => 'aina@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.name', 'Nur Aina')
            ->assertJsonPath('data.user.memberships', []);

        $this->assertDatabaseHas('users', ['email' => 'aina@example.com']);
    }

    public function test_me_lists_active_memberships_with_roles_in_priority_order(): void
    {
        $tenant = $this->createTenant();
        $closed = $this->createTenant(['is_active' => false]);
        $user = $this->createMember($tenant, 'student');
        $this->addRole($tenant, $user, 'lecturer');
        $this->addRole($closed, $user, 'student');

        $this->actingAsApi($user)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonCount(1, 'data.memberships')
            ->assertJsonPath('data.memberships.0.roles', ['lecturer', 'student']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_google_code_can_be_exchanged_only_once(): void
    {
        $user = User::factory()->create();
        Cache::put(GoogleController::mobileCodeCacheKey('one-time-code'), $user->id, now()->addMinutes(2));

        $this->postJson('/api/v1/auth/google/exchange', ['code' => 'one-time-code'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->postJson('/api/v1/auth/google/exchange', ['code' => 'one-time-code'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }
}
