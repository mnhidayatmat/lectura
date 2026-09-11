<?php

namespace Tests\Feature\Api\V1\Workspace;

use App\Models\Course;
use App\Models\StudentGroup;
use App\Models\StudentGroupFile;
use App\Models\StudentGroupFolder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

class WorkspaceFileApiTest extends ApiTestCase
{
    use CreatesWorkspaceGroups;

    private Tenant $tenant;

    private Course $course;

    private User $leader;

    private User $member;

    private StudentGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = $this->createTenant();
        $this->course = $this->createCourse($this->tenant, $this->createMember($this->tenant, 'lecturer'));
        $this->leader = $this->createMember($this->tenant, 'student');
        $this->member = $this->createMember($this->tenant, 'student');
        $this->group = $this->createGroup($this->course, [$this->leader, $this->member], $this->leader);
    }

    public function test_member_can_upload_list_and_download_a_local_file(): void
    {
        $response = $this->actingAsApi($this->member)->postJson($this->url('files'), [
            'file' => UploadedFile::fake()->create('proposal.pdf', 120, 'application/pdf'),
            'description' => 'Draft proposal',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'File uploaded.')
            ->assertJsonPath('data.name', 'proposal.pdf')
            ->assertJsonPath('data.storage', 'local')
            ->assertJsonPath('data.external_url', null)
            ->assertJsonPath('data.can_delete', true);

        $file = StudentGroupFile::firstOrFail();
        Storage::disk('local')->assertExists($file->storage_path);
        $this->assertStringEndsWith("/workspace/{$this->group->id}/files/{$file->id}/download", $response->json('data.download_url'));

        $this->getJson($this->url('files'))
            ->assertOk()
            ->assertJsonPath('data.folder', null)
            ->assertJsonCount(1, 'data.files');

        $this->get($this->url("files/{$file->id}/download"))
            ->assertOk()
            ->assertDownload('proposal.pdf');
    }

    public function test_upload_validates_file_type(): void
    {
        $this->actingAsApi($this->member)->postJson($this->url('files'), [
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->postJson($this->url('files'), [])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_folders_can_hold_files_and_must_be_empty_to_delete(): void
    {
        $folderId = $this->actingAsApi($this->member)
            ->postJson($this->url('folders'), ['name' => 'Chapter 1'])
            ->assertCreated()
            ->assertJsonPath('data.file_count', 0)
            ->json('data.id');

        $this->postJson($this->url('files'), [
            'file' => UploadedFile::fake()->create('notes.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'folder_id' => $folderId,
        ])->assertCreated()->assertJsonPath('data.folder_id', $folderId);

        $this->getJson($this->url('files'))
            ->assertOk()
            ->assertJsonCount(0, 'data.files')
            ->assertJsonPath('data.folders.0.file_count', 1)
            ->assertJsonPath('data.folders.0.can_delete', false);

        $this->getJson($this->url('files')."?folder_id={$folderId}")
            ->assertOk()
            ->assertJsonPath('data.folder.name', 'Chapter 1')
            ->assertJsonCount(1, 'data.files');

        $this->deleteJson($this->url("folders/{$folderId}"))
            ->assertStatus(422)
            ->assertJsonValidationErrors('folder');

        $fileId = StudentGroupFile::firstOrFail()->id;
        $this->deleteJson($this->url("files/{$fileId}"))->assertOk();
        $this->deleteJson($this->url("folders/{$folderId}"))->assertOk();
        $this->assertDatabaseMissing('student_group_folders', ['id' => $folderId]);
    }

    public function test_only_uploader_or_leader_can_delete_files(): void
    {
        $file = $this->storeLocalFile($this->leader);

        $this->actingAsApi($this->member)->deleteJson($this->url("files/{$file->id}"))
            ->assertForbidden()
            ->assertJsonPath('message', 'Only the uploader or group leader can delete files.');

        $memberFile = $this->storeLocalFile($this->member);

        $this->actingAsApi($this->leader)->deleteJson($this->url("files/{$memberFile->id}"))->assertOk();
        $this->assertSoftDeleted('student_group_files', ['id' => $memberFile->id]);
        Storage::disk('local')->assertMissing($memberFile->storage_path);
    }

    public function test_drive_file_download_returns_external_url(): void
    {
        $file = StudentGroupFile::create([
            'student_group_id' => $this->group->id,
            'uploaded_by' => $this->leader->id,
            'file_name' => 'budget.xlsx',
            'file_type' => 'xlsx',
            'file_size_bytes' => 2048,
            'storage_path' => '',
            'drive_file_id' => 'drive-123',
            'drive_web_link' => 'https://drive.google.com/file/d/drive-123/view',
        ]);

        $this->actingAsApi($this->member)->getJson($this->url('files'))
            ->assertOk()
            ->assertJsonPath('data.files.0.storage', 'google_drive')
            ->assertJsonPath('data.files.0.download_url', null);

        $this->getJson($this->url("files/{$file->id}/download"))
            ->assertOk()
            ->assertJsonPath('data.external_url', 'https://drive.google.com/file/d/drive-123/view');
    }

    public function test_cannot_upload_into_another_groups_folder_or_access_as_non_member(): void
    {
        $otherGroup = $this->createGroup($this->course, [$this->createMember($this->tenant, 'student')], null, 'Group B');
        $foreignFolder = StudentGroupFolder::create([
            'student_group_id' => $otherGroup->id,
            'name' => 'Theirs',
            'created_by' => $otherGroup->members()->first()->user_id,
        ]);

        $this->actingAsApi($this->member)->postJson($this->url('files'), [
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            'folder_id' => $foreignFolder->id,
        ])->assertForbidden();

        $outsider = $this->createMember($this->tenant, 'student');
        $this->actingAsApi($outsider)->getJson($this->url('files'))->assertForbidden();
    }

    private function storeLocalFile(User $uploader): StudentGroupFile
    {
        $path = "workspace/{$this->group->id}/".uniqid().'.pdf';
        Storage::disk('local')->put($path, 'content');

        return StudentGroupFile::create([
            'student_group_id' => $this->group->id,
            'uploaded_by' => $uploader->id,
            'file_name' => 'report.pdf',
            'file_type' => 'pdf',
            'file_size_bytes' => 7,
            'storage_path' => $path,
        ]);
    }

    private function url(string $path): string
    {
        return $this->tenantApi($this->tenant, "workspace/{$this->group->id}/{$path}");
    }
}
