<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Auth\GoogleController;
use App\Models\User;
use App\Services\Auth\AppleIdentityToken;
use App\Services\Auth\AppleTokenService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

    public function test_account_deletion_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-password')]);
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/me', ['password' => 'not-my-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotSoftDeleted('users', ['id' => $user->id]);

        $this->withToken($token)->deleteJson('/api/v1/me', ['password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('message', 'Your account has been deleted.');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_google_only_account_is_deleted_by_confirming_the_email(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-1']);

        $this->actingAsApi($user)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.has_password', false);

        $this->actingAsApi($user)->deleteJson('/api/v1/me', ['confirm_email' => 'someone@else.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirm_email');

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);

        // Case-insensitive: the keyboard may capitalise the first letter.
        $this->actingAsApi($user)->deleteJson('/api/v1/me', ['confirm_email' => strtoupper($user->email)])
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
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

    public function test_apple_sign_in_creates_an_account_from_the_identity_token(): void
    {
        $token = $this->appleIdentityToken(['email' => 'Aina@Example.com', 'email_verified' => 'true']);

        $this->postJson('/api/v1/auth/apple', [
            'identity_token' => $token,
            'name' => 'nur aina',
            'device_name' => 'iPhone',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Nur Aina')
            ->assertJsonPath('data.user.memberships', []);

        $this->assertDatabaseHas('users', ['email' => 'aina@example.com', 'apple_id' => 'apple-sub-1']);
        $this->assertNotNull(User::where('email', 'aina@example.com')->sole()->email_verified_at);
    }

    public function test_apple_sign_in_links_an_account_that_already_uses_the_same_email(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createMember($tenant, 'student', ['email' => 'aina@example.com']);

        $this->postJson('/api/v1/auth/apple', [
            'identity_token' => $this->appleIdentityToken(['email' => 'aina@example.com']),
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.memberships.0.tenant.slug', $tenant->slug);

        $this->assertSame('apple-sub-1', $user->fresh()->apple_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_apple_sign_in_recognises_a_returning_user_without_an_email_claim(): void
    {
        // Apple releases the email only on the first authorization; afterwards the
        // token carries nothing but the subject.
        $user = User::factory()->create(['password' => null, 'apple_id' => 'apple-sub-1']);

        $this->postJson('/api/v1/auth/apple', ['identity_token' => $this->appleIdentityToken()])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_apple_sign_in_without_an_email_cannot_create_an_account(): void
    {
        $this->postJson('/api/v1/auth/apple', ['identity_token' => $this->appleIdentityToken()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('identity_token');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_apple_sign_in_rejects_a_token_issued_for_another_app(): void
    {
        $token = $this->appleIdentityToken(['aud' => 'com.someone.else', 'email' => 'aina@example.com']);

        $this->postJson('/api/v1/auth/apple', ['identity_token' => $token])
            ->assertStatus(422)
            ->assertJsonValidationErrors('identity_token');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_apple_sign_in_rejects_a_replayed_token_whose_nonce_does_not_match(): void
    {
        $token = $this->appleIdentityToken([
            'email' => 'aina@example.com',
            'nonce' => hash('sha256', 'the-nonce-of-an-earlier-attempt'),
        ]);

        $this->postJson('/api/v1/auth/apple', ['identity_token' => $token, 'raw_nonce' => 'this-attempt'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('identity_token');

        // The same token is accepted by the attempt that actually asked for it.
        $this->postJson('/api/v1/auth/apple', [
            'identity_token' => $token,
            'raw_nonce' => 'the-nonce-of-an-earlier-attempt',
        ])->assertOk();
    }

    /**
     * Signs an identity token with a throwaway key and publishes it as Apple's key set,
     * so the verifier runs for real without reaching appleid.apple.com.
     *
     * @param  array<string, mixed>  $claims
     */
    private function appleIdentityToken(array $claims = []): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $details = openssl_pkey_get_details($key);
        $base64url = fn (string $binary) => rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');

        Cache::put(AppleIdentityToken::KEYS_CACHE_KEY, ['keys' => [[
            'kty' => 'RSA',
            'kid' => 'test-key',
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $base64url($details['rsa']['n']),
            'e' => $base64url($details['rsa']['e']),
        ]]]);

        return JWT::encode($claims + [
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.lectura.go',
            'sub' => 'apple-sub-1',
            'iat' => time(),
            'exp' => time() + 600,
        ], $key, 'RS256', 'test-key');
    }

    public function test_apple_sign_in_keeps_the_refresh_token_that_deletion_revokes_with(): void
    {
        $publicKey = $this->configureAppleSigningKey();
        Http::fake([AppleTokenService::TOKEN_URL => Http::response(['refresh_token' => 'apple-refresh-1'])]);

        $this->postJson('/api/v1/auth/apple', [
            'identity_token' => $this->appleIdentityToken(['email' => 'aina@example.com']),
            'authorization_code' => 'sheet-code',
        ])->assertOk();

        $this->assertSame('apple-refresh-1', User::sole()->apple_refresh_token);

        Http::assertSent(function ($request) use ($publicKey) {
            // The client secret is a JWT the app signs itself; if this decodes,
            // Apple can verify it too.
            $secret = (array) JWT::decode($request['client_secret'], new Key($publicKey, 'ES256'));

            return $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'sheet-code'
                && $request['client_id'] === 'com.lectura.go'
                && $secret['iss'] === 'TEAM123456'
                && $secret['sub'] === 'com.lectura.go'
                && $secret['aud'] === 'https://appleid.apple.com';
        });
    }

    public function test_apple_sign_in_still_succeeds_when_the_code_exchange_fails(): void
    {
        $this->configureAppleSigningKey();
        Http::fake([AppleTokenService::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->postJson('/api/v1/auth/apple', [
            'identity_token' => $this->appleIdentityToken(['email' => 'aina@example.com']),
            'authorization_code' => 'stale-code',
        ])->assertOk();

        $this->assertNull(User::sole()->apple_refresh_token);
    }

    public function test_apple_sign_in_leaves_apple_alone_when_no_signing_key_is_configured(): void
    {
        Http::fake();

        $this->postJson('/api/v1/auth/apple', [
            'identity_token' => $this->appleIdentityToken(['email' => 'aina@example.com']),
            'authorization_code' => 'sheet-code',
        ])->assertOk();

        Http::assertNothingSent();
    }

    public function test_deleting_an_apple_account_revokes_the_token_with_apple(): void
    {
        $this->configureAppleSigningKey();
        Http::fake([AppleTokenService::REVOKE_URL => Http::response('', 200)]);

        $user = User::factory()->create([
            'password' => null,
            'apple_id' => 'apple-sub-1',
            'apple_refresh_token' => 'apple-refresh-1',
        ]);

        $this->actingAsApi($user)->deleteJson('/api/v1/me', ['confirm_email' => $user->email])
            ->assertOk();

        Http::assertSent(fn ($request) => $request->url() === AppleTokenService::REVOKE_URL
            && $request['token'] === 'apple-refresh-1'
            && $request['token_type_hint'] === 'refresh_token'
            && $request['client_id'] === 'com.lectura.go');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_account_deletion_goes_through_even_when_apple_refuses_to_revoke(): void
    {
        $this->configureAppleSigningKey();
        Http::fake([AppleTokenService::REVOKE_URL => Http::response('', 500)]);

        $user = User::factory()->create([
            'password' => null,
            'apple_id' => 'apple-sub-1',
            'apple_refresh_token' => 'apple-refresh-1',
        ]);

        $this->actingAsApi($user)->deleteJson('/api/v1/me', ['confirm_email' => $user->email])
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    /**
     * Configures a throwaway EC key as the .p8 Apple would have issued.
     *
     * @return string the matching public key, for checking the client secret
     */
    private function configureAppleSigningKey(): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $privateKey);

        config([
            'services.apple.team_id' => 'TEAM123456',
            'services.apple.key_id' => 'KEY1234567',
            'services.apple.private_key' => $privateKey,
            'services.apple.private_key_path' => null,
        ]);

        return openssl_pkey_get_details($key)['key'];
    }
}
