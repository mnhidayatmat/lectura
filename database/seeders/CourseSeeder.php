<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseLearningOutcome;
use App\Models\CourseTopic;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo-university')->first();
        if (! $tenant) {
            return;
        }

        $lecturer = User::where('email', 'lecturer@demo.edu')->first();
        if (! $lecturer) {
            return;
        }

        // Create academic term
        $term = AcademicTerm::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'SEM1-2627'],
            [
                'name' => 'Semester 1, 2026/2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-01-31',
                'is_default' => true,
            ]
        );

        // Course 1: Data Structures
        $cs201 = Course::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'CS201'],
            [
                'lecturer_id' => $lecturer->id,
                'title' => 'Data Structures & Algorithms',
                'description' => 'Fundamental data structures including arrays, linked lists, trees, graphs, and their associated algorithms.',
                'credit_hours' => 3,
                'num_weeks' => 14,
                'teaching_mode' => 'face_to_face',
                'format' => ['lecture' => true, 'tutorial' => true, 'lab' => true],
                'academic_term_id' => $term->id,
                'status' => 'active',
            ]
        );

        // CLOs for CS201
        $cloData = [
            ['CLO1', 'Explain fundamental data structures and their operations'],
            ['CLO2', 'Implement common algorithms for sorting, searching, and traversal'],
            ['CLO3', 'Analyse time and space complexity of algorithms'],
            ['CLO4', 'Apply appropriate data structures to solve real-world problems'],
        ];
        foreach ($cloData as $i => [$code, $desc]) {
            CourseLearningOutcome::firstOrCreate(
                ['course_id' => $cs201->id, 'code' => $code],
                ['description' => $desc, 'sort_order' => $i]
            );
        }

        // Topics for CS201
        $topics = [
            'Introduction to Data Structures', 'Arrays and Strings', 'Linked Lists',
            'Stacks and Queues', 'Recursion', 'Trees and Binary Trees',
            'Binary Search Trees', 'Mid-Semester Review', 'Heaps and Priority Queues',
            'Hash Tables', 'Graphs and Traversals', 'Sorting Algorithms',
            'Algorithm Analysis', 'Final Review',
        ];
        foreach ($topics as $i => $title) {
            CourseTopic::firstOrCreate(
                ['course_id' => $cs201->id, 'week_number' => $i + 1],
                ['title' => $title, 'sort_order' => $i]
            );
        }

        // Section for CS201
        $section1 = Section::firstOrCreate(
            ['tenant_id' => $tenant->id, 'course_id' => $cs201->id, 'code' => 'SEC01'],
            [
                'name' => 'Section 01',
                'invite_code' => 'CS201SEC1',
                'capacity' => 45,
            ]
        );

        // Enroll demo students
        $students = User::whereHas('tenantUsers', fn($q) => $q->where('tenant_id', $tenant->id)->where('role', 'student'))->get();
        foreach ($students as $student) {
            SectionStudent::firstOrCreate(
                ['section_id' => $section1->id, 'user_id' => $student->id],
                ['enrolled_at' => now(), 'enrollment_method' => 'manual', 'is_active' => true]
            );
        }

        // Course 2: Web Development
        $cs301 = Course::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'CS301'],
            [
                'lecturer_id' => $lecturer->id,
                'title' => 'Web Application Development',
                'description' => 'Modern web development using PHP, Laravel, and front-end frameworks.',
                'credit_hours' => 3,
                'num_weeks' => 14,
                'teaching_mode' => 'hybrid',
                'format' => ['lecture' => true, 'lab' => true],
                'academic_term_id' => $term->id,
                'status' => 'active',
            ]
        );

        CourseLearningOutcome::firstOrCreate(
            ['course_id' => $cs301->id, 'code' => 'CLO1'],
            ['description' => 'Design and develop dynamic web applications using MVC architecture', 'sort_order' => 0]
        );
        CourseLearningOutcome::firstOrCreate(
            ['course_id' => $cs301->id, 'code' => 'CLO2'],
            ['description' => 'Implement RESTful APIs and database-driven applications', 'sort_order' => 1]
        );
    }
}
