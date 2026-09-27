<?php

namespace App\Console\Commands;

use App\Models\StorageMaintenanceRun;
use App\Models\StorageOperation;
use App\Models\User;
use App\Services\Storage\StorageInspectionService;
use App\Services\Storage\StorageOperationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RecoverStorageOperations extends Command
{
    protected $signature = 'storage:recover-operations
        {--owner= : Required positive owner ID}
        {--operation= : Optional operation UUID belonging to the owner}
        {--apply : Persist transitions and repeat recorded delete intents; default is dry-run}
        {--max-operations=5 : Operations per run, maximum10}
        {--max-requests=10 : Provider read/delete requests per run, maximum20}
        {--max-seconds=10 : Soft elapsed budget, maximum10}';

    protected $description = 'Recover journaled storage operations of THIS database only; never touches unjournaled objects';

    public function handle(StorageOperationService $operations, StorageInspectionService $inspection): int
    {
        $bounded = function ($name, $max) {
            $value = $this->option($name);
            if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,5}$/D', $value) || (int) $value > $max) {
                throw new \RuntimeException('invalid_limit');
            }
            return (int) $value;
        };
        try {
            $owner = (string) $this->option('owner');
            if (!preg_match('/^[1-9][0-9]{0,17}$/D', $owner) || !User::whereKey((int) $owner)->exists()) {
                throw new \RuntimeException('invalid_owner');
            }
            $maxOperations = $bounded('max-operations', 10);
            $maxRequests = $bounded('max-requests', 20);
            $maxSeconds = $bounded('max-seconds', 10);
            $query = StorageOperation::where('user_id', (int) $owner)
                ->where(fn ($q) => $q->whereNotIn('state', StorageOperation::TERMINAL))
                ->where(fn ($q) => $q->whereNull('not_before')->orWhere('not_before', '<=', now()))
                ->orderBy('id');
            if ($this->option('operation') !== null) {
                if (!Str::isUuid((string) $this->option('operation'))) throw new \RuntimeException('invalid_operation');
                $query->where('uuid', $this->option('operation'));
            }
            $candidates = $query->limit($maxOperations)->get();
        } catch (\Throwable $e) {
            $this->line(json_encode(['mode' => 'recover_operations', 'error' => 'invalid_scope', 'mutations' => 0]));
            return 1;
        }

        $apply = (bool) $this->option('apply');
        $run = $apply ? StorageMaintenanceRun::create(['uuid' => (string) Str::uuid(), 'kind' => 'manual',
            'scope_key' => 'owner:' . $owner, 'slot_key' => (string) Str::uuid(), 'status' => 'running',
            'started_at' => now()]) : null;
        $started = microtime(true);
        $requests = 0;
        $results = [];
        $stopped = null;
        foreach ($candidates as $op) {
            $remainingSeconds = (int) floor($maxSeconds - (microtime(true) - $started));
            if ($requests >= $maxRequests || $remainingSeconds < 1) { $stopped = 'budget_exhausted'; break; }
            try {
                $result = $operations->recover($op, $apply, $inspection, $maxRequests - $requests, $remainingSeconds);
            } catch (\Throwable $e) {
                $result = ['operation_id' => $op->uuid, 'kind' => $op->kind, 'state_before' => $op->state,
                    'action' => 'error', 'requests' => 0];
            }
            $requests += $result['requests'];
            $op->refresh();
            $results[] = $result + ['state_after' => $op->state];
        }
        $report = ['mode' => $apply ? 'recover_apply' : 'recover_dry_run', 'owner_id' => (int) $owner,
            'namespace' => $operations->namespace(), 'journal_scope' => 'this_database_only',
            'operations' => $results, 'requests_used' => $requests, 'stopped' => $stopped,
            'elapsed_seconds' => round(microtime(true) - $started, 3),
            'limitations' => ['Unjournaled remote objects are never modified.',
                'Metadata evidence only; checksum_state remains unverified.']];
        if ($run) {
            $run->update(['status' => $stopped ? 'partial' : 'finished', 'finished_at' => now(),
                'counters' => ['operations' => count($results), 'requests' => $requests]]);
            $report['run_id'] = $run->uuid;
        }
        $this->line(json_encode($report, JSON_PRETTY_PRINT));
        return 0;
    }
}
