<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution
    |--------------------------------------------------------------------------
    */

    'tenant' => [
        'resolver' => env('TENANT_RESOLVER', 'path'), // 'subdomain' or 'path'
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'default_provider' => env('AI_DEFAULT_PROVIDER', 'claude'),

        'providers' => [
            'claude' => [
                'api_key' => env('ANTHROPIC_API_KEY'),
                'model' => env('AI_CLAUDE_MODEL', 'claude-sonnet-4-6'),
                'max_tokens' => 4096,
            ],
            'openai' => [
                'api_key' => env('OPENAI_API_KEY'),
                'model' => env('AI_OPENAI_MODEL', 'gpt-4o'),
                'max_tokens' => 4096,
            ],
            'gemini' => [
                'api_key' => env('GEMINI_API_KEY'),
                'model' => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
                'max_tokens' => 4096,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance Configuration
    |--------------------------------------------------------------------------
    */

    'attendance' => [
        'qr_rotation_seconds' => env('QR_ROTATION_SECONDS', 30),
        'late_threshold_minutes' => env('ATTENDANCE_LATE_MINUTES', 15),
        'absence_alert_threshold' => 3, // consecutive absences to trigger alert
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Limits
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'max_file_size_mb' => env('MAX_FILE_SIZE_MB', 25),
        'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'pptx'],
        'image_max_width' => 2048,
        'image_quality' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Quiz Configuration
    |--------------------------------------------------------------------------
    */

    'quiz' => [
        'max_concurrent_participants' => env('QUIZ_MAX_PARTICIPANTS', 100),
        'default_time_limit_seconds' => 30,
        'join_code_length' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Folder Template
    |--------------------------------------------------------------------------
    */

    'default_folders' => [
        'Course Information',
        'Teaching Plan',
        'Weekly Materials',
        'Attendance Records',
        'Active Learning Activities',
        'Quizzes',
        'Assignments',
        'Rubrics and Marking Schemes',
        'Student Submissions',
        'Marked Scripts and Feedback',
        'CLO / Performance Reports',
        'Reflection / CQI',
        'Supporting Evidence',
    ],

];
