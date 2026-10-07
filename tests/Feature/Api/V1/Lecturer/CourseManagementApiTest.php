<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\SectionStudent;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class CourseManagementApiTest extends LecturerApiTestCase
{
    public function test_options_list_terms_and_colleagues_of_this_institution_only(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer', ['name' => 'Dr Aziz']);
        $this->createMember($tenant, 'student');
        $this->createTerm($tenant, ['name' => 'Semester 1, 2026/2027', 'is_default' => true]);
        $this->createMember($this->createTenant(), 'lecturer');

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/course-options'))
            ->assertOk()
            ->assertJsonCount(1, 'data.lecturers')
            ->assertJsonPath('data.lecturers.0.name', 'Dr Aziz')
            ->assertJsonPath('data.academic_terms.0.is_default', true)
            ->assertJsonPath('data.teaching_modes', ['face_to_face', 'online', 'hybrid']);
    }

    public function test_a_lecturer_creates_a_course_with_outcomes_and_topics(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $foreignTerm = AcademicTerm::withoutGlobalScopes()->find($this->createTerm($this->createTenant())->id);

        $this->actingAsApi($lecturer)->postJson($this->tenantApi($tenant, 'lecturer/courses'), [
            'code' => 'SKMM1203', 'title' => 'Statics', 'num_weeks' => 14, 'teaching_mode' => 'face_to_face',
            'academic_term_id' => $foreignTerm->id,
        ])->assertStatus(422)->assertJsonValidationErrors('academic_term_id');

        $response = $this->actingAsApi($lecturer)->postJson($this->tenantApi($tenant, 'lecturer/courses'), [
            'code' => 'SKMM1203',
            'title' => 'Statics',
            'num_weeks' => 14,
            'teaching_mode' => 'hybrid',
            'format' => ['lecture', 'lab'],
            'clos' => [['code' => 'CLO1', 'description' => 'Resolve forces']],
            'topics' => [['week_number' => 1, 'title' => 'Vectors']],
        ])->assertCreated()
            ->assertJsonPath('data.code', 'SKMM1203')
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.format', ['lecture', 'lab'])
            ->assertJsonPath('data.learning_outcomes.0.code', 'CLO1')
            ->assertJsonPath('data.topics.0.title', 'Vectors');

        $this->assertSame($lecturer->id, Course::find($response->json('data.id'))->lecturer_id);

        $student = $this->createMember($tenant, 'student');
        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, 'lecturer/courses'), [])->assertForbidden();
    }

    public function test_only_the_owner_edits_archives_or_deletes_a_course(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $sectionLecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $owner, ['title' => 'Statics']);
        $this->createSection($course, [], [$sectionLecturer]);
        $path = $this->tenantApi($tenant, "lecturer/courses/{$course->id}");

        $this->actingAsApi($sectionLecturer)->putJson($path, ['title' => 'Hijacked'])->assertForbidden();
        $this->actingAsApi($sectionLecturer)->deleteJson($path)->assertForbidden();

        $this->actingAsApi($owner)->putJson($path, ['title' => 'Engineering Statics', 'status' => 'archived'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Engineering Statics')
            ->assertJsonPath('data.status', 'archived');

        $this->actingAsApi($owner)->deleteJson($path)->assertOk();
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    }

    public function test_outcomes_and_topics_are_added_and_removed_by_the_owner(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $owner, ['num_weeks' => 14]);
        $other = $this->createCourse($tenant, $owner);

        $cloId = $this->actingAsApi($owner)
            ->postJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}/clos"), ['code' => 'CLO1', 'description' => 'Analyse'])
            ->assertCreated()->json('data.id');

        $this->actingAsApi($owner)
            ->postJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}/topics"), ['week_number' => 15, 'title' => 'Too late'])
            ->assertStatus(422);

        $this->actingAsApi($owner)->deleteJson($this->tenantApi($tenant, "lecturer/courses/{$other->id}/clos/{$cloId}"))->assertNotFound();
        $this->actingAsApi($owner)->deleteJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}/clos/{$cloId}"))->assertOk();
    }

    public function test_sections_are_created_edited_and_timetabled(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $colleague = $this->createMember($tenant, 'lecturer');
        $outsider = $this->createMember($this->createTenant(), 'lecturer');
        $course = $this->createCourse($tenant, $owner);
        $base = $this->tenantApi($tenant, "lecturer/courses/{$course->id}/sections");

        $this->actingAsApi($owner)->postJson($base, ['name' => 'Section 02', 'code' => '02', 'lecturer_ids' => [$outsider->id]])
            ->assertStatus(422)->assertJsonValidationErrors('lecturer_ids.0');

        $sectionId = $this->actingAsApi($owner)->postJson($base, ['name' => 'Section 02', 'code' => '02', 'lecturer_ids' => [$colleague->id]])
            ->assertCreated()
            ->assertJsonPath('data.lecturers.0.id', $colleague->id)
            ->json('data.id');

        // Renaming without lecturer_ids keeps the co-lecturer (the web would drop them).
        $this->actingAsApi($colleague)->putJson("$base/$sectionId", ['name' => 'Section 2B'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Section 2B')
            ->assertJsonPath('data.lecturers.0.id', $colleague->id);

        $this->actingAsApi($colleague)->putJson("$base/$sectionId", ['lecturer_ids' => []])->assertForbidden();

        $this->actingAsApi($colleague)->putJson("$base/$sectionId/schedule", ['schedule' => [
            ['day' => 'monday', 'start_time' => '10:00', 'end_time' => '09:00', 'type' => 'lecture'],
        ]])->assertStatus(422);

        $this->actingAsApi($colleague)->putJson("$base/$sectionId/schedule", ['schedule' => [
            ['day' => 'monday', 'start_time' => '08:00', 'end_time' => '10:00', 'location' => 'DK1', 'type' => 'lecture'],
        ]])->assertOk()->assertJsonPath('data.schedule.0.location', 'DK1');
    }

    public function test_csv_import_reports_each_skipped_row_and_reactivates_removed_students(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $owner);
        $section = $this->createSection($course);
        $removed = $this->createMember($tenant, 'student', ['email' => 'removed@example.com']);
        SectionStudent::create(['section_id' => $section->id, 'user_id' => $removed->id, 'enrolled_at' => now(), 'enrollment_method' => 'manual', 'is_active' => false]);

        $csv = "\u{FEFF}Name,Email,Matric\nAina Sofea,aina@example.com,A21EM0001\nNo Email,,\nRemoved Student,removed@example.com,\nBad,not-an-email,\n";

        $this->actingAsApi($owner)
            ->post($this->tenantApi($tenant, "lecturer/courses/{$course->id}/sections/{$section->id}/students/import"), [
                'csv_file' => UploadedFile::fake()->createWithContent('roster.csv', $csv),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.reactivated', 1)
            ->assertJsonPath('data.skipped', 2)
            ->assertJsonPath('data.errors.0.line', 3);

        $this->assertTrue(User::where('email', 'aina@example.com')->exists());
        $this->assertTrue(SectionStudent::where('user_id', $removed->id)->value('is_active'));
    }
}
