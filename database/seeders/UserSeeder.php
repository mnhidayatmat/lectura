<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin (platform-level)
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@lectura.app'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $tenant = Tenant::where('slug', 'demo-university')->first();

        if (! $tenant) {
            return;
        }

        // Tenant Admin
        $tenantAdmin = User::firstOrCreate(
            ['email' => 'coordinator@demo.edu'],
            [
                'name' => 'Dr. Ahmad (Coordinator)',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        TenantUser::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $tenantAdmin->id, 'role' => 'admin'],
            ['is_active' => true, 'joined_at' => now()]
        );

        // Lecturer
        $lecturer = User::firstOrCreate(
            ['email' => 'lecturer@demo.edu'],
            [
                'name' => 'Dr. Siti (Lecturer)',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        TenantUser::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $lecturer->id, 'role' => 'lecturer'],
            ['is_active' => true, 'joined_at' => now()]
        );

        // Students
        $students = [
            ['name' => 'Ali bin Abu', 'email' => 'ali@student.demo.edu', 'student_id' => 'S2024001'],
            ['name' => 'Nurul Aisyah', 'email' => 'nurul@student.demo.edu', 'student_id' => 'S2024002'],
            ['name' => 'Raj Kumar', 'email' => 'raj@student.demo.edu', 'student_id' => 'S2024003'],
        ];

        foreach ($students as $studentData) {
            $student = User::firstOrCreate(
                ['email' => $studentData['email']],
                [
                    'name' => $studentData['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            TenantUser::firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $student->id, 'role' => 'student'],
                [
                    'student_id_number' => $studentData['student_id'],
                    'is_active' => true,
                    'joined_at' => now(),
                ]
            );
        }
    }
}
