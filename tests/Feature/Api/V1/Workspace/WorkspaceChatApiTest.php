<?php

namespace Tests\Feature\Api\V1\Workspace;

use App\Events\GroupMessageSent;
use App\Models\StudentGroup;
use App\Models\StudentGroupPost;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Api\V1\ApiTestCase;

class WorkspaceChatApiTest extends ApiTestCase
{
    use CreatesWorkspaceGroups;

    private Tenant $tenant;

    private User $author;

    private User $peer;

    private StudentGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $course = $this->createCourse($this->tenant, $this->createMember($this->tenant, 'lecturer'));
        $this->author = $this->createMember($this->tenant, 'student', ['name' => 'Aina Rahman']);
        $this->peer = $this->createMember($this->tenant, 'student');
        $this->group = $this->createGroup($course, [$this->author, $this->peer], $this->author);
    }

    public function test_member_can_post_and_list_messages(): void
    {
        Event::fake([GroupMessageSent::class]);

        $this->actingAsApi($this->author)
            ->postJson($this->chatUrl(), ['body' => 'Meeting at 3pm'])
            ->assertCreated()
            ->assertJsonPath('message', 'Message sent.')
            ->assertJsonPath('data.body', 'Meeting at 3pm')
            ->assertJsonPath('data.user.initial', 'A')
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonPath('data.is_edited', false);

        Event::assertDispatched(GroupMessageSent::class);

        $this->actingAsApi($this->peer)->getJson($this->chatUrl())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_mine', false)
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_after_id_and_before_id_page_through_messages(): void
    {
        $ids = collect(range(1, 55))->map(fn (int $i) => StudentGroupPost::create([
            'student_group_id' => $this->group->id,
            'user_id' => $this->peer->id,
            'body' => "Message {$i}",
        ])->id);

        $newest = $this->actingAsApi($this->author)->getJson($this->chatUrl())
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('data.49.body', 'Message 55');

        $oldestShown = $newest->json('data.0.id');

        $this->getJson($this->chatUrl()."?before_id={$oldestShown}")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('data.0.body', 'Message 1');

        $this->getJson($this->chatUrl().'?after_id='.$ids[52])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.body', 'Message 55');
    }

    public function test_body_is_required(): void
    {
        $this->actingAsApi($this->author)->postJson($this->chatUrl(), ['body' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }

    public function test_non_member_cannot_read_or_post(): void
    {
        $outsider = $this->createMember($this->tenant, 'student');

        $this->actingAsApi($outsider)->getJson($this->chatUrl())->assertForbidden();
        $this->postJson($this->chatUrl(), ['body' => 'Hello'])->assertForbidden();
    }

    public function test_only_the_author_can_edit_or_delete_a_message(): void
    {
        $message = StudentGroupPost::create([
            'student_group_id' => $this->group->id,
            'user_id' => $this->author->id,
            'body' => 'Original',
        ]);

        $this->actingAsApi($this->peer)
            ->patchJson($this->chatUrl()."/{$message->id}", ['body' => 'Hijacked'])
            ->assertForbidden();
        $this->deleteJson($this->chatUrl()."/{$message->id}")->assertForbidden();

        $this->actingAsApi($this->author)
            ->patchJson($this->chatUrl()."/{$message->id}", ['body' => 'Edited'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Edited')
            ->assertJsonPath('data.is_edited', true);

        $this->deleteJson($this->chatUrl()."/{$message->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('student_group_posts', ['id' => $message->id]);
    }

    private function chatUrl(): string
    {
        return $this->tenantApi($this->tenant, "workspace/{$this->group->id}/chat");
    }
}
