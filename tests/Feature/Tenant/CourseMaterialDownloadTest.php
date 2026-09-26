<?php

namespace Tests\Feature\Tenant;

use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseMaterialSection;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Student\BuildsStudentFixtures;

/**
 * Web material links hand the browser a short-lived signed URL on the
 * uploads disk, so files are served by storage rather than through PHP.
 */
class CourseMaterialDownloadTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    private function addPdf(Course $course, User $lecturer): CourseFile
    {
        $section = CourseMaterialSection::create([
            'course_id' => $course->id,
            'title' => 'Week 1',
            'sort_order' => 0,
            'is_visible' => true,
        ]);

        Storage::disk('uploads')->put("course-files/{$course->id}/w01.pdf", '%PDF-1.4 test');

        return CourseFile::create([
            'course_id' => $course->id,
            'uploaded_by' => $lecturer->id,
            'material_type' => 'file',
            'file_name' => 'W01 Course Briefing.pdf',
            'file_type' => 'application/pdf',
            'file_size_bytes' => 13,
            'storage_path' => "course-files/{$course->id}/w01.pdf",
            'material_section_id' => $section->id,
        ]);
    }

    public function test_enrolled_student_views_and_downloads_through_signed_storage_urls(): void
    {
        Storage::fake('uploads');
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $file = $this->addPdf($course, $lecturer);

        foreach (['view', 'download'] as $action) {
            $response = $this->actingAs($student)
                ->get("/{$tenant->slug}/materials/course/{$course->id}/file/{$file->id}/{$action}")
                ->assertRedirect();

            $this->assertStringContainsString("course-files/{$course->id}/w01.pdf?expiration=", $response->headers->get('Location'));
        }
    }

    public function test_student_page_offers_view_and_download_for_a_pdf(): void
    {
        Storage::fake('uploads');
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $file = $this->addPdf($course, $lecturer);

        $this->actingAs($student)
            ->get("/{$tenant->slug}/my-materials/course/{$course->id}")
            ->assertOk()
            ->assertSee("/materials/course/{$course->id}/file/{$file->id}/view", false)
            ->assertSee("/materials/course/{$course->id}/file/{$file->id}/download", false);
    }

    public function test_a_file_cannot_be_fetched_through_another_course(): void
    {
        Storage::fake('uploads');
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $otherCourse = $this->createCourse($tenant, $this->createMember($tenant, 'lecturer'));
        $foreignFile = $this->addPdf($otherCourse, $lecturer);

        $this->actingAs($student)
            ->get("/{$tenant->slug}/materials/course/{$course->id}/file/{$foreignFile->id}/view")
            ->assertNotFound();
    }

    public function test_someone_outside_the_course_cannot_get_the_file(): void
    {
        Storage::fake('uploads');
        [$tenant, $lecturer, , $course] = $this->enrolledStudent();
        $file = $this->addPdf($course, $lecturer);
        $outsider = $this->createMember($tenant, 'student');

        $this->actingAs($outsider)
            ->get("/{$tenant->slug}/materials/course/{$course->id}/file/{$file->id}/download")
            ->assertForbidden();
    }
}
