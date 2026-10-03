<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FcmTokenService;
use Illuminate\Http\Request;

class FcmController extends Controller
{
    protected $fcmTokenService;

    public function __construct(FcmTokenService $fcmTokenService)
    {
        $this->fcmTokenService = $fcmTokenService;
    }

    /**
     * Store or update FCM token for the authenticated user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string|max:4096',
            'device_info' => 'nullable|string|max:255',
            'domain' => 'nullable|string|max:255'
        ]);

        $user = $request->user();
        $uid = $user->firebase_uid;

        if (!$uid) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have Firebase UID. Please authenticate via Firebase.'
            ], 400);
        }

        $token = $request->input('token');
        $deviceInfo = $request->input('device_info');
        $origin = app(\App\Services\PushOrigin::class)->current();
        $submitted = $request->input('domain');
        if ($submitted !== null && app(\App\Services\PushOrigin::class)->normalize($submitted) !== $origin) {
            return response()->json(['success' => false, 'message' => 'Push origin mismatch'], 422);
        }
        $bearer = $this->bearer($request);
        $result = $this->fcmTokenService->register($uid, $token, (int) $bearer->getKey(), $deviceInfo, $origin);
        if (!$bearer->newQuery()->whereKey($bearer->getKey())->exists()) {
            abort(401);
        }
        return response()->json([
            'success' => true, 'message' => 'FCM token registered successfully',
            'generation' => $result['generation'],
        ]);
    }

    /**
     * Remove FCM token for the authenticated user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function removeToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string|max:4096',
            'generation' => 'required|string|size:32',
        ]);

        $uid = $request->user()->firebase_uid;

        if (!$uid) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have Firebase UID'
            ], 400);
        }

        $result = $this->fcmTokenService->removeForBearer(
            $uid, $request->input('token'), $request->input('generation'), (int) $this->bearer($request)->getKey()
        );

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'FCM token removed successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to remove FCM token'
        ], 500);
    }

    /**
     * Get all FCM tokens for the authenticated user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getTokens(Request $request)
    {
        $uid = $request->user()->firebase_uid;

        if (!$uid) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have Firebase UID'
            ], 400);
        }

        $tokens = $this->fcmTokenService->getUserTokens($uid);

        return response()->json([
            'success' => true,
            'data' => [
                'tokens' => $tokens,
                'count' => count($tokens)
            ]
        ]);
    }

    /**
     * Test notification endpoint (for development)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function testNotification(Request $request)
    {
        if (!config('app.debug')) {
            return response()->json([
                'success' => false,
                'message' => 'Test notifications are only available in debug mode'
            ], 403);
        }

        $request->validate([
            'token' => 'required|string|max:4096',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:500'
        ]);

        $token = $request->input('token');
        $title = $request->input('title', 'Test Notification');
        $body = $request->input('body', 'This is a test notification from the app');

        /** @var \App\Services\FcmNotificationService */
        $fcmNotification = app(\App\Services\FcmNotificationService::class);

        $uid = $request->user()->firebase_uid;
        if (!is_string($uid) || $uid === '') {
            abort(403);
        }
        $origin = app(\App\Services\PushOrigin::class)->current();
        $owned = array_filter($this->fcmTokenService->getRegistrations($uid, $origin),
            fn ($row) => $row['token'] === $token);
        if (!$owned) {
            return response()->json(['success' => false, 'message' => 'Owned token required'], 403);
        }
        $result = $fcmNotification->sendToRegistration($uid, array_values($owned)[0], $title, $body);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Provider accepted the test notification'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send test notification'
        ], 500);
    }

    /**
     * Send test notification to a user by Firebase UID
     * Sends to all devices/tokens registered for the user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendTestToUser(Request $request)
    {
        $request->validate([
            'firebase_uid' => 'nullable|string',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:500'
        ]);

        // Default target is the bearer caller. Targeting another user requires the
        // caller (identified only by the authenticated bearer user) to hold the
        // Firebase `admin` custom claim, verified against Firebase. Fails closed.
        $callerUid = $request->user()?->firebase_uid;
        if (!is_string($callerUid) || $callerUid === '') {
            return response()->json([
                'success' => false,
                'message' => 'Authenticated user has no Firebase UID'
            ], 403);
        }

        $requestedUid = $request->input('firebase_uid');
        $firebaseUid = $callerUid;

        if (is_string($requestedUid) && $requestedUid !== '' && $requestedUid !== $callerUid) {
            if (!$this->callerIsFirebaseAdmin($callerUid)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin privileges required to send notifications to other users'
                ], 403);
            }
            $firebaseUid = $requestedUid;
        }

        $title = $request->input('title', 'Test Notification');
        $body = $request->input('body', 'This is a test notification from the admin panel');

        // Fixed deployment origin, never body/header-selected environment targeting.
        $domain = app(\App\Services\PushOrigin::class)->current();
        $result = app(\App\Services\FcmNotificationService::class)->sendToUserDetailed(
            $firebaseUid, $title, $body, 'info', ['type' => 'test_notification'], $domain
        );
        $accepted = $result['provider_accepted'];
        return response()->json(array_merge($result, [
            'success' => $accepted > 0,
            'message' => $accepted > 0 ? 'Provider accepted the notification' : 'No push accepted by provider',
            'domain' => $domain,
            'tokens_sent' => $accepted, // Compatibility alias: acceptance, not delivery.
        ]), $accepted > 0 ? 200 : ($result['attempted'] > 0 ? 502 : 400));
    }

    /** Only persisted bearer authentication can resolve the server push binding. */
    private function bearer(Request $request): \Laravel\Sanctum\PersonalAccessToken
    {
        $bearer = $request->user()->currentAccessToken();
        if (!$bearer instanceof \Laravel\Sanctum\PersonalAccessToken
            || (int) $bearer->tokenable_id !== (int) $request->user()->getKey()
            || $bearer->tokenable_type !== $request->user()->getMorphClass()) {
            abort(403, 'Session-issued bearer required');
        }
        return $bearer;
    }

    /** Authoritative admin check, owner implies admin; failures deny. */
    protected function callerIsFirebaseAdmin(string $callerUid): bool
    {
        return app(\App\Services\FirebaseRoleService::class)->isAdmin($callerUid);
    }
}
