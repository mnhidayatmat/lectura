<?php

namespace Tests\Feature\Tenant;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * The web Portfolio page: uploads, the week-by-week timeline and the generated PDF.
 */
class PortfolioWebTest extends ApiTestCase
{
    public function test_course_page_shows_the_timeline_and_the_pdf_downloads(): void
    {
        Storage::fake('media');
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)->post("/{$tenant->slug}/portfolio/course/{$course->id}/store", [
            'photo' => UploadedFile::fake()->image('a.jpg', 800, 600),
            'category' => 'lab',
            'caption' => 'Flow rig demo',
            'week_number' => 4,
        ])->assertRedirect();

        $this->actingAs($lecturer)->get("/{$tenant->slug}/portfolio/course/{$course->id}")
            ->assertOk()
            ->assertSee('Timeline')
            ->assertSee('Week 4')
            ->assertSee('Flow rig demo')
            ->assertSee(route('tenant.portfolio.pdf', [$tenant->slug, $course]), false);

        $this->actingAs($lecturer)->get("/{$tenant->slug}/portfolio/course/{$course->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
