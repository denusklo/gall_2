<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

class MigrateRequestStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'requests:migrate-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add status field to existing Firebase requests (default to pending)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting migration of request status...');

        $database = app('firebase.database');
        $reference = $database->getReference('Requests/');

        try {
            // Get all requests
            $allRequests = $reference->getValue();

            if (!$allRequests) {
                $this->info('No requests found in Firebase.');
                return 0;
            }

            $updatedCount = 0;
            $skippedCount = 0;

            foreach ($allRequests as $userId => $userRequests) {
                if (!is_array($userRequests)) {
                    continue;
                }

                foreach ($userRequests as $requestId => $requestData) {
                    if (!is_array($requestData)) {
                        continue;
                    }

                    // Check if status field already exists
                    if (isset($requestData['status'])) {
                        $skippedCount++;
                        continue;
                    }

                    // Add status field with default value 'pending'
                    $updateData = [
                        'status' => 'pending'
                    ];

                    // Update the specific request
                    $database->getReference("Requests/{$userId}/{$requestId}")->update($updateData);
                    $updatedCount++;

                    $this->line("Updated request {$requestId} for user {$userId}");
                }
            }

            $this->info("\nMigration completed successfully!");
            $this->info("Updated requests: {$updatedCount}");
            $this->info("Skipped requests (already had status): {$skippedCount}");

        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}