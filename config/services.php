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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'whatsapp' => [
        'send_endpoint' => env('WA_API_URL_SINGLE'),
        'campaign_endpoint' => env('WA_API_URL_BULK'),
        'link_endpoint' => env('WA_API_URL_LINK'),
        'relink_endpoint' => env('WA_API_URL_RELINK'),
        'accounts_endpoint' => env('WA_API_URL_GET_ACCOUNTS'),
        'message_endpoint' => env('WA_API_URL_GET_MESSAGE'),
        'api_key' => env('WA_API_KEY'),
        'sid' => env('WA_API_SID'),
        'default_account' => env('WA_DEFAULT_ACCOUNT'),
        'allowed_accounts' => array_filter(array_map(
            'trim',
            explode(',', (string) env('WA_ALLOWED_ACCOUNTS', ''))
        )),
        'webhook_token' => env('WA_WEBHOOK_TOKEN'),
        'timeout' => env('WA_API_TIMEOUT', 30),
        'verify_ssl' => env('WA_API_VERIFY_SSL', true),
        'chatbot_welcome_message' => env(
            'WA_CHATBOT_WELCOME_MESSAGE',
            'Hola, soy el asistente virtual. Hemos recibido tu mensaje.'
        ),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4.1-mini'),
        'timeout' => env('OPENAI_API_TIMEOUT', 60),
        'verify_ssl' => env('OPENAI_API_VERIFY_SSL', true),
    ],

    'lotonet' => [
        'session_url' => env('LOTONET_SESSION_URL', 'http://contable.apploteka.com/api/finan/sessions'),
        'attendance_url' => env('LOTONET_ATTENDANCE_URL', 'http://contable.apploteka.com/api/finan/asistencia_usuarios'),
        'attendance_token' => env('LOTONET_ATTENDANCE_TOKEN', 'ZFozLWdBYyqERusVdTsW'),
        'attendance_cookie' => env('LOTONET_ATTENDANCE_COOKIE', '_orkapi_session=RkZLWFpIMnM1UTdUdjRXVzNuMFRmZFZnQ2U5N0JoV0JaSzBheUFlZ21TSVoyUEhWWFc2Y2R4Nzd2SmVhQXJKOGtsSktHWnNmelgzWGsxcmJESEVkcXRlWW5tdGpzU1ZZcXRBZFNva2lqL3pGMFppZFZnZUxPUXBscWxLYVdVcUwzdURYb1V5bGJwanZkeDdJTGUzZndkV3FxNmtiMjdvNkxpU0ZQK2RWRU1nPS0tbkVwL215TXpYTXpLS1lYYXJTR3Y2UT09--7e272c2a327d71d9feb7996870d828122936b682'),
        'username' => env('LOTONET_USERNAME', 'fjoselito'),
        'password' => env('LOTONET_PASSWORD', 'mnXd5pSyF3HXjCC4'),
    ],

];
