<?php

namespace App\Console\Commands;

use App\Exceptions\RoleChangeException;
use App\Services\FirebaseRoleService;
use App\Services\RoleChangeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Kreait\Firebase\Contract\Auth;
use Throwable;

class BootstrapOwner extends Command
{
    protected $signature = 'roles:bootstrap-owner
        {--apply : Actually write the owner claim (default is a read-only dry run)}
        {--confirm-email= : Must equal the configured bootstrap email when using --apply}';

    protected $description = 'Promote the single configured bootstrap account to owner (dry run by default).';

    public function handle(Auth $auth, RoleChangeService $changes): int
    {
        $email = strtolower(trim((string) config('roles.bootstrap_owner_email')));
        if ($email === '') {
            $this->error('No bootstrap owner email configured.');
            return self::FAILURE;
        }

        try {
            $user = $auth->getUserByEmail($email); // read-only
        } catch (Throwable $e) {
            $this->error('Could not look up the bootstrap account (not found or provider error).');
            return self::FAILURE;
        }

        try {
            $changes->assertBootstrapEligible($user);
        } catch (RoleChangeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $claims = is_array($user->customClaims) ? $user->customClaims : [];
        $flags = FirebaseRoleService::flagsFromClaims($claims);
        $this->line("Account: {$email} (uid {$user->uid})");
        $this->line('Current: owner=' . ($flags['owner'] ? 'yes' : 'no') . ' admin=' . ($flags['admin'] ? 'yes' : 'no')
            . ' other claim keys preserved: ' . count(array_diff_key($claims, ['owner' => 1, 'admin' => 1])));

        if ($flags['owner']) {
            $this->info('Already an owner. No change.');
            return self::SUCCESS;
        }

        if (!$this->option('apply')) {
            $this->info('DRY RUN: would set owner=true, admin=true (other claims merged, not overwritten). Nothing written.');
            return self::SUCCESS;
        }

        if (strtolower(trim((string) $this->option('confirm-email'))) !== $email) {
            $this->error('--apply requires --confirm-email=' . $email);
            return self::FAILURE;
        }

        try {
            if (!Schema::hasTable('role_change_audits')) {
                $this->error('role_change_audits table is missing (migrate first). No change made.');
                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->error('Audit database unavailable. No change made.');
            return self::FAILURE;
        }

        try {
            $result = $changes->bootstrapOwner($user->uid);
        } catch (RoleChangeException $e) {
            $this->error($e->getMessage() . ($e->partial ? ' [PARTIAL: verify claims manually]' : ''));
            return self::FAILURE;
        }

        $this->info($result['changed'] ? 'Owner claim applied (audit #' . $result['audit']->id . ').' : 'Already an owner. No change.');
        return self::SUCCESS;
    }
}
