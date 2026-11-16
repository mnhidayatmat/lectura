<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentSubmissionFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Student\BuildsStudentFixtures;

/**
 * Lecturers download every submission, or the ones they tick, as one zip with
 * a folder per student.
 */
class AssessmentSubmissionBulkDownloadTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    public function test_downloads_all_or_selected_submissions_as_a_zip(): void
    {
        Storage::fake('uploads');
        [$tenant, $lecturer, $alice, $course, $section] = $this->enrolledStudent();
        $alice->update(['name' => 'Alice']);
        $bob = $this->createMember($tenant, 'student', ['name' => 'Bob']);
        $this->enroll($section, $bob);
        $outsider = $this->createMember($tenant, 'student', ['name' => 'Outsider']);
        $assessment = $this->createAssessment($course, ['title' => 'Lab 1', 'requires_submission' => true]);

        $aliceSub = $this->submit($assessment, $alice, ['report.pdf', 'report.pdf']);
        $this->submit($assessment, $bob, ['bob.docx']);
        $this->submit($assessment, $outsider, ['nope.pdf']);

        $url = "/{$tenant->slug}/courses/{$course->id}/assessments/{$assessment->id}/submissions/download";

        $all = $this->actingAs($lecturer)->post($url, ['all' => 1])->assertOk();
        $this->assertSame(
            ['Alice/report (2).pdf', 'Alice/report.pdf', 'Bob/bob.docx'],
            $this->zipEntries($all->baseResponse->getFile()->getPathname()),
        );

        $some = $this->actingAs($lecturer)->post($url, ['submission_ids' => [$aliceSub->id]])->assertOk();
        $this->assertSame(
            ['Alice/report (2).pdf', 'Alice/report.pdf'],
            $this->zipEntries($some->baseResponse->getFile()->getPathname()),
        );

        $this->actingAs($alice)->post($url, ['all' => 1])->assertForbidden();
    }

    public function test_submissions_page_offers_bulk_download_and_row_links(): void
    {
        Storage::fake('uploads');
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $assessment = $this->createAssessment($course, ['requires_submission' => true]);
        $sub = $this->submit($assessment, $student, ['a.pdf']);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$course->id}/assessments/{$assessment->id}/submissions")
            ->assertOk()
            ->assertSee('Download all (1)')
            ->assertSee("/submissions/{$sub->id}", false);
    }

    private function submit(Assessment $assessment, User $student, array $names): AssessmentSubmission
    {
        $submission = AssessmentSubmission::create([
            'tenant_id' => $assessment->tenant_id,
            'assessment_id' => $assessment->id,
            'user_id' => $student->id,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        foreach ($names as $i => $name) {
            $path = "assessment_submissions/{$assessment->id}/{$student->id}-{$i}-{$name}";
            Storage::disk('uploads')->put($path, "content of {$name}");
            AssessmentSubmissionFile::create([
                'assessment_submission_id' => $submission->id,
                'file_name' => $name,
                'file_type' => 'application/octet-stream',
                'file_size_bytes' => 10,
                'storage_path' => $path,
            ]);
        }

        return $submission;
    }

    private function zipEntries(string $path): array
    {
        $zip = new \ZipArchive;
        $zip->open($path);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        sort($names);

        return $names;
    }
}
