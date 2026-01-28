<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Kreait\Firebase\Auth;

class SetVolunteerClaim extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:set-volunteer {email} {--remove : Remove volunteer claim}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set or remove volunteer claim for a Firebase user by email';

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
        $email = $this->argument('email');
        $remove = $this->option('remove');

        try {
            $auth = app('firebase.auth');

            // Get the user by email
            $user = $auth->getUserByEmail($email);

            // Get current custom claims
            $currentClaims = $user->customClaims ?? [];

            if ($remove) {
                // Remove volunteer claim
                unset($currentClaims['volunteer']);
                $action = 'removed from';
                $this->info("Removing volunteer claim from user: {$email}");
            } else {
                // Add volunteer claim
                $currentClaims['volunteer'] = true;
                $action = 'added to';
                $this->info("Adding volunteer claim to user: {$email}");
            }

            // Set the custom claims
            $auth->setCustomUserClaims($user->uid, $currentClaims);

            $this->info("Volunteer claim successfully {$action} user: {$email}");
            $this->info("User UID: {$user->uid}");

            // Show current claims
            $this->line("\nCurrent custom claims:");
            $this->line(json_encode($currentClaims, JSON_PRETTY_PRINT));

        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            $this->error("User not found with email: {$email}");
            return 1;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}