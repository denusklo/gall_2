<?php

namespace App\Services\Storage;

use App\Models\StorageMaintenanceRun;
use App\Models\StorageOperation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bounded once-per-UTC-day maintenance of THIS database's journal. It never admits new
 * work: it only continues recorded operations through StorageOperationService::recover()
 * (register proven uploads, finalize proven deletes, repeat recorded delete intents).
 */
class StorageMaintenanceRunner
{
    private StorageOperationService $operations;
    private StorageInspectionService $inspection;

    public function __construct(StorageOperationService $operations, StorageInspectionService $inspection)
    {
        $this->operations = $operations;
        $this->inspection = $inspection;
    }

    public function run(string $trigger): array
    {
        $now = now()->utc();
        $slot = $now->toDateString();
        $token = (string) Str::uuid();
        try {
            $run = StorageMaintenanceRun::create(['uuid' => (string) Str::uuid(), 'kind' => 'daily',
                'scope_key' => 'global', 'slot_key' => $slot, 'status' => 'running', 'lease_token' => $token,
                'lease_generation' => 1, 'started_at' => $now,
                'lease_expires_at' => $now->copy()->addSeconds((int) config('storage_maintenance.lease_seconds', 60)),
                'counters' => ['trigger' => $trigger]]);
        } catch (QueryException $e) {
            // Unique (kind, scope, slot): the day's slot is already claimed. Never same-day rerun,
            // and never take over a crashed slot the same day; pending work stays in the journal.
            $existing = StorageMaintenanceRun::where(['kind' => 'daily', 'scope_key' => 'global', 'slot_key' => $slot])->first();
            if (!$existing) throw $e;
            $reason = in_array($existing->status, ['finished', 'partial'], true) ? 'already_completed'
                : ($existing->lease_expires_at && $existing->lease_expires_at->greaterThan($now) ? 'in_progress'
                    : 'crashed_slot_no_same_day_takeover');
            return ['status' => 'skipped', 'reason' => $reason, 'slot' => $slot, 'run_id' => $existing->uuid];
        }

        $maxOperations = max(1, (int) config('storage_maintenance.runner_max_operations', 5));
        $maxRequests = max(1, (int) config('storage_maintenance.runner_max_requests', 6));
        $softSeconds = max(1, (int) config('storage_maintenance.runner_soft_seconds', 4));
        $cursor = $this->cursor();
        $after = (int) ($cursor->counters['after_id'] ?? 0);
        $started = microtime(true);
        $counters = ['trigger' => $trigger, 'operations' => 0, 'requests' => 0, 'actions' => []];
        $stopped = null;
        foreach ($this->due($after, $maxOperations) as $op) {
            $remaining = $softSeconds - (microtime(true) - $started);
            if ($counters['requests'] >= $maxRequests) { $stopped = 'request_budget'; break; }
            if ($remaining < 1) { $stopped = 'time_budget'; break; }
            try {
                $result = $this->operations->recover($op, true, $this->inspection,
                    $maxRequests - $counters['requests'], (int) floor($remaining));
            } catch (\Throwable $e) {
                $result = ['action' => 'error', 'requests' => 0];
            }
            $counters['operations']++;
            $counters['requests'] += (int) ($result['requests'] ?? 0);
            $counters['actions'][$result['action']] = ($counters['actions'][$result['action']] ?? 0) + 1;
            // Persist progress after every unit so an aborted invocation resumes tomorrow.
            DB::table('storage_maintenance_runs')->where('id', $cursor->id)
                ->update(['counters' => json_encode(['after_id' => $op->id]), 'updated_at' => now()]);
            DB::table('storage_maintenance_runs')->where('id', $run->id)->where('lease_token', $token)
                ->update(['counters' => json_encode($counters), 'updated_at' => now()]);
        }
        if (!$stopped && $counters['operations'] >= $maxOperations) $stopped = 'operation_budget';
        DB::table('storage_maintenance_runs')->where('id', $run->id)->where('lease_token', $token)->update([
            'status' => $stopped ? 'partial' : 'finished', 'finished_at' => now(), 'lease_token' => null,
            'lease_expires_at' => null, 'counters' => json_encode($counters + ['stopped' => $stopped]), 'updated_at' => now()]);
        return ['status' => $stopped ? 'partial' : 'finished', 'slot' => $slot, 'run_id' => $run->uuid,
            'operations' => $counters['operations'], 'requests' => $counters['requests'],
            'actions' => $counters['actions'], 'stopped' => $stopped,
            'elapsed_seconds' => round(microtime(true) - $started, 3)];
    }

    /** Durable round-robin cursor across days for fairness between owners/operations. */
    private function cursor(): StorageMaintenanceRun
    {
        $attributes = ['kind' => 'cursor', 'scope_key' => 'global', 'slot_key' => 'recovery'];
        try {
            return StorageMaintenanceRun::firstOrCreate($attributes, ['uuid' => (string) Str::uuid(),
                'status' => 'cursor', 'counters' => ['after_id' => 0]]);
        } catch (QueryException $e) {
            return StorageMaintenanceRun::where($attributes)->firstOrFail();
        }
    }

    /** Due recorded operations, starting after the cursor and wrapping around. */
    private function due(int $after, int $limit)
    {
        $now = now();
        $query = fn () => StorageOperation::whereNotIn('state', array_merge(StorageOperation::TERMINAL, ['needs_review']))
            ->where(fn ($q) => $q->where('state', '!=', 'tombstoned')->orWhere('tombstone_until', '<=', $now))
            ->where(fn ($q) => $q->whereNull('not_before')->orWhere('not_before', '<=', $now))
            ->where(fn ($q) => $q->whereNull('lease_token')->orWhere('lease_expires_at', '<', $now))
            ->orderBy('id');
        $first = $query()->where('id', '>', $after)->limit($limit)->get();
        if ($first->count() >= $limit || $after === 0) return $first;
        return $first->concat($query()->where('id', '<=', $after)->limit($limit - $first->count())->get());
    }
}
