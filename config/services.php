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
        'redirect' => env('GOOGLE_REDIRECT'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT'),
    ],

    'tiktok_scraper' => [
        'url' => env('TIKTOK_SCRAPER_URL'),
        'key' => env('TIKTOK_SCRAPER_KEY'),
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'initialize_url' => env('PAYSTACK_INITIALIZE_URL', 'https://api.paystack.co/transaction/initialize'),
        'verify_url' => env('PAYSTACK_VERIFY_URL', 'https://api.paystack.co/transaction/verify'),
    ],

    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
        'payment_url' => env('FLUTTERWAVE_PAYMENT_URL', 'https://api.flutterwave.com/v3/payments'),
        'verify_url' => env('FLUTTERWAVE_VERIFY_URL', 'https://api.flutterwave.com/v3/transactions'),
        'verify_reference_url' => env('FLUTTERWAVE_VERIFY_REFERENCE_URL', 'https://api.flutterwave.com/v3/transactions/verify_by_reference'),
    ],

    'tgipay' => [
        'public_key' => env('TGIPAY_PUBLIC_KEY'),
        'secret_key' => env('TGIPAY_SECRET_KEY'),
        'base_url' => env('TGIPAY_BASE_URL', 'https://api.tgipay.com'),
        'initialize_path' => env('TGIPAY_INITIALIZE_PATH', '/payments/initialize'),
        'verify_path' => env('TGIPAY_VERIFY_PATH', '/payments/verify'),
        'signature_header' => env('TGIPAY_SIGNATURE_HEADER', 'x-tgipay-signature'),
        'signature_algo' => env('TGIPAY_SIGNATURE_ALGO', 'sha256'),
        'enforce_verify_on_success' => env('TGIPAY_ENFORCE_VERIFY_ON_SUCCESS', true),
        'webhook_event_key' => env('TGIPAY_WEBHOOK_EVENT_KEY', 'event'),
        'webhook_status_key' => env('TGIPAY_WEBHOOK_STATUS_KEY', 'data.status'),
        'webhook_reference_key' => env('TGIPAY_WEBHOOK_REFERENCE_KEY', 'data.reference'),
        'webhook_transaction_id_key' => env('TGIPAY_WEBHOOK_TRANSACTION_ID_KEY', 'data.id'),
        'success_events' => array_filter(array_map('trim', explode(',', (string) env('TGIPAY_SUCCESS_EVENTS', 'payment.success,charge.success,transaction.success')))),
        'failed_events' => array_filter(array_map('trim', explode(',', (string) env('TGIPAY_FAILED_EVENTS', 'payment.failed,charge.failed,transaction.failed')))),
        'pending_events' => array_filter(array_map('trim', explode(',', (string) env('TGIPAY_PENDING_EVENTS', 'payment.pending,charge.pending,transaction.pending')))),
        'success_statuses' => array_filter(array_map('trim', explode(',', (string) env('TGIPAY_SUCCESS_STATUSES', 'success,successful,paid,completed')))),
        'failed_statuses' => array_filter(array_map('trim', explode(',', (string) env('TGIPAY_FAILED_STATUSES', 'failed,error,declined,cancelled')))),
        'pending_statuses' => array_filter(array_map('trim', explode(',', (string) env('TGIPAY_PENDING_STATUSES', 'pending,processing,initiated')))),
    ],

];
