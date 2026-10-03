<?php

// Set these per deployment. Preview must not inherit the production origin.
$preview = env('VERCEL_ENV') === 'preview';
$previewHost = env('VERCEL_URL');

return [
    'origin' => env('PUSH_ORIGIN', $preview
        ? ($previewHost ? 'https://' . $previewHost : null)
        : env('APP_URL')),
];
