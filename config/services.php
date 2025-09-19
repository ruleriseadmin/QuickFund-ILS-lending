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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'interswitch' => [
        'base_url' => env('INTERSWITCH_BASE_ENDPOINT'),
        'name_enquiry_base_url' => env('INTERSWITCH_NAME_ENQUIRY_BASE_ENDPOINT'),
        'provider_code' => env('INTERSWITCH_PROVIDER_CODE'),
        'channel_code' => env('INTERSWITCH_CHANNEL_CODE'),
        'client_id' => env('INTERSWITCH_CLIENT_ID'),
        'client_secret' => env('INTERSWITCH_CLIENT_SECRET'),
        'oauth_token_url' => env('INTERSWITCH_OAUTH_TOKEN_ENDPOINT'),
        'customer_info_url' => env('INTERSWITCH_CUSTOMER_INFO_ENDPOINT'),
        'customer_credit_score_url' => env('INTERSWITCH_CUSTOMER_CREDIT_SCORE_ENDPOINT'),
        'credit_customer_url' => env('INTERSWITCH_CREDIT_CUSTOMER_URL'),
        'transaction_prefix' => env('INTERSWITCH_TRANSACTION_PREFIX'),
        'quickteller_terminal_id' => env('INTERSWITCH_QUICKTELLER_TERMINAL_ID'),
        'oauth_token_expiration' => 1800,
        'default_currency_code' => '566'
    ],

    'crc' => [
        'url' => env('CRC_URL'),
        'username' => env('CRC_USERNAME'),
        'password' => env('CRC_PASSWORD'),
        'reporting_userid' => env('CRC_REPORT_USERID'),
        'feedback_email' => env('CRC_FEEDBACK_EMAIL'),
    ],

    'first_central' => [
        'base_url' => env('FIRST_CENTRAL_BASE_URL'),
        'username' => env('FIRST_CENTRAL_USERNAME'),
        'password' => env('FIRST_CENTRAL_PASSWORD'),
        'enquiry_reason' => env('FIRST_CENTRAL_ENQUIRY_REASON')
    ],

];
