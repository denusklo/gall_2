<?php

namespace App\Services;

use App\Exceptions\RoleChangeException;
use App\Models\RoleChangeAudit;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Throwable;

/**
 * The ONLY app code path that mutates Firebase account state that touches roles:
 * custom claims (admin/owner/volunteer), and profile update / delete of accounts
 * that may be privileged. (Verified by grep: setCustomUserClaims appears only here.)
 *
 * There is intentionally NO owner demotion/removal operation.
 *
 * Every operation: authorize -> DB lock (RoleMutationLock) -> refetch actor+target
 * UNDER the lock -> durable pending audit row -> lock->assertHeld() -> provider call
 * -> terminal audit row. Firebase and the DB are not atomic; a provider exception is
 * AMBIGUOUS (it may have applied), so it is reported as "cannot confirm", never as
 * "no change". A pending/failed row with NULL after_* means provider outcome unknown.
 */
class RoleChangeService
{
    public const SYSTEM_ACTOR = 'system:bootstrap-owner';
    public const CLI_ACTOR = 'system:cli-operator';
    public const REGISTRATION_ACTOR = 'system:failed-registration';

    public function __construct(
        private Auth $auth,
        private FirebaseRoleService $roles,
        private RoleMutationLock $lock,
    ) {
    }

    // ---------------------------------------------------------------- roles

    public function grantAdmin(?string $actorUid, string $targetUid): RoleChangeAudit
    {
        return $this->mutateAdmin('grant_admin', $actorUid, $targetUid, true);
    }

    public function revokeAdmin(?string $actorUid, string $targetUid): RoleChangeAudit
    {
        return $this->mutateAdmin('revoke_admin', $actorUid, $targetUid, false);
    }

    private function mutateAdmin(string $action, ?string $actorUid, string $targetUid, bool $grant): RoleChangeAudit
    {
        if (!$this->roles->isOwner($actorUid)) {
            throw RoleChangeException::denied();
        }
        if ($targetUid === '') {
            throw new RoleChangeException('Invalid target.', 422);
        }

        return $this->withLock(function () use ($action, $actorUid, $targetUid, $grant) {
            // Re-verify under the lock; never trust the earlier/UI state.
            if (!$this->roles->isOwner($actorUid)) {
                throw RoleChangeException::denied();
            }

            $claims = $this->readClaims($targetUid);
            $before = FirebaseRoleService::flagsFromClaims($claims);

            if ($before['owner']) {
                $this->recordRefusal($action, $actorUid, $targetUid, $before, 'target_is_owner');
                throw new RoleChangeException('Owners are protected and cannot be changed.', 422);
            }

            $after = ['admin' => $grant, 'owner' => false];
            $write = null; // already in desired state: no provider write
            if ($before['admin'] !== $grant) {
                if ($grant) {
                    $newClaims = array_merge($claims, ['admin' => true]);
                } else {
                    $newClaims = $claims;
                    unset($newClaims['admin']);
                }
                $write = fn () => $this->auth->setCustomUserClaims($targetUid, $newClaims);
            }

            return $this->commit('role change', $action, $actorUid, $targetUid, $before, $after, $write);
        });
    }

    /**
     * Promote the single configured bootstrap account. Idempotent.
     *
     * @return array{changed:bool, audit:?RoleChangeAudit}
     */
    public function bootstrapOwner(string $uid): array
    {
        return $this->withLock(function () use ($uid) {
            try {
                $user = $this->auth->getUser($uid);
            } catch (Throwable $e) {
                throw new RoleChangeException('Could not read the bootstrap user; no change made.', 502);
            }
            $this->assertBootstrapEligible($user);

            $claims = is_array($user->customClaims) ? $user->customClaims : [];
            $before = FirebaseRoleService::flagsFromClaims($claims);
            if ($before['owner']) {
                return ['changed' => false, 'audit' => null];
            }

            $newClaims = array_merge($claims, ['owner' => true, 'admin' => true]);
            $audit = $this->commit('role change', 'bootstrap_owner', self::SYSTEM_ACTOR, $user->uid, $before,
                ['admin' => true, 'owner' => true],
                fn () => $this->auth->setCustomUserClaims($user->uid, $newClaims));

            return ['changed' => true, 'audit' => $audit];
        });
    }

    /** Shared by the command's dry-run so both paths apply the same rules. */
    public function assertBootstrapEligible(UserRecord $user): void
    {
        $configured = strtolower(trim((string) config('roles.bootstrap_owner_email')));
        if ($configured === '' || strtolower((string) $user->email) !== $configured) {
            throw new RoleChangeException('Only the configured bootstrap account may be made owner.', 422);
        }
        if (!$user->emailVerified) {
            throw new RoleChangeException('Bootstrap account email is not verified.', 422);
        }
    }

