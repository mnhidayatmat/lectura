<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    // Sign in with Apple (mobile app): the audience of the identity token is the
    // app's bundle id. Comma-separated so a second bundle (e.g. a beta app) can be
    // accepted without a code change.
    'apple' => [
        'client_ids' => env('APPLE_CLIENT_IDS', 'com.lectura.go'),

        // Revoking the token when an account is deleted is an App Store requirement,
        // and the only part of Sign in with Apple that needs a signing key. Leave
        // these empty and sign-in still works — nothing is revoked.
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
        'private_key_path' => env('APPLE_PRIVATE_KEY_PATH'),
    ],

    // Firebase Cloud Messaging (mobile push). Points at the service-account JSON
    // downloaded from Firebase console → Project settings → Service accounts. Left
    // empty, notifications are still stored and shown in the app; none are pushed.
    'fcm' => [
        'credentials_path' => env('FCM_CREDENTIALS_PATH'),
    ],

    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_DRIVE_REDIRECT_URI'),
    ],

];
