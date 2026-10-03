<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Database\TransactionFailed;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FcmTokenService
{
    protected $database;

    public function __construct($database = null)
    {
        // Lazy resolution lets ordinary bearer issuance survive push outages.
        $this->database = $database;
    }

    protected function db()
    {
        return $this->database ??= app('firebase.database');
    }

    private function guarded(callable $operation)
    {
        try {
            return $operation();
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Push authority unavailable', ['exception_class' => get_class($e)]);
            throw new HttpException(503, 'Push registration authority unavailable');
        }
    }

    private function read(string $path): ?array
    {
        $value = $this->db()->getReference($path)->getSnapshot()->getValue();
        if ($value !== null && !is_array($value)) {
            throw new \RuntimeException('Malformed push authority');
        }
        return $value;
    }

    // Kreait conditional writes are per-reference, not multi-path transactions.
    private function change(string $path, callable $mutate): array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return $this->db()->runTransaction(function ($transaction) use ($path, $mutate) {
                    $ref = $this->db()->getReference($path);
                    $old = $transaction->snapshot($ref)->getValue();
                    if ($old !== null && !is_array($old)) {
                        throw new \RuntimeException('Malformed push authority');
                    }
                    $next = $mutate($old);
                    if ($next !== null) {
                        $transaction->set($ref, $next);
                    }
                    return $next ?? [];
                });
            } catch (TransactionFailed $e) {
                if ($attempt === 2) {
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('Push authority contention');
    }

    private function validSession(?array $session, string $uid): bool
    {
        return $session !== null && ($session['uid'] ?? null) === $uid
            && ($session['state'] ?? null) === 'active';
    }

    private function bearerPath(int $bearerId): string
    {
        // Separate deployments may use separate SQL databases with overlapping token IDs.
        return 'push_bearers/' . hash('sha256', app(PushOrigin::class)->current()) . '/' . $bearerId;
    }

    public function bindBearer(string $uid, string $sessionId, int $bearerId): void
    {
        $this->guarded(function () use ($uid, $sessionId, $bearerId) {
            $this->change('push_sessions/' . $sessionId, function ($old) use ($uid) {
                if ($old !== null && !$this->validSession($old, $uid)) {
                    throw new HttpException(409, 'Push session retired');
                }
                return $old ?? ['uid' => $uid, 'state' => 'active'];
            });
            $this->change($this->bearerPath($bearerId), function ($old) use ($uid, $sessionId) {
                if ($old !== null && ($old['uid'] ?? null) !== $uid) {
                    throw new \RuntimeException('Push bearer owner mismatch');
                }
                if ($old !== null && ($old['session_id'] ?? null) !== $sessionId) {
                    throw new \RuntimeException('Push bearer session mismatch');
                }
                return ['uid' => $uid, 'session_id' => $sessionId];
            });
            if (!$this->validSession($this->read('push_sessions/' . $sessionId), $uid)) {
                throw new HttpException(409, 'Push session retired');
            }
        });
    }

    public function revokeSession(string $uid, string $sessionId): void
    {
        $this->guarded(function () use ($uid, $sessionId) {
            $this->change('push_sessions/' . $sessionId, function ($old) use ($uid) {
                if ($old !== null && ($old['uid'] ?? null) !== $uid) {
                    throw new \RuntimeException('Push session owner mismatch');
                }
                return ['uid' => $uid, 'state' => 'revoked'];
            });
        });
    }

    public function forgetBearer(string $uid, int $bearerId): void
    {
        $this->guarded(function () use ($uid, $bearerId) {
            // Bounded cleanup of one mapping. Tombstone prevents late reattachment.
            $this->change($this->bearerPath($bearerId), function ($old) use ($uid) {
                if ($old !== null && ($old['uid'] ?? null) !== $uid) {
                    throw new \RuntimeException('Push bearer owner mismatch');
                }
                return ['uid' => $uid, 'state' => 'revoked'];
            });
        });
    }

    private function sessionForBearer(string $uid, int $bearerId): string
    {
        $map = $this->read($this->bearerPath($bearerId));
        $id = $map['session_id'] ?? null;
        if (($map['uid'] ?? null) !== $uid || !is_string($id)
            || !$this->validSession($this->read('push_sessions/' . $id), $uid)) {
            throw new HttpException(503, 'Refresh authentication before registering push');
        }
        return $id;
    }

    public function register(string $uid, string $token, int $bearerId, ?string $deviceInfo, string $origin): array
    {
        return $this->guarded(function () use ($uid, $token, $bearerId, $deviceInfo, $origin) {
            $sessionId = $this->sessionForBearer($uid, $bearerId);
            $hash = hash('sha256', $token);
            $record = $this->change('fcm_token_owners/' . $hash, function ($old) use ($uid, $token, $sessionId, $origin, $deviceInfo) {
                if ($old !== null && (($old['uid'] ?? null) !== $uid
                    || ($old['session_id'] ?? null) !== $sessionId
                    || ($old['state'] ?? null) !== 'active'
                    || ($old['origin'] ?? null) !== $origin)) {
                    throw new HttpException(409, 'Retire this SDK token and explicitly register a new token');
                }
                return [
                    'uid' => $uid, 'token' => $token, 'session_id' => $sessionId,
                    'origin' => $origin, 'state' => 'active',
                    'generation' => $old['generation'] ?? bin2hex(random_bytes(16)),
                    'device_info' => $deviceInfo,
                ];
            });
            // Index is not authority. An interrupted update cannot authorize delivery.
            $this->db()->getReference('users/' . $uid . '/fcm_tokens/' . $this->sanitizeTokenKey($token))->set([
                'token' => $token, 'domain' => $origin, 'generation' => $record['generation'],
            ]);
            if (!$this->validSession($this->read('push_sessions/' . $sessionId), $uid)
                || $this->registration($uid, $token, $record['generation'], $origin) === null) {
                throw new HttpException(409, 'Push registration retired');
            }
            return $record;
        });
    }

    // Compatibility entry point deliberately refuses registrations without server binding.
    public function storeToken($uid, $token, $deviceInfo = null, $domain = null)
    {
        return false;
    }

    public function registration(string $uid, string $token, string $generation, ?string $origin = null): ?array
    {
        return $this->guarded(function () use ($uid, $token, $generation, $origin) {
            $row = $this->read('fcm_token_owners/' . hash('sha256', $token));
            if (($row['uid'] ?? null) !== $uid || ($row['token'] ?? null) !== $token
                || ($row['state'] ?? null) !== 'active' || ($row['generation'] ?? null) !== $generation
                || ($origin !== null && ($row['origin'] ?? null) !== $origin)
                || !is_string($row['session_id'] ?? null)
                || !$this->validSession($this->read('push_sessions/' . $row['session_id']), $uid)) {
                return null;
            }
            return $row;
        });
    }

    public function getRegistrations(string $uid, ?string $origin = null): array
    {
        return $this->guarded(function () use ($uid, $origin) {
            $rows = $this->read('users/' . $uid . '/fcm_tokens') ?? [];
            $result = [];
            foreach ($rows as $row) {
                if (!is_array($row) || !is_string($row['token'] ?? null)
                    || !is_string($row['generation'] ?? null)) {
                    continue; // Legacy records require authenticated reenrollment.
                }
                $current = $this->registration($uid, $row['token'], $row['generation'], $origin);
                if ($current !== null) {
                    $result[hash('sha256', $row['token'])] = $current;
                }
            }
            return array_values($result);
        });
    }

    public function getUserTokens($uid)
    {
        return array_column($this->getRegistrations($uid), 'token');
    }

    public function getUserTokensForDomain($uid, $domain)
    {
        $origin = app(PushOrigin::class)->normalize($domain);
        return $origin === null ? [] : array_column($this->getRegistrations($uid, $origin), 'token');
    }

    public function getPrimaryToken($uid)
    {
        return $this->getUserTokens($uid)[0] ?? null;
    }

    public function removeToken($uid, $token, $generation = null, $sessionId = null)
    {
        if (!is_string($generation) || $generation === '') {
            return false; // Never let token-only delayed deletion revoke a new registration.
        }
        return $this->guarded(function () use ($uid, $token, $generation, $sessionId) {
            $this->change('fcm_token_owners/' . hash('sha256', $token), function ($old) use ($uid, $generation, $sessionId) {
                if ($old === null) {
                    return null; // Unknown token: no authority claim from a DELETE.
                }
                if (($old['uid'] ?? null) !== $uid || ($old['generation'] ?? null) !== $generation
                    || ($sessionId !== null && ($old['session_id'] ?? null) !== $sessionId)) {
                    return $old; // Stale or foreign DELETE is an idempotent no-op.
                }
                $old['state'] = 'revoked';
                return $old;
            });
            return true;
        });
    }

    public function removeForBearer(string $uid, string $token, string $generation, int $bearerId): bool
    {
        return $this->guarded(fn () => $this->removeToken($uid, $token, $generation, $this->sessionForBearer($uid, $bearerId)));
    }

    public function clearAllTokens($uid)
    {
        return false; // Logout is session-scoped, never user-wide.
    }

    public function cleanUpOldTokens($uid = null)
    {
        return ['cleaned' => 0, 'users' => 0]; // Legacy reenrollment, not destructive migration.
    }

    public function cleanUpDuplicateTokens($uid)
    {
        // Token-key identity already deduplicates normal registration. A hostname is not a device.
        return ['cleaned' => 0];
    }

    private function sanitizeTokenKey($token)
    {
        return hash('sha256', $token);
    }

    public function hasTokens($uid)
    {
        return !empty($this->getUserTokens($uid));
    }
}
