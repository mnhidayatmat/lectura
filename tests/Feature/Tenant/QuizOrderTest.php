<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\QuizSession;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * Lecturers arrange a course's quiz list by hand.
 */
class QuizOrderTest extends ApiTestCase
{
    public function test_quiz_list_follows_the_manual_order(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $section = $this->createSection($course, [], [$lecturer]);
        $this->createQuiz($section, $lecturer, 'Gamma', ['sort_order' => 2]);
        $this->createQuiz($section, $lecturer, 'Alpha', ['sort_order' => 0]);
        $this->createQuiz($section, $lecturer, 'Beta', ['sort_order' => 1]);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/quizzes/course/{$course->id}")
            ->assertOk()
            ->assertSee('listSorter', false)
            ->assertSee('Move up')
            ->assertSeeInOrder(['Alpha', 'Beta', 'Gamma']);
    }

    public function test_new_quizzes_join_the_end_of_their_course_order(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $sectionA = $this->createSection($course, [], [$lecturer]);
        $sectionB = $this->createSection($course, [], [$lecturer]);
        $otherSection = $this->createSection($this->createCourse($tenant, $lecturer), [], [$lecturer]);

        $this->createQuiz($sectionA, $lecturer, 'First', ['sort_order' => 4]);
        $this->createQuiz($otherSection, $lecturer, 'Elsewhere', ['sort_order' => 9]);

        $this->assertSame(5, $this->createQuiz($sectionB, $lecturer, 'Second')->sort_order);
        $this->assertSame(0, $this->createQuiz($this->createSection($this->createCourse($tenant, $lecturer)), $lecturer, 'Only')->sort_order);
    }

    public function test_lecturer_saves_a_new_order_and_foreign_quizzes_are_ignored(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $section = $this->createSection($course, [], [$lecturer]);
        $otherSection = $this->createSection($this->createCourse($tenant, $lecturer), [], [$lecturer]);
        $a = $this->createQuiz($section, $lecturer, 'A');
        $b = $this->createQuiz($section, $lecturer, 'B');
        $c = $this->createQuiz($section, $lecturer, 'C');
        $foreign = $this->createQuiz($otherSection, $lecturer, 'Foreign', ['sort_order' => 7]);

        $this->actingAs($lecturer)
            ->postJson("/{$tenant->slug}/quizzes/course/{$course->id}/reorder", ['ordered_ids' => [$c->id, $foreign->id, $a->id, $b->id]])
            ->assertOk();

        $this->assertSame(
            ['C', 'A', 'B'],
            QuizSession::where('section_id', $section->id)->orderBy('sort_order')->pluck('title')->all()
        );
        $this->assertSame(7, $foreign->fresh()->sort_order);
    }

    public function test_another_lecturer_cannot_reorder_the_quizzes(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $stranger = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $owner);
        $quiz = $this->createQuiz($this->createSection($course, [], [$owner]), $owner, 'A', ['sort_order' => 3]);

        $this->actingAs($stranger)
            ->postJson("/{$tenant->slug}/quizzes/course/{$course->id}/reorder", ['ordered_ids' => [$quiz->id]])
            ->assertForbidden();

        $this->assertSame(3, $quiz->fresh()->sort_order);
    }

    public function test_migration_backfills_creation_order(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $section = $this->createSection($course, [], [$lecturer]);
        $first = $this->createQuiz($section, $lecturer, 'First', ['sort_order' => 5]);
        $second = $this->createQuiz($section, $lecturer, 'Second', ['sort_order' => 1]);
        DB::table('quiz_sessions')->update(['sort_order' => null]);

        $migration = require database_path('migrations/2026_09_28_000001_add_sort_order_to_quiz_sessions_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame([0, 1], [$first->fresh()->sort_order, $second->fresh()->sort_order]);
    }

    private function createQuiz(Section $section, User $lecturer, string $title, array $attributes = []): QuizSession
    {
        return QuizSession::create(array_merge([
            'tenant_id' => $section->tenant_id,
            'section_id' => $section->id,
            'lecturer_id' => $lecturer->id,
            'title' => $title,
            'category' => 'live',
            'mode' => 'formative',
            'status' => 'ended',
        ], $attributes));
    }
}
