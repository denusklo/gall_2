<?php

namespace App\Services;

use App\Exceptions\RoleChangeException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cross-instance mutual exclusion for role mutations (grant/revoke/bootstrap/
 * protected delete), using the shared database instead of per-host cache.
 *
 * A DEDICATED connection (same server/credentials as the default one, cloned at
 * runtime) opens a transaction and runs `SELECT ... FOR UPDATE` on the single
 * seeded row of `role_mutation_lock`. Normal audit writes use the default
 * connection, so they commit independently of this transaction: the lock
 * transaction is only ever rolled back (it writes nothing), and a rollback or
 * crash cannot erase a pending audit row.
 *
 * Release: the finally block rolls back and purges the lock connection; if the
 * PHP process dies, MySQL drops the session and rolls the transaction back, so
 * the lock cannot leak. A waiter gives up after lock_wait_seconds (MySQL
 * innodb_lock_wait_timeout / pgsql lock_timeout) and FAILS CLOSED.
 *
 * Portability: real exclusion needs row locks (mysql/mariadb/pgsql). SQLite
 * has no FOR UPDATE and is refused unless roles.lock_unsafe_test_mode is set.
 * Out of scope: changes made directly in the Firebase console.
 */
class RoleMutationLock
{
    private $conn = null;
    private $pdo = null;
    private bool $held = false;
    private bool $heldShared = false;

    public function run(callable $fn)
    {
        $name = $this->prepareConnectionName();
        $shared = $name === config('database.default');
        // Unsafe mode is honoured ONLY in the testing environment, whatever the config says.
        $unsafe = (bool) config('roles.lock_unsafe_test_mode') && app()->environment('testing');

        if ($shared && !$unsafe) {
            throw $this->unavailable('lock connection must differ from the default connection');
        }

        $conn = null;
        $began = false;
        try {
            $conn = DB::connection($name);
            $driver = $conn->getDriverName();
            if (!in_array($driver, ['mysql', 'mariadb', 'pgsql'], true) && !$unsafe) {
                throw new \RuntimeException('driver without row locks');
            }
            $wait = max(1, (int) config('roles.lock_wait_seconds', 5));
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $conn->statement('SET SESSION innodb_lock_wait_timeout = ' . $wait);
            }
            if (!$shared) {
                $conn->beginTransaction();
                $began = true;
                if ($driver === 'pgsql') {
                    $conn->statement("SET LOCAL lock_timeout = '" . $wait . "s'");
                }
            }
            $row = $conn->table('role_mutation_lock')->where('id', 1)->lockForUpdate()->first();
            if (!$row) {
                throw new \RuntimeException('lock row missing');
            }
        } catch (Throwable $e) {
            $this->release($conn, $name, $began, $shared);
            throw $this->unavailable(get_class($e));
        }

        $this->conn = $conn;
        $this->pdo = $conn->getPdo();
        $this->heldShared = $shared;
        $this->held = true;

        try {
            return $fn();
        } finally {
            $this->held = false;
            $this->conn = $this->pdo = null;
            $this->release($conn, $name, $began, $shared);
        }
    }

    /**
     * Call IMMEDIATELY before any provider mutation. Proves, without letting Laravel
     * reconnect, that the lock session is the same live one that acquired FOR UPDATE:
     * same raw PDO handle, transaction still open, and a raw SELECT 1 succeeds.
     * Fails closed (503, nothing sent) otherwise. A session can still die right AFTER
     * this probe; that residual window is an inherent limit of a networked lock.
     */
    public function assertHeld(): void
    {
        try {
            if (!$this->held || $this->conn === null || $this->pdo === null) {
                throw new \RuntimeException('no active lock');
            }
            if ($this->conn->getRawPdo() !== $this->pdo) {
                throw new \RuntimeException('lock session replaced');
            }
            if (!$this->heldShared && ($this->conn->transactionLevel() !== 1 || !$this->pdo->inTransaction())) {
                throw new \RuntimeException('lock transaction not open');
            }
            $stmt = $this->pdo->query('SELECT 1');
            if ($stmt === false || (string) $stmt->fetchColumn() !== '1') {
                throw new \RuntimeException('probe failed');
            }
        } catch (Throwable $e) {
            Log::error('Role mutation lock lost before provider call; failing closed', ['why' => get_class($e)]);
            throw new RoleChangeException('The role-change lock was lost; nothing was sent to the provider.', 503);
        }
    }

    private function prepareConnectionName(): string
    {
        $name = config('roles.lock_connection') ?: 'role_lock';
        if (config("database.connections.$name") === null) {
            $default = config('database.default');
            config(["database.connections.$name" => config("database.connections.$default")]);
        }

        return $name;
    }

    private function release($conn, string $name, bool $began, bool $shared): void
    {
        if ($shared) {
            return; // never touch the default connection
        }
        try {
            if ($conn && $began) {
                $conn->rollBack();
            }
        } catch (Throwable $e) {
        }
        try {
            DB::purge($name);
        } catch (Throwable $e) {
        }
    }

    private function unavailable(string $why): RoleChangeException
    {
        Log::error('Role mutation lock unavailable; failing closed', ['why' => $why]);

        return new RoleChangeException('Could not obtain the role-change lock; no change was made.', 503);
    }
}