    /**
     * Operator-only (CLI) volunteer claim change. Locked, refetched, merged, audited.
     * Preserves owner/admin and every other claim. Not reachable from the web.
     *
     * @return array{audit:RoleChangeAudit, claims:array}
     */
    public function setVolunteer(string $targetUid, bool $on): array
    {
        if ($targetUid === '') {
            throw new RoleChangeException('Invalid target.', 422);
        }

        return $this->withLock(function () use ($targetUid, $on) {
            $claims = $this->readClaims($targetUid);
            $before = FirebaseRoleService::flagsFromClaims($claims);

            $new = $claims;
            if ($on) {
                $new['volunteer'] = true;
            } else {
                unset($new['volunteer']);
            }
            $same = $on ? (($claims['volunteer'] ?? null) === true) : !array_key_exists('volunteer', $claims);
            $write = $same ? null : fn () => $this->auth->setCustomUserClaims($targetUid, $new);

            $audit = $this->commit('volunteer-claim change', $on ? 'set_volunteer' : 'remove_volunteer',
                self::CLI_ACTOR, $targetUid, $before, $before, $write);

            return ['audit' => $audit, 'claims' => $new];
        });
    }

    // ------------------------------------------------------- delete / profile

    /**
     * Delete an account. Policy (validated UNDER the lock from freshly read claims):
     * owners are never deletable (not by anyone, incl. themselves); an admin account may
     * be deleted only by an owner or by itself; ordinary accounts by themselves or any admin/owner.
     */
    public function deleteUser(?string $actorUid, string $targetUid, callable $delete): RoleChangeAudit
    {
        return $this->protectedOperation('delete', 'delete_user', $actorUid, $targetUid, $delete);
    }

    /**
     * Compensate only for a Firebase account just created by failed registration.
     * The caller supplies the createUser result UID, never a request body target/actor.
     * Unlike normal deletion, neither admins nor owners are eligible for cleanup.
     */
    public function cleanupFailedRegistration(string $createdUid): RoleChangeAudit
    {
        if ($createdUid === '') {
            throw new RoleChangeException('Invalid cleanup target.', 422);
        }

        return $this->withLock(function () use ($createdUid) {
            $before = FirebaseRoleService::flagsFromClaims($this->readClaims($createdUid));
            if ($before['admin'] || $before['owner']) {
                $this->recordRefusal('cleanup_failed_registration', self::REGISTRATION_ACTOR, $createdUid, $before, 'privileged_target');
                throw RoleChangeException::denied('Privileged accounts cannot be deleted by registration cleanup.');
            }

            return $this->commit('registration cleanup', 'cleanup_failed_registration', self::REGISTRATION_ACTOR,
                $createdUid, $before, null, fn () => $this->auth->deleteUser($createdUid));
        });
    }

    /**
     * Update an account's profile (name/phone: a sign-in identifier). Policy: an owner may be
     * edited only by that same owner; an admin account only by itself or an owner; ordinary
     * accounts by themselves or any admin/owner.
     */
    public function updateProfile(?string $actorUid, string $targetUid, callable $update): RoleChangeAudit
    {
        return $this->protectedOperation('update', 'update_profile', $actorUid, $targetUid, $update);
    }

    /** Unlocked read-only gate for the edit form (update() re-validates under the lock). */
    public function assertMayViewProfile(?string $actorUid, string $targetUid): void
    {
        $this->authorize('update_profile', $this->actorFlagsAndTarget($actorUid, $targetUid), (string) $actorUid, $targetUid);
    }

    private function protectedOperation(string $noun, string $action, ?string $actorUid, string $targetUid, callable $provider): RoleChangeAudit
    {
        if (!is_string($actorUid) || $actorUid === '') {
            throw RoleChangeException::denied('Authentication required.');
        }
        if ($targetUid === '') {
            throw new RoleChangeException('Invalid target.', 422);
        }

        return $this->withLock(function () use ($noun, $action, $actorUid, $targetUid, $provider) {
            $ctx = $this->actorFlagsAndTarget($actorUid, $targetUid);
            try {
                $this->authorize($action, $ctx, $actorUid, $targetUid);
            } catch (RoleChangeException $e) {
                $this->recordRefusal($action, $actorUid, $targetUid, $ctx['target'], 'policy_denied');
                throw $e;
            }

            return $this->commit($noun, $action, $actorUid, $targetUid, $ctx['target'], null, $provider);
        });
    }

    /** @return array{actor:array{admin:bool,owner:bool}, target:array{admin:bool,owner:bool}} */
    private function actorFlagsAndTarget(?string $actorUid, string $targetUid): array
    {
        if (!is_string($actorUid) || $actorUid === '') {
            throw RoleChangeException::denied('Authentication required.');
        }
        try {
            $actor = FirebaseRoleService::flagsFromClaims($this->roles->claimsFor($actorUid));
        } catch (Throwable $e) {
            throw new RoleChangeException('Could not verify your permissions; nothing was changed.', 503);
        }
        $target = $actorUid === $targetUid ? $actor : FirebaseRoleService::flagsFromClaims($this->readClaims($targetUid));

        return ['actor' => $actor, 'target' => $target];
    }

