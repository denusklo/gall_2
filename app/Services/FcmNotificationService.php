<?php

namespace App\Services;

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\WebPushConfig;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;

class FcmNotificationService
{
    protected $messaging;

    public function __construct()
    {
        $this->messaging = app('firebase.messaging');
    }

    /**
     * Save notification to database for a user
     *
     * @param string $firebaseUid Firebase UID
     * @param int|null $userId MySQL user ID (optional)
     * @param string $title Notification title
     * @param string $body Notification body
     * @param string $type Notification type
     * @param array $data Additional data
     * @return Notification
     */
    protected function saveNotification($firebaseUid, $userId, $title, $body, $type = 'info', $data = [])
    {
        return Notification::create([
            'user_id' => $userId,
            'firebase_uid' => $firebaseUid,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data
        ]);
    }

    /**
     * Send notification to a user (by Firebase UID)
     *
     * @param string $firebaseUid Firebase UID
     * @param string $title Notification title
     * @param string $body Notification body
     * @param string $type Notification type (info, success, warning, error)
     * @param array $data Additional data payload
     * @return bool
     */
    public function sendToUser($firebaseUid, $title, $body, $type = 'info', $data = [])
    {
        try {
            // Get user by Firebase UID
            $user = \App\Models\User::where('firebase_uid', $firebaseUid)->first();

            // Save notification to database (persists even if FCM fails or user offline)
            $this->saveNotification($firebaseUid, $user?->id, $title, $body, $type, $data);

            // Get FCM tokens for this user
            $fcmTokenService = app(FcmTokenService::class);
            $tokens = $fcmTokenService->getUserTokens($firebaseUid);

            if (empty($tokens)) {
                Log::info('No FCM tokens found for user, notification saved to DB only', [
                    'firebase_uid' => $firebaseUid
                ]);
                return true; // Still success since saved to DB
            }

            // Send FCM notification to all user's devices
            // Note: Using individual send() instead of sendMulticast() due to
            // Google deprecating the /batch endpoint that sendMulticast() uses
            $notification = FirebaseNotification::create($title, $body);

            $successCount = 0;
            $failCount = 0;

            foreach ($tokens as $token) {
                try {
                    $targetedMessage = CloudMessage::withTarget('token', $token)
                        ->withNotification($notification)
                        ->withData(array_merge($data, ['type' => $type]));

                    $this->messaging->send($targetedMessage);
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    Log::warning('Failed to send to specific token', [
                        'token' => substr($token, 0, 20) . '...',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('FCM notification sent to user', [
                'firebase_uid' => $firebaseUid,
                'success' => $successCount,
                'failures' => $failCount,
                'total_tokens' => count($tokens),
                'title' => $title
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send notification to user', [
                'firebase_uid' => $firebaseUid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send notification to a single device
     *
     * @param string $token FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return bool
     */
    public function sendToToken($token, $title, $body, $data = [])
    {
        try {
            $notification = FirebaseNotification::create($title, $body);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);

            Log::info('FCM notification sent', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title,
                'body' => $body
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('FCM notification failed', [
                'token' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send notification to multiple devices
     *
     * @param array $tokens Array of FCM tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return array Results with success/failure counts
     */
    public function sendToMultipleTokens($tokens, $title, $body, $data = [])
    {
        try {
            $notification = FirebaseNotification::create($title, $body);

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);

            $result = $this->messaging->sendMulticast($message, $tokens);

            Log::info('FCM multicast notification sent', [
                'success' => $result->successes()->count(),
                'failures' => $result->failures()->count(),
                'title' => $title
            ]);

            return [
                'success' => $result->successes()->count(),
                'failed' => $result->failures()->count(),
                'invalid_tokens' => $result->invalidTokens()
            ];
        } catch (\Exception $e) {
            Log::error('FCM multicast notification failed', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => 0,
                'failed' => count($tokens),
                'invalid_tokens' => []
            ];
        }
    }

    /**
     * Send notification to a topic
     *
     * @param string $topic Topic name
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return bool
     */
    public function sendToTopic($topic, $title, $body, $data = [])
    {
        try {
            $notification = FirebaseNotification::create($title, $body);

            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);

            Log::info('FCM topic notification sent', [
                'topic' => $topic,
                'title' => $title,
                'body' => $body
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('FCM topic notification failed', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Core notification: Request completed (single completer)
     * Sent to request creator when their single-completer request is completed
     *
     * @param string $firebaseUid Firebase UID of request creator
     * @param string $requestName Name of the request
     * @param string $completerName Name of person who completed
     * @return bool
     */
    public function notifyRequestCompleted($firebaseUid, $requestName, $completerName = 'Someone')
    {
        return $this->sendToUser(
            $firebaseUid,
            'Request Completed! 🎉',
            "\"{$requestName}\" has been completed by {$completerName}",
            'success',
            [
                'type' => 'request_completed',
                'request_name' => $requestName,
                'completer_name' => $completerName
            ]
        );
    }

    /**
     * Core notification: New completion received (multi-completer)
     * Sent to request creator when someone completes their multi-completer request
     *
     * @param string $firebaseUid Firebase UID of request creator
     * @param string $requestName Name of the request
     * @param int $completedCount Number of people completed
     * @param int $requiredCount Total required
     * @param string $completerName Name of person who just completed
     * @return bool
     */
    public function notifyNewCompletion($firebaseUid, $requestName, $completedCount, $requiredCount, $completerName = 'Someone')
    {
        return $this->sendToUser(
            $firebaseUid,
            'New Progress on Your Request 📈',
            "\"{$requestName}\" has {$completedCount}/{$requiredCount} completions. {$completerName} just helped!",
            'info',
            [
                'type' => 'new_completion',
                'request_name' => $requestName,
                'completed_count' => $completedCount,
                'required_count' => $requiredCount,
                'completer_name' => $completerName
            ]
        );
    }

    /**
     * Core notification: Fully completed (multi-completer)
     * Sent to request creator when multi-completer request reaches required number
     *
     * @param string $firebaseUid Firebase UID of request creator
     * @param string $requestName Name of the request
     * @param int $totalCompleters Total number of completers
     * @return bool
     */
    public function notifyFullyCompleted($firebaseUid, $requestName, $totalCompleters)
    {
        return $this->sendToUser(
            $firebaseUid,
            'Request Fully Completed! ✅',
            "\"{$requestName}\" is now complete! {$totalCompleters} people have responded.",
            'success',
            [
                'type' => 'fully_completed',
                'request_name' => $requestName,
                'total_completers' => $totalCompleters
            ]
        );
    }

    /**
     * Core notification: Completion confirmation
     * Sent to completer after they complete a request
     *
     * @param string $firebaseUid Firebase UID of completer
     * @param string $requestName Name of the request
     * @param int $completedCount Current number of completions
     * @param int $requiredCount Total required (0 for single-completer)
     * @return bool
     */
    public function notifyCompletionConfirmation($firebaseUid, $requestName, $completedCount, $requiredCount)
    {
        if ($requiredCount <= 1) {
            // Single completer
            return $this->sendToUser(
                $firebaseUid,
                'Thank You! 💪',
                "You've completed \"{$requestName}\". Great job!",
                'success',
                [
                    'type' => 'completion_confirmation',
                    'request_name' => $requestName
                ]
            );
        } else {
            // Multi completer
            return $this->sendToUser(
                $firebaseUid,
                'Thanks for Helping! 🙏',
                "You've completed \"{$requestName}\" ({$completedCount}/{$requiredCount} people).",
                'success',
                [
                    'type' => 'completion_confirmation',
                    'request_name' => $requestName,
                    'completed_count' => $completedCount,
                    'required_count' => $requiredCount
                ]
            );
        }
    }

    /**
     * Subscribe a token to a topic
     *
     * @param string $token FCM token
     * @param string $topic Topic name
     * @return bool
     */
    public function subscribeToTopic($token, $topic)
    {
        try {
            $this->messaging->subscribeToTopic($topic, $token);
            Log::info('FCM token subscribed to topic', [
                'topic' => $topic,
                'token' => substr($token, 0, 20) . '...'
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('FCM topic subscription failed', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate an FCM token
     *
     * @param string $token FCM token to validate
     * @return bool
     */
    public function validateToken($token)
    {
        try {
            // Send a test message with dry_run=true to validate token
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(FirebaseNotification::create('Test', 'Test'));

            $this->messaging->validate($message);

            return true;
        } catch (\Exception $e) {
            Log::warning('FCM token validation failed', [
                'token' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
