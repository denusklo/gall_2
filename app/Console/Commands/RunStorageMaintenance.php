<?php

namespace App\Console\Commands;

use App\Services\Storage\StorageMaintenanceRunner;
use Illuminate\Console\Command;

/** CLI parity for local/stage, where no cron exists. Same runner, same daily slot rules. */
class RunStorageMaintenance extends Command
{
    protected $signature = 'storage:run-maintenance';

    protected $description = 'Run the bounded daily storage maintenance for THIS database (same runner as the cron route)';

    public function handle(StorageMaintenanceRunner $runner): int
    {
        $this->line(json_encode($runner->run('cli'), JSON_PRETTY_PRINT));
        return 0;
    }
}
