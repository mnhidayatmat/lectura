<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseMaterialSection;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

class StudentMaterialApiTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    private function addFile(Course $course, User $lecturer, CourseMaterialSection $section, array $attributes = []): CourseFile
    {
        return CourseFile::create(array_merge([
            'course_id' => $course->id,
            'uploaded_by' => $lecturer->id,
            'material_type' => 'file',
            'file_name' => 'Lecture 1.pdf',
            'file_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'storage_path' => "course-files/{$course->id}/lecture-1.pdf",
            'material_section_id' => $section->id,
        ], $attributes));
    }

    private function materialSection(Course $course, array $attributes = []): CourseMaterialSection
    {
        return CourseMaterialSection::create(array_merge([
            'course_id' => $course->id,
            'title' => 'Week 1',
            'sort_order' => 0,
            'is_visible' => true,
        ], $attributes));
    }

    public function test_lists_courses_with_material_counts(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $this->addFile($course, $lecturer, $this->materialSection($course));

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/materials'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'SKMM1203')
            ->assertJsonPath('data.0.lecturer_name', 'Dr Hidayat')
            ->assertJsonPath('data.0.materials_count', 1);
    }

    public function test_course_materials_include_visible_sections_with_links(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $week1 = $this->materialSection($course);
        $hidden = $this->materialSection($course, ['title' => 'Week 2', 'sort_order' => 1, 'is_visible' => false]);
        $this->materialSection($course, ['title' => 'Week 3', 'sort_order' => 2]);

        $file = $this->addFile($course, $lecturer, $week1);
        $this->addFile($course, $lecturer, $week1, [
            'material_type' => 'link',
            'file_name' => 'Intro video',
            'file_type' => null,
            'file_size_bytes' => null,
            'storage_path' => null,
            'url' => 'https://youtu.be/lectura',
            'sort_order' => 1,
        ]);
        $this->addFile($course, $lecturer, $hidden, ['file_name' => 'Hidden.pdf']);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/materials/courses/'.$course->id))
            ->assertOk()
            ->assertJsonPath('data.course.code', 'SKMM1203')
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonPath('data.sections.0.title', 'Week 1')
            ->assertJsonPath('data.sections.0.items_count', 2)
            ->assertJsonPath('data.sections.0.items.0.type', 'file')
            ->assertJsonPath('data.sections.0.items.0.download_url', route('api.v1.tenant.student.materials.download', [
                'tenant' => $tenant->slug,
                'course' => $course->id,
                'file' => $file->id,
            ]))
            ->assertJsonPath('data.sections.0.items.0.external_url', null)
            ->assertJsonPath('data.sections.0.items.1.type', 'link')
            ->assertJsonPath('data.sections.0.items.1.download_url', null)
            ->assertJsonPath('data.sections.0.items.1.external_url', 'https://youtu.be/lectura');
    }

    public function test_downloads_a_stored_material(): void
    {
        Storage::fake('local');
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $file = $this->addFile($course, $lecturer, $this->materialSection($course));
        Storage::disk('local')->put($file->storage_path, '%PDF-1.4');

        $this->actingAsApi($student)
            ->get($this->tenantApi($tenant, "student/materials/courses/{$course->id}/files/{$file->id}/download"))
            ->assertOk()
            ->assertDownload('Lecture 1.pdf');
    }

    public function test_student_not_enrolled_cannot_view_or_download_materials(): void
    {
        Storage::fake('local');
        [$tenant, $lecturer, , $course] = $this->enrolledStudent();
        $file = $this->addFile($course, $lecturer, $this->materialSection($course));
        Storage::disk('local')->put($file->storage_path, '%PDF-1.4');
        $stranger = $this->createMember($tenant, 'student');

        $this->actingAsApi($stranger)->getJson($this->tenantApi($tenant, 'student/materials/courses/'.$course->id))
            ->assertForbidden();

        $this->getJson($this->tenantApi($tenant, "student/materials/courses/{$course->id}/files/{$file->id}/download"))
            ->assertForbidden();
    }

    public function test_links_and_files_of_other_courses_cannot_be_downloaded(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $section = $this->materialSection($course);
        $link = $this->addFile($course, $lecturer, $section, [
            'material_type' => 'link',
            'storage_path' => null,
            'url' => 'https://example.com',
        ]);
        $otherCourse = $this->createCourse($tenant, $lecturer);
        $foreignFile = $this->addFile($otherCourse, $lecturer, $this->materialSection($otherCourse));

        $this->actingAsApi($student)
            ->getJson($this->tenantApi($tenant, "student/materials/courses/{$course->id}/files/{$link->id}/download"))
            ->assertNotFound();

        $this->getJson($this->tenantApi($tenant, "student/materials/courses/{$course->id}/files/{$foreignFile->id}/download"))
            ->assertNotFound();
    }
}
