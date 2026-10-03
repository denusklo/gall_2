<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

            // Read-only lookup by email; the mutation itself is locked, refetched, merged and audited.
            $user = $auth->getUserByEmail($email);

            $this->info(($remove ? 'Removing' : 'Adding') . " volunteer claim " . ($remove ? 'from' : 'to') . " user: {$email}");
            app(\App\Services\RoleChangeService::class)->setVolunteer($user->uid, !$remove);

            $this->info('Volunteer claim successfully ' . ($remove ? 'removed from' : 'added to') . " user: {$email}");
            $this->info("User UID: {$user->uid}");
            $this->line('Volunteer flag: ' . ($remove ? 'false' : 'true'));
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            $this->error("User not found with email: {$email}");
            return 1;
        } catch (\App\Exceptions\RoleChangeException $e) {
            $this->error($e->getMessage() . ($e->partial ? ' [PARTIAL/UNKNOWN: verify claims manually]' : ''));
            return 1;
        } catch (\Exception $e) {
            $this->error('Error: lookup failed (' . get_class($e) . ').');
            return 1;
        }

        return 0;
    }
}