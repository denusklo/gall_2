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
                            {--duplicates : Clean up duplicate tokens per domain (keeps most recent)}
                            {--old : Clean up old tokens without domain information}
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

        $uid = $this->option('uid');
        $cleanOld = $this->option('old') || $this->option('all');
        $cleanDuplicates = $this->option('duplicates') || $this->option('all');

        // If no specific option is provided, default to cleaning old tokens
        if (!$cleanOld && !$cleanDuplicates) {
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

        $this->line("\n" . str_repeat('-', 50));
        $this->info("Total tokens cleaned: {$totalCleaned}");
        $this->line('Cleanup completed!');

        return 0;
    }
}
