<?php

namespace App\Services;

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\WebPushConfig;
use Illuminate\Support\Facades\Log;

class FcmNotificationService
{
    protected $messaging;

    public function __construct()
    {
        $this->messaging = app('firebase.messaging');
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
     * @param string $token FCM token of request creator
     * @param string $requestName Name of the request
     * @param string $completerName Name of person who completed
     * @return bool
     */
    public function notifyRequestCompleted($token, $requestName, $completerName = 'Someone')
    {
        return $this->sendToToken(
            $token,
            'Request Completed! 🎉',
            "\"{$requestName}\" has been completed by {$completerName}",
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
     * @param string $token FCM token of request creator
     * @param string $requestName Name of the request
     * @param int $completedCount Number of people completed
     * @param int $requiredCount Total required
     * @param string $completerName Name of person who just completed
     * @return bool
     */
    public function notifyNewCompletion($token, $requestName, $completedCount, $requiredCount, $completerName = 'Someone')
    {
        return $this->sendToToken(
            $token,
            'New Progress on Your Request 📈',
            "\"{$requestName}\" has {$completedCount}/{$requiredCount} completions. {$completerName} just helped!",
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
     * @param string $token FCM token of request creator
     * @param string $requestName Name of the request
     * @param int $totalCompleters Total number of completers
     * @return bool
     */
    public function notifyFullyCompleted($token, $requestName, $totalCompleters)
    {
        return $this->sendToToken(
            $token,
            'Request Fully Completed! ✅',
            "\"{$requestName}\" is now complete! {$totalCompleters} people have responded.",
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
     * @param string $token FCM token of completer
     * @param string $requestName Name of the request
     * @param int $completedCount Current number of completions
     * @param int $requiredCount Total required (0 for single-completer)
     * @return bool
     */
    public function notifyCompletionConfirmation($token, $requestName, $completedCount, $requiredCount)
    {
        if ($requiredCount <= 1) {
            // Single completer
            return $this->sendToToken(
                $token,
                'Thank You! 💪',
                "You've completed \"{$requestName}\". Great job!",
                [
                    'type' => 'completion_confirmation',
                    'request_name' => $requestName
                ]
            );
        } else {
            // Multi completer
            return $this->sendToToken(
                $token,
                'Thanks for Helping! 🙏',
                "You've completed \"{$requestName}\" ({$completedCount}/{$requiredCount} people).",
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
