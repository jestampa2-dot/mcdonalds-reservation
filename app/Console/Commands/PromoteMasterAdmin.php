<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteMasterAdmin extends Command
{
    protected $signature = 'user:promote-master {email : Email address of an existing account} {--force : Skip the confirmation prompt}';

    protected $description = 'Promote an existing account to Master Admin without creating a default password';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account was found with that email address.');

            return self::FAILURE;
        }

        if ($user->role === 'master_admin') {
            if (! $user->google_id && ! $user->google_link_pending) {
                $user->update(['google_link_pending' => true]);
            }

            $this->info("{$user->email} is already a Master Admin.");
            $this->line($this->googleStatus($user->fresh()));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Promote {$user->email} to Master Admin with access to every branch?")) {
            $this->warn('No changes were made.');

            return self::SUCCESS;
        }

        $user->update([
            'role' => 'master_admin',
            'branch_code' => null,
            'google_link_pending' => ! $user->google_id,
        ]);

        $this->info("{$user->email} is now a Master Admin.");
        $this->line($this->googleStatus($user->fresh()));

        return self::SUCCESS;
    }

    private function googleStatus(User $user): string
    {
        if ($user->google_id) {
            return 'Google sign-in: linked';
        }

        return $user->google_link_pending
            ? 'Google sign-in: ready for one-time verified linking'
            : 'Google sign-in: not linked';
    }
}
