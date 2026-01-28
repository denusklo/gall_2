<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FcmTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            'token' => 'required|string',
            'device_info' => 'nullable|string|max:255'
        ]);

        // Get Firebase UID from session or use authenticated user's firebase_uid
        $uid = session()->get('verified_user_id');
        $user = $request->user();

        // If no Firebase session, check if Sanctum user has firebase_uid
        if (!$uid && $user) {
            $uid = $user->firebase_uid;

            // If Sanctum user doesn't have firebase_uid, create Firebase user
            if (!$uid) {
                try {
                    $auth = app('firebase.auth');

                    // Create Firebase user with same email
                    $firebaseUser = $auth->createUser([
                        'email' => $user->email,
                        'displayName' => $user->name,
                        'emailVerified' => $user->email_verified_at !== null,
                    ]);

                    $uid = $firebaseUser->uid;

                    // Update MySQL user with firebase_uid
                    $user->firebase_uid = $uid;
                    $user->auth_provider = $user->auth_provider === 'sanctum' ? 'both' : $user->auth_provider;
                    $user->save();

                    Log::info('Created Firebase user for Sanctum-only user', [
                        'user_id' => $user->id,
                        'firebase_uid' => $uid
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create Firebase user for FCM', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to initialize Firebase for user: ' . $e->getMessage()
                    ], 500);
                }
            }
        }

        if (!$uid) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $token = $request->input('token');
        $deviceInfo = $request->input('device_info');

        $result = $this->fcmTokenService->storeToken($uid, $token, $deviceInfo);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'FCM token registered successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to register FCM token'
        ], 500);
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
            'token' => 'required|string'
        ]);

        $uid = session()->get('verified_user_id');

        if (!$uid && $request->user()) {
            $uid = $request->user()->firebase_uid;
        }

        if (!$uid) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or Firebase UID not found'
            ], 401);
        }

        $result = $this->fcmTokenService->removeToken($uid, $request->input('token'));

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
        $uid = session()->get('verified_user_id');

        if (!$uid && $request->user()) {
            $uid = $request->user()->firebase_uid;
        }

        if (!$uid) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or Firebase UID not found'
            ], 401);
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
            'token' => 'required|string',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:500'
        ]);

        $token = $request->input('token');
        $title = $request->input('title', 'Test Notification');
        $body = $request->input('body', 'This is a test notification from the app');

        /** @var \App\Services\FcmNotificationService */
        $fcmNotification = app(\App\Services\FcmNotificationService::class);

        $result = $fcmNotification->sendToToken($token, $title, $body);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Test notification sent successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send test notification'
        ], 500);
    }
}
