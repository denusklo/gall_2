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
        $domain = $request->input('domain');

        $result = $this->fcmTokenService->storeToken($uid, $token, $deviceInfo, $domain);

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

        $uid = $request->user()->firebase_uid;

        if (!$uid) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have Firebase UID'
            ], 400);
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
            'firebase_uid' => 'required|string',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:500'
        ]);

        $firebaseUid = $request->input('firebase_uid');
        $title = $request->input('title', 'Test Notification');
        $body = $request->input('body', 'This is a test notification from the admin panel');

        // Get current domain from request for filtering
        $domain = $request->getSchemeAndHttpHost();

        /** @var \App\Services\FcmTokenService */
        $fcmTokenService = app(FcmTokenService::class);

        // Get all tokens for this user
        $allTokens = $fcmTokenService->getUserTokens($firebaseUid);

        // Get tokens filtered by domain
        $domainTokens = $fcmTokenService->getUserTokensForDomain($firebaseUid, $domain);

        Log::info('[TEST NOTIFICATION] Preparing to send', [
            'firebase_uid' => $firebaseUid,
            'request_domain' => $domain,
            'all_tokens_count' => count($allTokens),
            'domain_tokens_count' => count($domainTokens),
            'all_tokens' => array_map(function($token) {
                return substr($token, 0, 30) . '...';
            }, $allTokens),
            'domain_tokens' => array_map(function($token) {
                return substr($token, 0, 30) . '...';
            }, $domainTokens)
        ]);

        // Check if user has any tokens for this domain
        if (empty($domainTokens)) {
            $message = empty($allTokens)
                ? 'User has no registered FCM tokens on any domain'
                : "User has " . count($allTokens) . " token(s) but none registered for domain: {$domain}";

            Log::warning('[TEST NOTIFICATION] No tokens for domain', [
                'firebase_uid' => $firebaseUid,
                'domain' => $domain,
                'all_tokens_count' => count($allTokens)
            ]);

            return response()->json([
                'success' => false,
                'message' => $message,
                'has_tokens' => false,
                'all_tokens_count' => count($allTokens),
                'domain_tokens_count' => 0,
                'requested_domain' => $domain
            ], 400);
        }

        /** @var \App\Services\FcmNotificationService */
        $fcmNotification = app(\App\Services\FcmNotificationService::class);

        $result = $fcmNotification->sendToUser(
            $firebaseUid,
            $title,
            $body,
            'info',
            ['type' => 'test_notification'],
            $domain
        );

        if ($result) {
            Log::info('[TEST NOTIFICATION] Sent successfully', [
                'firebase_uid' => $firebaseUid,
                'domain' => $domain,
                'tokens_sent' => count($domainTokens),
                'title' => $title
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test notification sent successfully to ' . count($domainTokens) . ' device(s) on ' . $domain,
                'domain' => $domain,
                'tokens_sent' => count($domainTokens),
                'tokens_preview' => array_map(function($token) {
                    return substr($token, 0, 30) . '...';
                }, $domainTokens)
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send test notification'
        ], 500);
    }
}
