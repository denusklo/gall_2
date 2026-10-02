<?php

namespace App\Console\Commands;

use App\Services\Storage\StorageInspectionService;
use Illuminate\Console\Command;

class ReconcileStorage extends Command
{
    protected $signature = 'storage:reconcile
        {--owner= : Required positive owner ID; never defaults}
        {--credential= : Required saved credential belonging to owner}
        {--max-pages=10 : Inventory page cap, maximum30}
        {--max-requests=30 : All provider read requests, maximum100}
        {--max-seconds=10 : Soft elapsed budget, maximum30; DNS may outlast this}
        {--page-size=100 : Page size, maximum100}
        {--dry-run : Compatibility flag; command is always read-only}';

    protected $description = 'Report account-scoped storage metadata as JSON; no DB/provider mutations or cleanup';

    public function handle(StorageInspectionService $inspection): int
    {
        try {
            $report = $inspection->inspect($this->option('owner'), $this->option('credential'), [
                'pages' => $this->option('max-pages'), 'requests' => $this->option('max-requests'),
                'seconds' => $this->option('max-seconds'), 'page_size' => $this->option('page-size'),
            ]);
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));
            return $report['issues'] ? 2 : 0;
        } catch (\Throwable $e) {
            // Never serialize models, raw provider errors, exception messages or signed URLs.
            $this->line(json_encode(['mode' => 'reconcile_read_only', 'error' => 'inspection_rejected',
                'hint' => 'Check explicit owned IDs, hosted account identity, service credentials and limit bounds.', 'mutations' => 0]));
            return 1;
        }
    }
}
