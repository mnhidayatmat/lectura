<?php

namespace Tests\Feature\Tenant;

use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Student\BuildsStudentFixtures;

/**
 * QR check-in is for Lectura Go and the installed web app only: in a browser
 * tab the scanner and every link to it are hidden (`pwa-only`) and the scan
 * page sends the student to the app (`web-only`).
 */
class StudentScanPageTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    public function test_the_scanner_is_marked_installed_app_only(): void
    {
        [$tenant, , $student] = $this->enrolledStudent();

        $this->actingAs($student)->get("/{$tenant->slug}/scan")
            ->assertOk()
            ->assertSee("classList.add('is-pwa')", false)
            ->assertSeeInOrder(['class="web-only', 'Check in from the app', 'class="pwa-only', 'Start Camera'], false);
    }

    public function test_scan_links_only_show_in_the_installed_app(): void
    {
        [$tenant, , $student] = $this->enrolledStudent();

        $html = $this->actingAs($student)->get("/{$tenant->slug}/dashboard")->assertOk()->getContent();

        preg_match_all('/<a href="[^"]*\/scan"\s+class="([^"]*)"/', $html, $links);
        $this->assertNotEmpty($links[1]);
        foreach ($links[1] as $classes) {
            $this->assertStringStartsWith('pwa-only', $classes);
        }
    }
}
