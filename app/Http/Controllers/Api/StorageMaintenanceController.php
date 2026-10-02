<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Storage\StorageMaintenanceRunner;

/** GET because Vercel Cron invokes routes with GET. Aggregate output only. */
class StorageMaintenanceController extends Controller
{
    public function __invoke(StorageMaintenanceRunner $runner)
    {
        return response()->json($runner->run('cron'))
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
