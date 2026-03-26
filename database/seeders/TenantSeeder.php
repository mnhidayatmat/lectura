<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::firstOrCreate(
            ['slug' => 'demo-university'],
            [
                'name' => 'Demo University',
                'timezone' => 'Asia/Kuala_Lumpur',
                'locale' => 'en',
                'is_active' => true,
                'settings' => [
                    'auth' => [
                        'allow_google_login' => true,
                        'sso_enabled' => false,
                    ],
                    'ai' => [
                        'enabled' => true,
                        'provider' => 'claude',
                        'api_key_source' => 'platform',
                        'modules_enabled' => ['teaching_plan', 'marking', 'feedback', 'activity'],
                        'monthly_quota' => 10000,
                    ],
                    'storage' => [
                        'drive_mode' => 'lecturer',
                        'max_file_size_mb' => 25,
                    ],
                    'privacy' => [
                        'ai_consent_required' => true,
                        'data_retention_months' => 36,
                        'admin_can_view_individual_marks' => false,
                    ],
                ],
            ]
        );
    }
}
