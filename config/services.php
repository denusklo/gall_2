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

    // Firebase FCM Configuration
    'firebase' => [
        'api_key' => env('FIREBASE_WEB_API_KEY', env('FIREBASE_API_KEY')),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
        'database_url' => env('FIREBASE_DATABASE_URL'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
        'app_id' => env('FIREBASE_APP_ID'),
        'measurement_id' => env('FIREBASE_MEASUREMENT_ID'),
        'vapid_key' => env('FIREBASE_VAPID_KEY'),
    ],

    // Also add these to your .env file:
    // FIREBASE_API_KEY=your-web-api-key
    // FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
    // FIREBASE_DATABASE_URL=https://your-project.firebaseio.com
    // FIREBASE_PROJECT_ID=your-project-id
    // FIREBASE_STORAGE_BUCKET=your-project.appspot.com
    // FIREBASE_MESSAGING_SENDER_ID=your-sender-id
    // FIREBASE_APP_ID=your-app-id
    // FIREBASE_MEASUREMENT_ID=your-measurement-id
    // FIREBASE_VAPID_KEY=your-vapid-key

];
