<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\CourseFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MaterialApiTest extends LecturerApiTestCase
{
    public function test_lecturer_builds_sections_uploads_links_and_students_see_them(): void
    {
        Storage::fake('uploads');
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $student = $this->createMember($tenant, 'student');
        $course = $this->createCourse($tenant, $lecturer);
        $this->enroll($this->createSection($course), $student);
        $base = $this->tenantApi($tenant, "lecturer/courses/{$course->id}/materials");

        $week1 = $this->actingAsApi($lecturer)->postJson("$base/sections", ['title' => 'Week 1'])->assertCreated()->json('data.id');
        $week2 = $this->actingAsApi($lecturer)->postJson("$base/sections", ['title' => 'Week 2'])->assertCreated()->json('data.id');

        $fileId = $this->actingAsApi($lecturer)->post("$base/sections/$week1/files", [
            'file' => UploadedFile::fake()->create('slides.pdf', 120, 'application/pdf'),
            'title' => 'Lecture 1 slides',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Lecture 1 slides')
            ->assertJsonPath('data.type', 'file')
            ->json('data.id');

        Storage::disk('uploads')->assertExists(CourseFile::find($fileId)->storage_path);

        $this->actingAsApi($lecturer)->postJson("$base/sections/$week2/links", ['title' => 'Video', 'url' => 'not a url'])
            ->assertStatus(422);
        $this->actingAsApi($lecturer)->postJson("$base/sections/$week2/links", ['title' => 'Video', 'url' => 'https://youtu.be/abc'])
            ->assertCreated()
            ->assertJsonPath('data.external_url', 'https://youtu.be/abc');

        $this->actingAsApi($lecturer)->postJson("$base/sections/$week2/move", ['direction' => 'up'])
            ->assertOk()
            ->assertJsonPath('data.order', [$week2, $week1]);

        $this->actingAsApi($lecturer)->patchJson("$base/sections/$week2", ['is_visible' => false])->assertOk();

        // Students see the visible section with its file; the hidden one is gone.
        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/materials/courses/{$course->id}"))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Lecture 1 slides'])
            ->assertJsonMissing(['title' => 'Video']);

        $this->actingAsApi($lecturer)->getJson($base)
            ->assertOk()
            ->assertJsonCount(2, 'data.sections')
            ->assertJsonPath('data.sections.0.is_visible', false);
    }

    public function test_items_can_move_sections_but_not_leave_the_course(): void
    {
        Storage::fake('uploads');
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $other = $this->createCourse($tenant, $lecturer);
        $base = $this->tenantApi($tenant, "lecturer/courses/{$course->id}/materials");
        $a = $this->actingAsApi($lecturer)->postJson("$base/sections", ['title' => 'A'])->json('data.id');
        $b = $this->actingAsApi($lecturer)->postJson("$base/sections", ['title' => 'B'])->json('data.id');
        $foreign = $this->actingAsApi($lecturer)->postJson($this->tenantApi($tenant, "lecturer/courses/{$other->id}/materials/sections"), ['title' => 'X'])->json('data.id');
        $link = $this->actingAsApi($lecturer)->postJson("$base/sections/$a/links", ['title' => 'Notes', 'url' => 'https://example.com'])->json('data.id');

        $this->actingAsApi($lecturer)->patchJson("$base/items/$link", ['material_section_id' => $foreign])->assertStatus(422);
        $this->actingAsApi($lecturer)->patchJson("$base/items/$link", ['material_section_id' => $b, 'title' => 'Notes v2'])
            ->assertOk()
            ->assertJsonPath('data.material_section_id', $b)
            ->assertJsonPath('data.title', 'Notes v2');

        $this->actingAsApi($lecturer)->deleteJson($this->tenantApi($tenant, "lecturer/courses/{$other->id}/materials/items/$link"))->assertNotFound();

        $this->actingAsApi($lecturer)->deleteJson("$base/sections/$b")
            ->assertOk()
            ->assertJsonPath('message', 'Section and its 1 item deleted.');
        $this->assertNull(CourseFile::withTrashed()->find($link));
    }

    public function test_strangers_cannot_touch_materials(): void
    {
        $tenant = $this->createTenant();
        $course = $this->createCourse($tenant, $this->createMember($tenant, 'lecturer'));
        $stranger = $this->createMember($tenant, 'lecturer');

        $this->actingAsApi($stranger)->getJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}/materials"))->assertForbidden();
    }
}
