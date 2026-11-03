<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\AcademicTerm;
use App\Models\PortfolioPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PortfolioApiTest extends LecturerApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    public function test_photo_taken_in_a_class_session_lands_in_that_week_of_the_timeline(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $section = $this->createSection($course, [], [$lecturer]);
        $session = $this->startAttendance($section, $lecturer, ['session_type' => 'lab', 'week_number' => 5]);
        $base = $this->tenantApi($tenant, "lecturer/courses/{$course->id}/portfolio");

        $response = $this->actingAsApi($lecturer)->post($base, [
            'photo' => UploadedFile::fake()->image('class.jpg', 1200, 900),
            'attendance_session_id' => $session->id,
            'caption' => 'Students testing the pump rig',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.week_number', 5)
            ->assertJsonPath('data.category', 'lab')
            ->assertJsonPath('data.section.id', $section->id)
            ->assertJsonPath('data.attendance_session.id', $session->id)
            ->assertJsonPath('data.caption', 'Students testing the pump rig');

        $photo = PortfolioPhoto::find($response->json('data.id'));
        Storage::disk('media')->assertExists($photo->file_path);
        Storage::disk('media')->assertExists($photo->thumbnail_path);

        // A second photo without a session and an explicit week goes in its own group.
        $this->actingAsApi($lecturer)->post($base, [
            'photo' => UploadedFile::fake()->image('board.png', 800, 600),
            'category' => 'lecture',
            'week_number' => 2,
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->actingAsApi($lecturer)->getJson($base)
            ->assertOk()
            ->assertJsonPath('data.photo_count', 2)
            ->assertJsonPath('data.timeline.0.week', 2)
            ->assertJsonPath('data.timeline.1.week', 5)
            ->assertJsonPath('data.timeline.1.label', 'Week 5')
            ->assertJsonPath('data.timeline.1.photos.0.caption', 'Students testing the pump rig')
            ->assertJsonPath('data.sections.0.id', $section->id);

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/portfolio'))
            ->assertOk()
            ->assertJsonPath('data.0.course.id', $course->id)
            ->assertJsonPath('data.0.photo_count', 2)
            ->assertJsonPath('data.0.week_count', 2);
    }

    public function test_week_comes_from_the_semester_start_when_not_given(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $term = AcademicTerm::create(['tenant_id' => $tenant->id, 'name' => 'Sem 1', 'code' => 'S1', 'start_date' => '2026-10-05', 'end_date' => '2027-02-01']);
        $course = $this->createCourse($tenant, $lecturer, ['academic_term_id' => $term->id]);

        $this->actingAsApi($lecturer)->post($this->tenantApi($tenant, "lecturer/courses/{$course->id}/portfolio"), [
            'photo' => UploadedFile::fake()->image('a.jpg'),
            'taken_at' => '2026-10-20T10:00:00+08:00',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.week_number', 3)
            ->assertJsonPath('data.category', 'other');
    }

    public function test_session_and_section_must_belong_to_the_course(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $other = $this->createCourse($tenant, $lecturer);
        $otherSection = $this->createSection($other, [], [$lecturer]);
        $session = $this->startAttendance($otherSection, $lecturer);
        $url = $this->tenantApi($tenant, "lecturer/courses/{$course->id}/portfolio");

        $this->actingAsApi($lecturer)->post($url, [
            'photo' => UploadedFile::fake()->image('a.jpg'),
            'attendance_session_id' => $session->id,
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attendance_session_id');

        $this->actingAsApi($lecturer)->post($url, [
            'photo' => UploadedFile::fake()->image('a.jpg'),
            'section_id' => $otherSection->id,
        ], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_only_the_owner_edits_or_deletes_and_strangers_see_nothing(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $colleague = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $id = $this->actingAsApi($lecturer)->post($this->tenantApi($tenant, "lecturer/courses/{$course->id}/portfolio"), [
            'photo' => UploadedFile::fake()->image('a.jpg'),
            'category' => 'lecture',
        ], ['Accept' => 'application/json'])->json('data.id');

        $this->actingAsApi($colleague)->getJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}/portfolio"))->assertForbidden();
        $this->actingAsApi($colleague)->patchJson($this->tenantApi($tenant, "lecturer/portfolio/$id"), ['caption' => 'x'])->assertForbidden();
        $this->actingAsApi($colleague)->deleteJson($this->tenantApi($tenant, "lecturer/portfolio/$id"))->assertForbidden();

        $this->actingAsApi($lecturer)->patchJson($this->tenantApi($tenant, "lecturer/portfolio/$id"), ['caption' => 'Week 1 intro', 'week_number' => 1, 'category' => 'workshop'])
            ->assertOk()
            ->assertJsonPath('data.caption', 'Week 1 intro')
            ->assertJsonPath('data.category_label', 'Workshop');

        $path = PortfolioPhoto::find($id)->file_path;
        $this->actingAsApi($lecturer)->deleteJson($this->tenantApi($tenant, "lecturer/portfolio/$id"))->assertOk();
        $this->assertNull(PortfolioPhoto::find($id));
        Storage::disk('media')->assertMissing($path);
    }

    public function test_pdf_portfolio_downloads(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer, ['code' => 'BTG 3333']);
        $this->actingAsApi($lecturer)->post($this->tenantApi($tenant, "lecturer/courses/{$course->id}/portfolio"), [
            'photo' => UploadedFile::fake()->image('a.jpg', 900, 600),
            'category' => 'lecture',
            'week_number' => 1,
            'caption' => 'Opening lecture',
        ], ['Accept' => 'application/json'])->assertCreated();

        $response = $this->actingAsApi($lecturer)->get($this->tenantApi($tenant, "lecturer/courses/{$course->id}/portfolio/pdf"))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString('BTG_3333_teaching_portfolio.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_students_cannot_use_the_portfolio(): void
    {
        $tenant = $this->createTenant();
        $student = $this->createMember($tenant, 'student');

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'lecturer/portfolio'))->assertForbidden();
    }
}
