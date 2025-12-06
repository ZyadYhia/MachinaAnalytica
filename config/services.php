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

    'anythingllm' => [
        'url' => env('ANYTHINGLLM_URL'),
        'auth_token' => env('ANYTHINGLLM_AUTH'),
        'default_workspace' => env('ANYTHINGLLM_DEFAULT_WORKSPACE'),
        'default_thread' => env('ANYTHINGLLM_DEFAULT_THREAD'),
    ],

    'jan' => [
        'url' => env('JAN_URL', 'http://localhost:1337'),
        'auth_token' => env('JAN_AUTH_TOKEN'),
        'default_model' => env('JAN_DEFAULT_MODEL', ''),
    ],

];