    private function authorize(string $action, array $ctx, string $actorUid, string $targetUid): void
    {
        $self = $actorUid === $targetUid;
        $a = $ctx['actor'];
        $t = $ctx['target'];
        $deny = fn (string $m) => new RoleChangeException($m, 403);

        if ($t['owner']) {
            if ($action === 'delete_user') {
                throw $deny('Owner accounts are protected and cannot be deleted.');
            }
            if (!($self && $a['owner'])) {
                throw $deny('Owner accounts can only be edited by that owner.');
            }

            return;
        }
        if ($self) {
            return; // own non-owner account: existing policy
        }
        if ($t['admin']) {
            if (!$a['owner']) {
                throw $deny('Admin accounts can only be changed by an owner.');
            }

            return;
        }
        if (!($a['owner'] || $a['admin'])) {
            throw $deny('Admin privileges required.');
        }
    }

    // ---------------------------------------------------------------- core

    /** @return array<string,mixed> */
    private function readClaims(string $uid): array
    {
        try {
            return $this->roles->claimsFor($uid);
        } catch (UserNotFound $e) {
            throw new RoleChangeException('User not found!', 404);
        } catch (Throwable $e) {
            throw new RoleChangeException('Could not read the target user; no change made.', 502);
        }
    }

    /**
     * Durable pending row -> lock probe -> provider call -> terminal row.
     * $write === null means "no provider call needed". $after === null means this is not a
     * flag change, so no after_* are stored (also what an unknown outcome looks like).
     */
    private function commit(string $noun, string $action, string $actorUid, string $targetUid, array $before, ?array $after, ?callable $write): RoleChangeAudit
    {
        if ($actorUid === '' || $targetUid === '') {
            throw new RoleChangeException('Audit requires an actor and a target; no change was made.', 422);
        }

        try {
            // An enclosing transaction would hide/roll back the intent. Fail closed.
            $connection = (new RoleChangeAudit)->getConnection();
            if ($connection->transactionLevel() !== 0 || $connection->getPdo()->inTransaction()) {
                throw new \RuntimeException('audit connection has an active transaction');
            }
            // Not in a transaction: this row must survive any later failure.
            $audit = RoleChangeAudit::create([
                'actor_uid' => $actorUid,
                'target_uid' => $targetUid,
                'action' => $action,
                'before_admin' => $before['admin'],
                'before_owner' => $before['owner'],
                'status' => RoleChangeAudit::PENDING,
            ]);
        } catch (Throwable $e) {
            Log::error('Role audit unavailable; no provider write attempted', ['action' => $action, 'exception' => get_class($e)]);
            throw new RoleChangeException('Audit log unavailable; no change was made.', 503);
        }

        if ($write !== null) {
            try {
                $this->lock->assertHeld(); // immediately before the provider mutation
            } catch (RoleChangeException $e) {
                try {
                    $audit->update(['status' => RoleChangeAudit::FAILED, 'reason' => 'lock_lost']);
                } catch (Throwable $e2) {
                }
                throw $e; // nothing was sent: "no change" is accurate here
            }

            try {
                $write();
            } catch (Throwable $e) {
                // AMBIGUOUS: a timeout/network error can occur after Firebase applied the call.
                // Never claim "no change"; after_* stay NULL = provider outcome unknown.
                Log::warning('Provider call threw; outcome unknown', ['audit_id' => $audit->id, 'action' => $action, 'exception' => get_class($e)]);
                $msg = "Could not confirm the provider outcome of this {$noun}; verify the account manually.";
                try {
                    $audit->update(['status' => RoleChangeAudit::FAILED, 'reason' => 'provider_error']);
                } catch (Throwable $e2) {
                    Log::error('Role audit could not be marked failed; pending row retained', ['audit_id' => $audit->id]);
                    $msg .= ' The audit record was left pending.';
                }
                throw new RoleChangeException($msg, 502, true, $audit->id);
            }
        }

        try {
            $audit->update([
                'status' => RoleChangeAudit::SUCCEEDED,
                'after_admin' => $after['admin'] ?? null,
                'after_owner' => $after['owner'] ?? null,
                'reason' => $write === null ? 'no_change' : null,
            ]);
        } catch (Throwable $e) {
            Log::error('Provider call done but audit finalisation failed; pending row retained', ['audit_id' => $audit->id]);
            throw new RoleChangeException(
                'The change was applied at the provider, but its audit record could not be finalised (left pending). Verify manually.',
                500, true, $audit->id
            );
        }

        return $audit;
    }

    private function recordRefusal(string $action, string $actorUid, string $targetUid, array $before, string $reason): void
    {
        try {
            RoleChangeAudit::create([
                'actor_uid' => $actorUid, 'target_uid' => $targetUid, 'action' => $action,
                'before_admin' => $before['admin'], 'before_owner' => $before['owner'],
                'after_admin' => $before['admin'], 'after_owner' => $before['owner'],
                'status' => RoleChangeAudit::FAILED, 'reason' => $reason,
            ]);
        } catch (Throwable $e) {
            // Best effort: a refusal performs no provider write.
        }
    }

    private function withLock(callable $fn)
    {
        return $this->lock->run($fn);
    }
}
