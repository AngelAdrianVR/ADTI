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
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | BioTime Pro (reloj checador)
    |--------------------------------------------------------------------------
    | url: URL base del reloj al que el ERP puede llegar por API
    |      (ej. http://127.0.0.1:81 si el ERP corre en la misma máquina del reloj,
    |      o http://192.168.1.50:81 si está en la misma red).
    |      Si se deja vacío, el comando intenta detectar la IP local de la máquina.
    | backfill_key: llave para ejecutar /backfill-biotime desde el navegador.
    */

    'biotime' => [
        'url' => env('BIOTIME_URL'),
        'port' => env('BIOTIME_PORT', 81),
        'username' => env('BIOTIME_USER', 'admin'),
        'password' => env('BIOTIME_PASS', 'adti1234'),
        'timeout' => (int) env('BIOTIME_TIMEOUT', 30),
        'page_size' => (int) env('BIOTIME_PAGE_SIZE', 200),
        'backfill_key' => env('BACKFILL_KEY'),
    ],

];
