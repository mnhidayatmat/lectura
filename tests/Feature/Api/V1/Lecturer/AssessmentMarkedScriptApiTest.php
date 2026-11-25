<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentSubmissionFile;
use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

class AssessmentMarkedScriptApiTest extends LecturerApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private Course $course;

    private User $student;

    private Assessment $assessment;

    private AssessmentSubmission $submission;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');

        $this->tenant = $this->createTenant();
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->course = $this->createCourse($this->tenant, $this->lecturer);
        $section = $this->createSection($this->course);
        $this->student = $this->createMember($this->tenant, 'student');
        $this->enroll($section, $this->student);

        $this->assessment = Assessment::create([
            'tenant_id' => $this->tenant->id,
            'course_id' => $this->course->id,
            'title' => 'Lab 1',
            'type' => 'assignment',
            'weightage' => 10,
            'total_marks' => 100,
            'status' => 'active',
            'requires_submission' => true,
        ]);
        $this->submission = AssessmentSubmission::create([
            'tenant_id' => $this->tenant->id,
            'assessment_id' => $this->assessment->id,
            'user_id' => $this->student->id,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
    }

    private function addFile(string $name, string $type, array $attributes = []): AssessmentSubmissionFile
    {
        $path = "assessment_submissions/{$this->assessment->id}/".md5($name.microtime()).'.'.pathinfo($name, PATHINFO_EXTENSION);
        Storage::disk('uploads')->put($path, "original {$name}");

        return AssessmentSubmissionFile::create(array_merge([
            'assessment_submission_id' => $this->submission->id,
            'file_name' => $name,
            'file_type' => $type,
            'file_size_bytes' => 100,
            'storage_path' => $path,
        ], $attributes));
    }

    private function upload(array $data = [], ?User $as = null): TestResponse
    {
        return $this->actingAsApi($as ?? $this->lecturer)->post(
            $this->tenantApi($this->tenant, "lecturer/assessments/{$this->assessment->id}/submissions/{$this->submission->id}/marked-script"),
            array_merge(['file' => UploadedFile::fake()->create('marked.pdf', 200, 'application/pdf')], $data),
            ['Accept' => 'application/json'],
        );
    }

    public function test_a_single_pdf_is_replaced_by_the_marked_script(): void
    {
        $file = $this->addFile('Lab1 Report.pdf', 'application/pdf', [
            'graded_file_path' => 'assessment_submissions/graded/x.pdf',
            'graded_at' => now(),
            'drive_file_id' => 'drive-1',
        ]);
        $originalPath = $file->storage_path;

        $this->upload()
            ->assertOk()
            ->assertJsonPath('data.id', $this->submission->id)
            ->assertJsonCount(1, 'data.files')
            ->assertJsonPath('data.files.0.id', $file->id)
            ->assertJsonPath('data.files.0.name', 'Lab1 Report_marked.pdf')
            ->assertJsonPath('data.files.0.mime_type', 'application/pdf')
            ->assertJsonPath('data.files.0.is_stamped', false);

        $file->refresh();
        $this->assertStringStartsWith("assessment_submissions/{$this->assessment->id}/marked/{$this->submission->id}-", $file->storage_path);
        $this->assertNull($file->graded_file_path);
        $this->assertNull($file->graded_at);
        $this->assertNull($file->drive_file_id);
        Storage::disk('uploads')->assertExists([$file->storage_path, $originalPath]);

        $original = $this->submission->refresh()->original_files;
        $this->assertCount(1, $original);
        $this->assertSame($originalPath, $original[0]['storage_path']);
        $this->assertSame('Lab1 Report.pdf', $original[0]['file_name']);
        $this->assertSame('drive-1', $original[0]['drive_file_id']);
        $this->assertSame('assessment_submissions/graded/x.pdf', $original[0]['graded_file_path']);
    }

    public function test_pdf_and_image_are_replaced_and_the_video_is_kept(): void
    {
        $pdf = $this->addFile('report.pdf', 'application/pdf');
        $png = $this->addFile('sketch.png', 'image/png');
        $video = $this->addFile('demo.mp4', 'video/mp4');

        $this->upload()
            ->assertOk()
            ->assertJsonCount(2, 'data.files');

        $this->assertModelExists($pdf);
        $this->assertModelMissing($png);
        $this->assertSame('report_marked.pdf', $pdf->refresh()->file_name);
        $this->assertSame($video->storage_path, $video->refresh()->storage_path);
        $this->assertSame('demo.mp4', $video->file_name);

        $original = $this->submission->refresh()->original_files;
        $this->assertSame([$pdf->id, $png->id], array_column($original, 'id'));
        Storage::disk('uploads')->assertExists(array_column($original, 'storage_path'));
        Storage::disk('uploads')->assertExists($video->storage_path);
    }

    public function test_reupload_keeps_the_originals_and_removes_the_previous_marked_copy(): void
    {
        $file = $this->addFile('report.pdf', 'application/pdf');
        $originalPath = $file->storage_path;

        $this->upload()->assertOk();
        $firstMarked = $file->refresh()->storage_path;
        $firstOriginals = $this->submission->refresh()->original_files;

        $this->upload()->assertOk();
        $file->refresh();

        $this->assertNotSame($firstMarked, $file->storage_path);
        $this->assertSame('report_marked.pdf', $file->file_name);
        $this->assertSame($firstOriginals, $this->submission->refresh()->original_files);
        Storage::disk('uploads')->assertMissing($firstMarked);
        Storage::disk('uploads')->assertExists([$originalPath, $file->storage_path]);
    }

    public function test_explicit_file_ids_are_honoured_and_foreign_ids_are_refused(): void
    {
        $pdf = $this->addFile('report.pdf', 'application/pdf');
        $png = $this->addFile('sketch.png', 'image/png');
        $other = AssessmentSubmission::create([
            'tenant_id' => $this->tenant->id,
            'assessment_id' => $this->assessment->id,
            'user_id' => $this->createMember($this->tenant, 'student')->id,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
        $foreign = AssessmentSubmissionFile::create([
            'assessment_submission_id' => $other->id,
            'file_name' => 'x.pdf',
            'file_type' => 'application/pdf',
            'file_size_bytes' => 10,
            'storage_path' => 'assessment_submissions/x.pdf',
        ]);

        $this->upload(['replace_file_ids' => [$pdf->id, $foreign->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('replace_file_ids');
        $this->assertNull($this->submission->refresh()->original_files);
        $this->assertSame('report.pdf', $pdf->refresh()->file_name);
        Storage::disk('uploads')->assertDirectoryEmpty("assessment_submissions/{$this->assessment->id}/marked");

        $this->upload(['replace_file_ids' => [$png->id]])
            ->assertOk()
            ->assertJsonCount(2, 'data.files');
        $this->assertSame('sketch_marked.pdf', $png->refresh()->file_name);
        $this->assertSame('report.pdf', $pdf->refresh()->file_name);
    }

    public function test_nothing_to_replace_and_non_pdf_uploads_are_refused(): void
    {
        $this->addFile('demo.mp4', 'video/mp4');

        $this->upload()
            ->assertStatus(422)
            ->assertJsonValidationErrors('replace_file_ids');

        $this->addFile('report.pdf', 'application/pdf');

        $this->upload(['file' => UploadedFile::fake()->image('marked.png')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->upload(['file' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_students_colleagues_and_other_tenants_are_refused(): void
    {
        $file = $this->addFile('report.pdf', 'application/pdf');
        $colleague = $this->createMember($this->tenant, 'lecturer');

        $this->upload([], $this->student)->assertForbidden();
        $this->upload([], $colleague)->assertForbidden();

        $otherTenant = $this->createTenant();
        $otherLecturer = $this->createMember($otherTenant, 'lecturer');
        $this->actingAsApi($otherLecturer)->post(
            $this->tenantApi($otherTenant, "lecturer/assessments/{$this->assessment->id}/submissions/{$this->submission->id}/marked-script"),
            ['file' => UploadedFile::fake()->create('marked.pdf', 10, 'application/pdf')],
            ['Accept' => 'application/json'],
        )->assertNotFound();

        $otherAssessment = Assessment::create([
            'tenant_id' => $this->tenant->id, 'course_id' => $this->course->id, 'title' => 'Lab 2',
            'type' => 'assignment', 'weightage' => 10, 'total_marks' => 100, 'status' => 'active',
        ]);
        $this->actingAsApi($this->lecturer)->post(
            $this->tenantApi($this->tenant, "lecturer/assessments/{$otherAssessment->id}/submissions/{$this->submission->id}/marked-script"),
            ['file' => UploadedFile::fake()->create('marked.pdf', 10, 'application/pdf')],
            ['Accept' => 'application/json'],
        )->assertNotFound();

        $this->assertSame('report.pdf', $file->refresh()->file_name);
        $this->assertNull($this->submission->refresh()->original_files);
    }
}
