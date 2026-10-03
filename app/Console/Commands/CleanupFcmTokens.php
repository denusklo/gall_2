<?php

namespace App\Console\Commands;

use App\Services\FcmTokenService;
use Illuminate\Console\Command;

class CleanupFcmTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:cleanup
                            {--uid= : Specific Firebase UID to clean up (optional, default: all users)}
                            {--duplicates : Compatibility no-op; distinct devices are preserved}
                            {--old : Compatibility no-op; legacy records require reenrollment}
                            {--invalid : Validate and remove invalid tokens}
                            {--all : Run all cleanup operations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up FCM tokens (old tokens without domain, duplicates, etc.)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $fcmTokenService = app(FcmTokenService::class);
        $fcmNotificationService = app(\App\Services\FcmNotificationService::class);

        $uid = $this->option('uid');
        $cleanOld = $this->option('old') || $this->option('all');
        $cleanDuplicates = $this->option('duplicates') || $this->option('all');
        $cleanInvalid = $this->option('invalid') || $this->option('all');

        // If no specific option is provided, default to cleaning old tokens
        if (!$cleanOld && !$cleanDuplicates && !$cleanInvalid) {
            $cleanOld = true;
        }

        $this->info('Starting FCM token cleanup...');

        if ($uid) {
            $this->info("Cleaning tokens for user: {$uid}");
        } else {
            $this->info('Cleaning tokens for ALL users');
        }

        $totalCleaned = 0;

        // Clean up old tokens (without domain)
        if ($cleanOld) {
            $this->line("\n" . str_repeat('-', 50));
            $this->info('Cleaning old tokens (without domain information)...');

            $result = $fcmTokenService->cleanUpOldTokens($uid);

            $this->line("Users processed: {$result['users']}");
            $this->line("Tokens cleaned: {$result['cleaned']}");

            $totalCleaned += $result['cleaned'];
        }

        // Clean up duplicate tokens
        if ($cleanDuplicates) {
            $this->line("\n" . str_repeat('-', 50));

            if ($uid) {
                $this->info('Cleaning duplicate tokens for user...');
                $result = $fcmTokenService->cleanUpDuplicateTokens($uid);
                $this->line("Duplicate tokens cleaned: {$result['cleaned']}");
                $totalCleaned += $result['cleaned'];
            } else {
                $this->warn('Duplicate cleanup requires a specific user UID (--uid option)');
                $this->line('Skipping duplicate cleanup for all users (performance protection)');
            }
        }

        // Clean up invalid tokens
        if ($cleanInvalid) {
            $this->line("\n" . str_repeat('-', 50));

            if ($uid) {
                $this->info('Validating and removing invalid tokens for user...');
                $result = $this->cleanInvalidTokens($uid, $fcmTokenService, $fcmNotificationService);
                $this->line("Invalid tokens removed: {$result['cleaned']}");
                $totalCleaned += $result['cleaned'];
            } else {
                $this->warn('Invalid token validation requires a specific user UID (--uid option)');
                $this->line('Skipping invalid token cleanup for all users (performance protection)');
            }
        }

        $this->line("\n" . str_repeat('-', 50));
        $this->info("Total tokens cleaned: {$totalCleaned}");
        $this->line('Cleanup completed!');

        return 0;
    }

    /**
     * Validate and remove invalid FCM tokens for a user
     *
     * @param string $uid Firebase UID
     * @param FcmTokenService $tokenService
     * @param \App\Services\FcmNotificationService $notificationService
     * @return array
     */
    protected function cleanInvalidTokens($uid, $tokenService, $notificationService)
    {
        $registrations = $tokenService->getRegistrations($uid, app(\App\Services\PushOrigin::class)->current());
        $cleaned = 0;
        $this->line('Found ' . count($registrations) . ' registrations to validate...');
        foreach ($registrations as $row) {
            $status = $notificationService->validationStatus($row['token']);
            if ($status === 'unregistered') {
                if ($tokenService->removeToken($uid, $row['token'], $row['generation'])) {
                    $cleaned++;
                }
            } elseif ($status === 'unknown') {
                $this->warn('Validation unavailable; registration preserved.');
            }
        }

        return ['cleaned' => $cleaned];
    }
}
