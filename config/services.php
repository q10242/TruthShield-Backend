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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', env('APP_URL').'/api/auth/facebook/socialite-callback'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/api/auth/google/socialite-callback'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', env('APP_URL').'/api/auth/github/socialite-callback'),
    ],

    'ecpay' => [
        'checkout_url' => env('ECPAY_CHECKOUT_URL', 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5'),
        'merchant_id' => env('ECPAY_MERCHANT_ID', '<ECPAY_STAGING_MERCHANT_ID>'),
        'hash_key' => env('ECPAY_HASH_KEY', '<ECPAY_STAGING_HASH_KEY>'),
        'hash_iv' => env('ECPAY_HASH_IV', '<ECPAY_STAGING_HASH_IV>'),
        'trade_prefix' => env('ECPAY_TRADE_PREFIX', 'TSD'),
        'api_base_url' => env('ECPAY_API_BASE_URL', env('APP_URL')),
        'web_base_url' => env('ECPAY_WEB_BASE_URL', env('FRONTEND_URL', 'http://127.0.0.1:15173')),
    ],

];
