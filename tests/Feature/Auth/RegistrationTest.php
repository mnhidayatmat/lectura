<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_links_the_privacy_policy_and_configures_livewire_once(): void
    {
        $response = $this->get('/register')->assertOk();

        $response->assertSee('href="'.route('privacy').'"', false);
        // There is no terms page, so the label is plain text rather than a dead link.
        $response->assertDontSee('href="#"', false);
        // Without the config script Livewire's bundle starts itself on top of app.js's
        // Livewire.start(), and Alpine throws "Cannot redefine property: $persist".
        $this->assertSame(1, substr_count($response->getContent(), 'window.livewireScriptConfig ='));
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        // A new user belongs to no institution yet, so registration goes to onboarding
        // (/dashboard would only bounce them there, and sits behind `verified`).
        $response->assertRedirect(route('onboarding', absolute: false));
    }
}
