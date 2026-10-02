<?php

namespace App\Console\Commands;

use App\Services\Storage\StorageInspectionService;
use Illuminate\Console\Command;

class ReviewLegacyStorageMapping extends Command
{
    protected $signature = 'storage:mapping-review
        {--owner= : Required positive owner ID}
        {--credential= : Required saved candidate credential belonging to owner}
        {--image= : Required owned image ID, including soft-deleted images}
        {--max-requests=10 : Provider read request cap, maximum100}
        {--max-seconds=10 : Soft elapsed budget, maximum30; DNS may outlast this}
        {--dry-run : Compatibility flag; mapping is never applied}';

    protected $description = 'Emit a private JSON legacy mapping proposal with metadata evidence; no apply action';

    public function handle(StorageInspectionService $inspection): int
    {
        try {
            if ($this->option('image') === null) throw new \RuntimeException('image_required');
            $report = $inspection->inspect($this->option('owner'), $this->option('credential'), [
                'requests' => $this->option('max-requests'), 'seconds' => $this->option('max-seconds'),
            ], $this->option('image'));
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));
            return $report['issues'] ? 2 : 0;
        } catch (\Throwable $e) {
            $this->line(json_encode(['mode' => 'mapping_dry_run', 'error' => 'inspection_rejected',
                'hint' => 'Check explicit owner, saved candidate credential, owned image and limit bounds.',
                'apply_enabled' => false, 'mutations' => 0]));
            return 1;
        }
    }
}
