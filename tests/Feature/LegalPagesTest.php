<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The App Store listing links to these, so they must stay public.
 */
class LegalPagesTest extends TestCase
{
    public function test_privacy_and_support_pages_are_public(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy')->assertSee('hello@lectura.app');
        $this->get('/support')->assertOk()->assertSee('How do I delete my account?');
    }
}
