<?php

$namespace = strtolower((string) env('STORAGE_NAMESPACE', env('APP_ENV', 'production')));
$namespace = preg_match('/^[a-z0-9-]{1,32}$/D', $namespace) ? $namespace : 'invalid';

return [
    // Every new durable upload is reserved under app/{namespace}/{owner}/{operation}/.
    // Approved long-term direction: a separate bucket/store per environment. That needs no
    // code: each environment's DB registers its own credentials pointing at its own store.
    // The design must still be safe when environments share a store with identical
    // credentials, so namespaces must differ and each DB acts only on its own journal.
    'namespace' => $namespace,

    // 16:00 UTC is 00:00 in Malaysia. vercel.json declares this cron for
    // GET /apiv/_1/internal/storage-maintenance. Vercel only runs crons on production
    // deployments, and the route fails closed (401, zero work) unless CRON_SECRET is set,
    // so declaring it is safe before any secret is provisioned. Local/stage use
    // `php artisan storage:run-maintenance` (same runner, same once-per-UTC-day slot).
    'daily_schedule_utc' => '0 16 * * *',
    'cron_secret' => env('CRON_SECRET'),

    // Daily runner budgets, deliberately small for the PHP function deadline. The soft
    // seconds budget gates starting new work; one slow provider request may exceed it.
    // Unfinished work stays in the journal and resumes on the next day via a durable cursor.
    'runner_max_operations' => 5,
    'runner_max_requests' => 6,
    'runner_soft_seconds' => 4,

    'lease_seconds' => 60,
    // Read-only inspection/recovery transport. connect_timeout includes the TLS handshake
    // (~6s measured from php-fpm-8.2 to Supabase), so it must exceed that.
    'inspection_connect_timeout' => 8,
    'inspection_request_timeout' => 12,
    // storage-js documents two-hour signed upload URLs; Vercel client tokens here are one hour.
    'supabase_upload_ttl_seconds' => 7200,
    'vercel_upload_ttl_seconds' => 3600,
    // Allowance for uploads still in flight when an authorization expires.
    'upload_grace_seconds' => 900,
    // Legacy v1 receipts and broad keys could authorize writes for up to one hour.
    'legacy_authorization_horizon_seconds' => 3600,
    'max_pending_per_owner' => 100,
    'max_pending_global' => 1000,
];
