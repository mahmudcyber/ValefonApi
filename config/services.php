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
    'flutterwave' => [
        'base_url' => env('FLW_BASE_URL'),
        'public_key' => env('FLW_PUBLIC_KEY'),
        'secret_key' => env('FLW_SECRET_KEY'),
        'encryption_key' => env('FLW_ENCRYPTION_KEY'),
    ],
    'paystack' => [
        'base_url' => env('PAYSTACK_BASE_URL'),
        'test_key' => env('PAYSTACK_SECRET_KEY_TEST'),
        'live_key' => env('PAYSTACK_SECRET_KEY_LIVE'),
        'redirect_url' => env('PAYSTACK_REDIRECT_URL'),
    ],
    'transtype' => [
        'invs' => 'Investment',
        'widr' => 'Withdrawal',
        'reiv' => 'Reinvestment',
        'prdt' => 'Profitdist',
    ],
    'transcode' => [
        'invs' => '2210',
        'widr' => '2220',
        'reiv' => '2230',
        'pdst' => '2240',
    ],
    'transsource' => [
        'capt' => 'capital',
        'prof' => 'profit',
    ],
    'currency' => [
        'nigeria' => 'NGN',
    ],




];
