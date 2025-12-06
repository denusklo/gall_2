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
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'key' => env('SUPABASE_KEY'),
        'service_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    ],

    // Add this to your config/services.php file in the return array:

    'vercel' => [
        'blob_read_write_token' => env('VERCEL_BLOB_READ_WRITE_TOKEN'),
        'blob_store_url' => env('VERCEL_BLOB_STORE_URL', 'https://blob.vercel-storage.com'),
    ],

    // Also add these to your .env file:
    // VERCEL_BLOB_READ_WRITE_TOKEN=your-vercel-token-here
    // VERCEL_BLOB_STORE_URL=https://your-store-id.blob.vercel-storage.com

];
