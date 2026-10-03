<?php

namespace App\Services;

use Kreait\Firebase\Contract\Auth;
use Throwable;

/**
 * Authoritative role checks. Always re-reads the Firebase user; any failure
 * (empty uid, provider error, malformed claims) denies.
 */
class FirebaseRoleService
{
    public function __construct(private Auth $auth)
    {
    }

    /** @return array<string,mixed> @throws Throwable */
    public function claimsFor(string $uid): array
    {
        $claims = $this->auth->getUser($uid)->customClaims;

        return is_array($claims) ? $claims : [];
    }

    public function isOwner(?string $uid): bool
    {
        return $this->flags($uid)['owner'];
    }

    /** Owner implies admin. */
    public function isAdmin(?string $uid): bool
    {
        $f = $this->flags($uid);

        return $f['owner'] || $f['admin'];
    }

    /** @return array{admin:bool, owner:bool} strict === true only */
    public static function flagsFromClaims(array $claims): array
    {
        return [
            'admin' => ($claims['admin'] ?? null) === true,
            'owner' => ($claims['owner'] ?? null) === true,
        ];
    }

    /** @return array{admin:bool, owner:bool} */
    private function flags(?string $uid): array
    {
        if (!is_string($uid) || $uid === '') {
            return ['admin' => false, 'owner' => false];
        }
        try {
            return self::flagsFromClaims($this->claimsFor($uid));
        } catch (Throwable $e) {
            return ['admin' => false, 'owner' => false];
        }
    }
}
