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
     * @param string|null $domain Optional domain to filter tokens (e.g., https://example.com)
     * @return bool
     */
    public function sendToUser($firebaseUid, $title, $body, $type = 'info', $data = [], $domain = null)
    {
        $result = $this->sendToUserDetailed($firebaseUid, $title, $body, $type, $data, $domain);
        // Preserve history-only success for existing callers, but not all-failed push success.
        return $result['history_saved'] && ($result['attempted'] === 0 || $result['provider_accepted'] > 0)
            && !$result['authority_unavailable'];
    }

    public function sendToUserDetailed($firebaseUid, $title, $body, $type = 'info', $data = [], $domain = null): array
    {
        $result = ['history_saved' => false, 'attempted' => 0, 'provider_accepted' => 0,
            'failed' => 0, 'skipped' => 0, 'authority_unavailable' => false];
        try {
            $user = \App\Models\User::where('firebase_uid', $firebaseUid)->first();
            $this->saveNotification($firebaseUid, $user?->id, $title, $body, $type, $data);
            $result['history_saved'] = true;
        } catch (\Throwable $e) {
            Log::warning('Notification history unavailable', ['exception_class' => get_class($e)]);
            return $result;
        }
        try {
            // All-domain fanout is not an environment boundary. This deployment sends only its origin.
            $origin = app(PushOrigin::class)->current();
            if ($domain !== null && app(PushOrigin::class)->normalize($domain) !== $origin) {
                throw new \RuntimeException('Push origin mismatch');
            }
            $service = app(FcmTokenService::class);
            $rows = $service->getRegistrations($firebaseUid, $origin);
            foreach ($rows as $row) {
                $outcome = $this->attemptRegistration($firebaseUid, $row, $title, $body, array_merge($data, ['type' => $type]));
                if ($outcome === 'skipped' || $outcome === 'unavailable') {
                    $result['skipped']++;
                    $result['authority_unavailable'] = $result['authority_unavailable'] || $outcome === 'unavailable';
                    continue;
                }
                $result['attempted']++;
                $result[$outcome === 'accepted' ? 'provider_accepted' : 'failed']++;
            }
        } catch (\Throwable $e) {
            $result['authority_unavailable'] = true;
            Log::warning('Push send authority unavailable', ['exception_class' => get_class($e)]);
        }
        return $result;
    }

    public function sendToRegistration(string $uid, array $row, string $title, string $body, array $data = []): bool
    {
        return $this->attemptRegistration($uid, $row, $title, $body, $data) === 'accepted';
    }

    private function attemptRegistration(string $uid, array $row, string $title, string $body, array $data): string
    {
        try {
            $origin = app(PushOrigin::class)->current();
            $service = app(FcmTokenService::class);
            if ($service->registration($uid, $row['token'], $row['generation'], $origin) === null) {
                return 'skipped';
            }
        } catch (\Throwable $e) {
            Log::warning('Push send authority unavailable', ['exception_class' => get_class($e)]);
            return 'unavailable';
        }
        try {
            $message = CloudMessage::withTarget('token', $row['token'])
                ->withNotification(FirebaseNotification::create($title, $body))
                ->withData(array_merge($data, ['recipient_uid' => $uid]))
                ->withWebPushConfig(WebPushConfig::fromArray([
                    'fcm_options' => ['link' => $origin . '/images'],
                    'headers' => ['TTL' => '3600', 'Urgency' => 'high'],
                ]));
            // Accepted/queued messages can outlive this check and explicit logout.
            $this->messaging->send($message);
            return 'accepted';
        } catch (\Throwable $e) {
            Log::warning('Push not accepted', ['exception_class' => get_class($e)]);
            if (self::isUnregistered($e)) {
                try {
                    app(FcmTokenService::class)->removeToken($uid, $row['token'], $row['generation']);
                } catch (\Throwable $cleanup) {
                    Log::warning('Push retirement unavailable', ['exception_class' => get_class($cleanup)]);
                }
            }
            return 'failed';
        }
    }

    public static function isUnregistered(\Throwable $e): bool
    {
        if (!$e instanceof \Kreait\Firebase\Exception\MessagingException) {
            return false;
        }
        foreach ($e->errors()['error']['details'] ?? [] as $detail) {
            if (is_array($detail)
                && ($detail['@type'] ?? null) === 'type.googleapis.com/google.firebase.fcm.v1.FcmError'
                && ($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }
        return false;
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

            // Add WebPushConfig for Firefox support
            $webPushConfig = WebPushConfig::fromArray([
                'fcm_options' => [
                    'link' => url('/images')
                ],
                'headers' => [
                    'TTL' => '3600'
                ]
            ]);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data)
                ->withWebPushConfig($webPushConfig);

            $this->messaging->send($message);

            Log::info('FCM provider accepted notification');

            return true;
        } catch (\Exception $e) {
            Log::warning('FCM notification not accepted', ['exception_class' => get_class($e)]);
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
     * @param string|null $domain Optional domain to filter tokens
     * @return bool
     */
    public function notifyRequestCompleted($firebaseUid, $requestName, $completerName = 'Someone', $domain = null)
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
            ],
            $domain
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
     * @param string|null $domain Optional domain to filter tokens
     * @return bool
     */
    public function notifyNewCompletion($firebaseUid, $requestName, $completedCount, $requiredCount, $completerName = 'Someone', $domain = null)
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
            ],
            $domain
        );
    }

    /**
     * Core notification: Fully completed (multi-completer)
     * Sent to request creator when multi-completer request reaches required number
     *
     * @param string $firebaseUid Firebase UID of request creator
     * @param string $requestName Name of the request
     * @param int $totalCompleters Total number of completers
     * @param string|null $domain Optional domain to filter tokens
     * @return bool
     */
    public function notifyFullyCompleted($firebaseUid, $requestName, $totalCompleters, $domain = null)
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
            ],
            $domain
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
     * @param string|null $domain Optional domain to filter tokens
     * @return bool
     */
    public function notifyCompletionConfirmation($firebaseUid, $requestName, $completedCount, $requiredCount, $domain = null)
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
                ],
                $domain
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
                ],
                $domain
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
        return $this->validationStatus($token) === 'valid';
    }

    public function validationStatus(string $token): string
    {
        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(FirebaseNotification::create('Test', 'Test'));
            $this->messaging->validate($message);
            return 'valid';
        } catch (\Throwable $e) {
            return self::isUnregistered($e) ? 'unregistered' : 'unknown';
        }
    }
}
