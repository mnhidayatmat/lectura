<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SectionRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_leaves_out_staff_who_also_hold_the_student_role(): void
    {
        $tenant = Tenant::create(['name' => 'Test U', 'slug' => 'test-u', 'timezone' => 'UTC', 'locale' => 'en']);
        $other = Tenant::create(['name' => 'Other U', 'slug' => 'other-u', 'timezone' => 'UTC', 'locale' => 'en']);
        app()->instance('current_tenant', $tenant);

        $lecturer = User::factory()->create();
        $course = Course::create([
            'tenant_id' => $tenant->id, 'lecturer_id' => $lecturer->id,
            'code' => 'BTG1000', 'title' => 'Test Course', 'status' => 'active',
        ]);
        $section = Section::create([
            'tenant_id' => $tenant->id, 'course_id' => $course->id,
            'name' => 'Section 1', 'code' => 'S1', 'is_active' => true,
        ]);

        $student = User::factory()->create();
        $staffElsewhere = User::factory()->create();
        $dropped = User::factory()->create();

        $roles = [
            [$lecturer->id, $tenant->id, 'lecturer'],
            [$lecturer->id, $tenant->id, 'student'],
            [$student->id, $tenant->id, 'student'],
            [$staffElsewhere->id, $tenant->id, 'student'],
            [$staffElsewhere->id, $other->id, 'lecturer'],
        ];
        foreach ($roles as [$userId, $tenantId, $role]) {
            DB::table('tenant_users')->insert([
                'user_id' => $userId, 'tenant_id' => $tenantId, 'role' => $role, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach ([$lecturer, $student, $staffElsewhere] as $user) {
            SectionStudent::create(['section_id' => $section->id, 'user_id' => $user->id, 'is_active' => true]);
        }
        SectionStudent::create(['section_id' => $section->id, 'user_id' => $dropped->id, 'is_active' => false]);

        $roster = SectionStudent::where('section_id', $section->id)->roster()->pluck('user_id')->sort()->values();

        $this->assertEquals(collect([$student->id, $staffElsewhere->id])->sort()->values(), $roster);
    }
}
