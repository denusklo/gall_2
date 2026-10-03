<?php

namespace App\Services;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Throwable;

class EmailVerificationService
{
    public function handle(Request $request, bool $send): JsonResponse
    {
        $uid = $request->session()->get('verified_user_id');
        $token = $request->session()->get('idTokenString');
        $local = $request->user();
        if (!is_string($uid) || $uid === '' || !is_string($token) || $token === '' || !$local || $local->firebase_uid !== $uid) {
            return $this->reply('unauthenticated', 401);
        }

        // Reject caller-controlled identity before spending the shared request budget.
        if (array_diff(array_keys($request->all()), ['_token'])) {
            return $this->reply('unexpected_fields', 422);
        }
        try {
            $retry = $this->reserve($uid, true);
        } catch (Throwable $e) {
            return $this->reply('limiter_unavailable', 503);
        }
        if ($retry > 0) {
            // No provider verdict exists yet. Do not report verified=false.
            return $this->reply('rate_limited', 429, ['rate_limit_scope' => 'request'], $retry);
        }

        $auth = app('firebase.auth');
        try {
            if ($auth->verifyIdToken($token, true)->claims()->get('sub') !== $uid) {
                return $this->reply('unauthenticated', 401);
            }
        } catch (Throwable $e) {
            return $this->reply('unauthenticated', 401);
        }

        try {
            $user = $auth->getUser($uid);
        } catch (UserNotFound $e) {
            return $this->reply('unauthenticated', 401);
        } catch (Throwable $e) {
            return $this->reply('provider_unavailable', 503);
        }
        if ($user->uid !== $uid || $user->disabled) {
            return $this->reply('unauthenticated', 401);
        }
        if (!$user->email) {
            return $this->reply('missing_email', 422);
        }

        $state = ['email' => $user->email, 'verified' => $user->emailVerified];
        try {
            $state['mirror'] = app(UserSyncService::class)->mirrorEmailVerification($uid, $user);
        } catch (Throwable $e) {
            // Retain the provider verdict, especially true: never suggest resending to fix SQL.
            return $this->reply('sync_failed', 503, $state);
        }

        if (!$send || $user->emailVerified) {
            return $this->reply($user->emailVerified ? 'verified' : 'unverified', 200, $state);
        }

        try {
            $retry = $this->reserve($uid);
        } catch (Throwable $e) {
            return $this->reply('limiter_unavailable', 503, $state);
        }
        if ($retry > 0) {
            return $this->reply('rate_limited', 429, $state + ['rate_limit_scope' => 'send'], $retry);
        }

        try {
            // Firebase's convenience API resolves this fresh email again internally.
            // External console email reassignment is NOT atomic with this UID lookup.
            // No ActionCodeSettings: use the configured Firebase hosted action handler.
            $auth->sendEmailVerificationLink($user->email);
        } catch (Throwable $e) {
            // Unknown delivery outcome. Keep BOTH reservations, including on quota/timeout.
            return $this->reply('send_failed', 503, $state, 60);
        }

        return $this->reply('sent', 200, $state, 60);
    }

    private function reserve(string $uid, bool $request = false): int
    {
        if (config('cache.stores.email_verification.driver') !== 'database') {
            throw new \RuntimeException('Shared database limiter required.');
        }
        $store = Cache::store('email_verification')->getStore();
        if (!$store instanceof DatabaseStore) {
            throw new \RuntimeException('Shared database limiter required.');
        }
        $connection = $store->getConnection();
        if ($store->getLockConnection() && $store->getLockConnection() !== $connection) {
            throw new \RuntimeException('Limiter lock must share its connection.');
        }
        // Raw PDO transactions do not increment Laravel's transaction counter.
        // Also refuse an enclosing default-connection transaction when the store
        // uses another connection: neither reads nor sends may precede its commit.
        foreach ([$connection, DB::connection()] as $candidate) {
            if ($candidate->transactionLevel() !== 0 || $candidate->getPdo()->inTransaction()) {
                throw new \RuntimeException('Limiter requires its own committed transaction.');
            }
        }
        $key = ($request ? 'request:uid:' : 'uid:').hash('sha256', $uid);
        $limit = $request ? 10 : 5;
        $window = $request ? 60 : 3600;

        // Acquire/release the native lock INSIDE the same DB transaction as the cache
        // reservation. The lock row write stays locked through commit even if its
        // lease expires; another process cannot steal it while counters are in flight.
        // No provider call runs in this transaction. Failed sends never refund it.
        return $connection->transaction(function () use ($store, $key, $request, $limit, $window) {
            $lock = $store->lock($key.':lock', 30);
            if (!$lock->get()) {
                return 1;
            }
            try {
                $now = now()->timestamp;
                $history = $store->get($key) ?? [];
                if (!is_array($history) || count($history) > $limit) {
                    throw new \RuntimeException('Invalid limiter state.');
                }
                foreach ($history as $time) {
                    if (!is_int($time)) {
                        throw new \RuntimeException('Invalid limiter state.');
                    }
                }
                $history = array_values(array_filter($history, fn ($time) => $time > $now - $window));
                sort($history);
                $retry = !$request && $history ? max(0, end($history) + 60 - $now) : 0;
                if (count($history) >= $limit) {
                    $retry = max($retry, $history[0] + $window - $now);
                }
                if ($retry > 0) {
                    return $retry;
                }
                $history[] = $now;
                if (!$store->put($key, $history, $window + 1)) {
                    throw new \RuntimeException('Reservation failed.');
                }
                return 0;
            } finally {
                if (!$lock->release()) {
                    throw new \RuntimeException('Limiter lock lost.');
                }
            }
        });
    }

    private function reply(string $outcome, int $status, array $state = [], int $retry = 0): JsonResponse
    {
        $response = response()->json(array_merge(['outcome' => $outcome, 'retry_after' => $retry], $state), $status)
            ->header('Cache-Control', 'no-store');
        if ($retry > 0) {
            $response->header('Retry-After', (string) $retry);
        }
        return $response;
    }
}
