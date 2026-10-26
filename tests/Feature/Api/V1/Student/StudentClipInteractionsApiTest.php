<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\ClipComment;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeClip;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\Api\V1\ApiTestCase;

class StudentClipInteractionsApiTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    private function episode(Course $course, int $number, array $attributes = []): Episode
    {
        $series = CourseSeries::firstOrCreate(
            ['course_id' => $course->id],
            ['tenant_id' => $course->tenant_id, 'title' => 'Titis: A Piping Story'],
        );

        return Episode::create(array_merge([
            'tenant_id' => $course->tenant_id,
            'course_series_id' => $series->id,
            'course_id' => $course->id,
            'episode_number' => $number,
            'title' => "Episode {$number}",
            'status' => Episode::STATUS_PUBLISHED,
            'video_disk' => 'local',
            'video_path' => "episodes/{$course->id}/ep{$number}.mp4",
        ], $attributes));
    }

    private function clip(Episode $episode, int $number = 1): EpisodeClip
    {
        return EpisodeClip::create([
            'tenant_id' => $episode->tenant_id,
            'episode_id' => $episode->id,
            'course_id' => $episode->course_id,
            'clip_number' => $number,
            'title' => "Clip {$number}",
            'video_disk' => 'local',
            'video_path' => "episodes/{$episode->course_id}/clips/{$episode->id}-{$number}.mp4",
        ]);
    }

    private function api(Tenant $tenant, EpisodeClip $clip, string $path = ''): string
    {
        return $this->tenantApi($tenant, "student/watch/clips/{$clip->id}".($path === '' ? '' : "/{$path}"));
    }

    /** @return array{Tenant, User, User, Course, EpisodeClip, User} */
    private function setUpClip(): array
    {
        [$tenant, $lecturer, $student, $course, $section] = $this->enrolledStudent();
        $classmate = $this->createMember($tenant, 'student', ['name' => 'Siti Aminah']);
        $this->enroll($section, $classmate);

        return [$tenant, $lecturer, $student, $course, $this->clip($this->episode($course, 1)), $classmate];
    }

    public function test_a_view_counts_once_per_student_every_thirty_minutes(): void
    {
        [$tenant, , $student, , $clip, $classmate] = $this->setUpClip();

        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'view'))
            ->assertOk()->assertJsonPath('data.views_count', 1);
        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'view'))
            ->assertJsonPath('data.views_count', 1);
        $this->actingAsApi($classmate)->postJson($this->api($tenant, $clip, 'view'))
            ->assertJsonPath('data.views_count', 2);

        $this->travel(31)->minutes();
        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'view'))
            ->assertJsonPath('data.views_count', 3);

        $this->assertDatabaseHas('episode_clip_views', ['episode_clip_id' => $clip->id, 'user_id' => $student->id, 'plays' => 2]);
    }

    public function test_a_clip_played_to_its_end_is_watched_and_no_longer_new(): void
    {
        [$tenant, , $student, , $clip, $classmate] = $this->setUpClip();
        $list = $this->tenantApi($tenant, 'student/watch/clips');

        $this->actingAsApi($student)->getJson($list)
            ->assertJsonPath('data.0.watched', false)
            ->assertJsonPath('data.0.is_new', true);

        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'view'))
            ->assertJsonPath('data.watched', false);
        // Completing inside the view window marks it watched without counting another view.
        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'view'), ['completed' => true])
            ->assertJson(['data' => ['views_count' => 1, 'watched' => true]]);

        $this->actingAsApi($student)->getJson($list)
            ->assertJsonPath('data.0.watched', true)
            ->assertJsonPath('data.0.is_new', false);
        $this->actingAsApi($classmate)->getJson($list)
            ->assertJsonPath('data.0.watched', false);

        $this->travel(15)->days();
        $this->actingAsApi($classmate)->getJson($list)
            ->assertJsonPath('data.0.is_new', false);
    }

    public function test_like_and_save_are_idempotent_and_show_in_the_list(): void
    {
        [$tenant, , $student, , $clip, $classmate] = $this->setUpClip();

        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'like'))
            ->assertOk()->assertJson(['data' => ['liked' => true, 'likes_count' => 1]]);
        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'like'))
            ->assertJson(['data' => ['liked' => true, 'likes_count' => 1]]);
        $this->actingAsApi($classmate)->postJson($this->api($tenant, $clip, 'like'))
            ->assertJson(['data' => ['likes_count' => 2]]);
        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'save'))
            ->assertJson(['data' => ['saved' => true, 'saves_count' => 1]]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/watch/clips'))
            ->assertJsonPath('data.0.liked', true)
            ->assertJsonPath('data.0.saved', true)
            ->assertJsonPath('data.0.likes_count', 2)
            ->assertJsonPath('data.0.saves_count', 1)
            ->assertJsonPath('data.0.views_count', 0)
            ->assertJsonPath('data.0.comments_count', 0);
        $this->actingAsApi($classmate)->getJson($this->tenantApi($tenant, 'student/watch/clips'))
            ->assertJsonPath('data.0.liked', true)
            ->assertJsonPath('data.0.saved', false);

        $this->actingAsApi($student)->deleteJson($this->api($tenant, $clip, 'like'))
            ->assertJson(['data' => ['liked' => false, 'likes_count' => 1]]);
        $this->actingAsApi($student)->deleteJson($this->api($tenant, $clip, 'like'))
            ->assertJson(['data' => ['liked' => false, 'likes_count' => 1]]);
        $this->actingAsApi($student)->deleteJson($this->api($tenant, $clip, 'save'))
            ->assertJson(['data' => ['saved' => false, 'saves_count' => 0]]);
    }

    public function test_saved_filter_lists_newest_save_first(): void
    {
        [$tenant, , $student, $course, $first] = $this->setUpClip();
        $second = $this->clip($first->episode, 2);
        $this->clip($first->episode, 3);

        $this->actingAsApi($student)->postJson($this->api($tenant, $first, 'save'))->assertOk();
        $this->travel(1)->minutes();
        $this->actingAsApi($student)->postJson($this->api($tenant, $second, 'save'))->assertOk();

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/watch/clips?saved=1&course_id={$course->id}"))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);
    }

    public function test_comments_list_most_liked_then_newest_with_reply_counts(): void
    {
        [$tenant, $lecturer, $student, , $clip, $classmate] = $this->setUpClip();
        $student->update(['name' => 'Ali Bin Abu']);

        $old = $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'comments'), ['body' => '  So the pump is the heart?  '])
            ->assertCreated()
            ->assertJsonPath('data.body', 'So the pump is the heart?')
            ->assertJsonPath('data.author.initials', 'AB')
            ->assertJsonPath('data.author.is_lecturer', false)
            ->assertJsonPath('data.can_delete', true)
            ->json('data.id');
        $this->travel(1)->minutes();
        $newer = $this->actingAsApi($classmate)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Nice'])->json('data.id');
        $this->travel(1)->minutes();
        $liked = $this->actingAsApi($lecturer)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Exactly right'])
            ->assertJsonPath('data.author.is_lecturer', true)
            ->json('data.id');
        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, "comments/{$liked}/like"))->assertOk();
        $this->actingAsApi($classmate)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Yes', 'parent_id' => $old])->assertCreated();

        $this->actingAsApi($student)->getJson($this->api($tenant, $clip, 'comments'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $liked)
            ->assertJsonPath('data.0.liked', true)
            ->assertJsonPath('data.0.likes_count', 1)
            ->assertJsonPath('data.0.can_delete', false)
            ->assertJsonPath('data.1.id', $newer)
            ->assertJsonPath('data.2.id', $old)
            ->assertJsonPath('data.2.replies_count', 1)
            ->assertJsonPath('meta.total', 3);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/watch/clips'))
            ->assertJsonPath('data.0.comments_count', 4);
    }

    public function test_a_reply_to_a_reply_joins_the_top_level_thread(): void
    {
        [$tenant, , $student, , $clip, $classmate] = $this->setUpClip();

        $top = $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Question'])->json('data.id');
        $reply = $this->actingAsApi($classmate)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Answer', 'parent_id' => $top])
            ->assertJsonPath('data.reply_to', null)
            ->json('data.id');
        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Thanks', 'parent_id' => $reply])
            ->assertCreated()
            ->assertJsonPath('data.reply_to.id', $classmate->id)
            ->assertJsonPath('data.reply_to.name', 'Siti Aminah');

        $this->assertSame(2, ClipComment::where('parent_id', $top)->count());

        $this->actingAsApi($student)->getJson($this->api($tenant, $clip, "comments/{$top}/replies"))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'Answer')
            ->assertJsonPath('data.1.body', 'Thanks')
            ->assertJsonPath('data.1.replies_count', 0);
    }

    public function test_comment_validation(): void
    {
        [$tenant, , $student, $course, $clip] = $this->setUpClip();
        $other = $this->clip($this->episode($course, 2));
        $elsewhere = $this->actingAsApi($student)->postJson($this->api($tenant, $other, 'comments'), ['body' => 'Hi'])->json('data.id');

        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'comments'), ['body' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'comments'), ['body' => str_repeat('a', 501)])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Hi', 'parent_id' => $elsewhere])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    public function test_delete_permissions_and_counts(): void
    {
        [$tenant, $lecturer, $student, , $clip, $classmate] = $this->setUpClip();

        $top = $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Question'])->json('data.id');
        $reply = $this->actingAsApi($classmate)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Answer', 'parent_id' => $top])->json('data.id');
        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'More', 'parent_id' => $top]);
        $mine = $this->actingAsApi($classmate)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Mine'])->json('data.id');

        $this->actingAsApi($classmate)->deleteJson($this->api($tenant, $clip, "comments/{$top}"))->assertForbidden();

        $this->actingAsApi($classmate)->deleteJson($this->api($tenant, $clip, "comments/{$reply}"))
            ->assertOk()->assertJsonPath('data.comments_count', 3);

        $this->actingAsApi($lecturer)->deleteJson($this->api($tenant, $clip, "comments/{$top}"))
            ->assertOk()->assertJsonPath('data.comments_count', 1);
        $this->assertSoftDeleted('clip_comments', ['id' => $top]);
        $this->assertSame(0, ClipComment::where('parent_id', $top)->count());

        $this->actingAsApi($classmate)->deleteJson($this->api($tenant, $clip, "comments/{$mine}"))
            ->assertOk()->assertJsonPath('data.comments_count', 0);
        $this->actingAsApi($classmate)->deleteJson($this->api($tenant, $clip, "comments/{$mine}"))->assertNotFound();
    }

    public function test_comment_likes_are_idempotent(): void
    {
        [$tenant, , $student, , $clip, $classmate] = $this->setUpClip();
        $comment = $this->actingAsApi($classmate)->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Hi'])->json('data.id');

        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, "comments/{$comment}/like"))
            ->assertOk()->assertJson(['data' => ['liked' => true, 'likes_count' => 1]]);
        $this->actingAsApi($student)->postJson($this->api($tenant, $clip, "comments/{$comment}/like"))
            ->assertJson(['data' => ['liked' => true, 'likes_count' => 1]]);
        $this->actingAsApi($student)->deleteJson($this->api($tenant, $clip, "comments/{$comment}/like"))
            ->assertJson(['data' => ['liked' => false, 'likes_count' => 0]]);
        $this->actingAsApi($student)->deleteJson($this->api($tenant, $clip, "comments/{$comment}/like"))
            ->assertJson(['data' => ['liked' => false, 'likes_count' => 0]]);
    }

    public function test_every_route_hides_a_clip_the_student_cannot_see(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $locked = $this->clip($this->episode($course, 1, ['status' => Episode::STATUS_LOCKED]));
        $other = $this->createCourse($tenant, $lecturer, ['code' => 'BTG3333']);
        $foreign = $this->clip($this->episode($other, 1));
        $scheduled = $this->clip($this->episode($course, 2), 1);
        $scheduled->update(['publish_at' => now()->addDay()]);
        $comment = ClipComment::create([
            'tenant_id' => $tenant->id, 'episode_clip_id' => $locked->id, 'user_id' => $lecturer->id, 'body' => 'Hidden',
        ]);

        foreach ([$locked, $foreign, $scheduled] as $clip) {
            $this->actingAsApi($student);
            $this->postJson($this->api($tenant, $clip, 'view'))->assertNotFound();
            $this->postJson($this->api($tenant, $clip, 'like'))->assertNotFound();
            $this->deleteJson($this->api($tenant, $clip, 'like'))->assertNotFound();
            $this->postJson($this->api($tenant, $clip, 'save'))->assertNotFound();
            $this->deleteJson($this->api($tenant, $clip, 'save'))->assertNotFound();
            $this->getJson($this->api($tenant, $clip, 'comments'))->assertNotFound();
            $this->postJson($this->api($tenant, $clip, 'comments'), ['body' => 'Hi'])->assertNotFound();
            $this->getJson($this->api($tenant, $clip, "comments/{$comment->id}/replies"))->assertNotFound();
            $this->deleteJson($this->api($tenant, $clip, "comments/{$comment->id}"))->assertNotFound();
            $this->postJson($this->api($tenant, $clip, "comments/{$comment->id}/like"))->assertNotFound();
            $this->deleteJson($this->api($tenant, $clip, "comments/{$comment->id}/like"))->assertNotFound();
        }
    }
}
