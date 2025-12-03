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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'preset' => [
        'url'        => env('PRESET_URL'),
        'api_secret' => env('PRESET_API_SECRET'),
    ],

    'metabase' => [
        'url'    => env('METABASE_URL'),
        'secret' => env('METABASE_SECRET'),
    ],

    'b2' => [
        'key_id'      => env('B2_KEY_ID'),
        'app_key'     => env('B2_APP_KEY'),
        'bucket_id'   => env('B2_BUCKET_ID'),
        'bucket_name' => env('B2_BUCKET_NAME'),
    ],

    'superset' => [
        'base_url'     => env('SUPERSET_BASE_URL', 'http://localhost:8088'),
        'username'     => env('SUPERSET_USERNAME'),
        'password'     => env('SUPERSET_PASSWORD'),
        'dashboard_id' => env('SUPERSET_DASHBOARD_ID'),
    ],

];
