<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ActivityLogger;

class UserActivityObserver
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function created(User $user): void
    {
        $this->logger->record(
            'account.created',
            $user,
            "Account {$user->name} was created with the {$user->role} role.",
            $user->branch_code,
            ['role' => $user->role]
        );
    }

    public function updated(User $user): void
    {
        if (! $user->wasChanged(['name', 'email', 'role', 'branch_code'])) {
            return;
        }

        $this->logger->record(
            'account.updated',
            $user,
            "Account {$user->name} was updated.",
            $user->branch_code,
            [
                'role' => $user->role,
                'branch_code' => $user->branch_code,
            ]
        );
    }

    public function deleted(User $user): void
    {
        $this->logger->record(
            'account.deleted',
            $user,
            "Account {$user->name} ({$user->email}) was deleted.",
            $user->branch_code,
            ['role' => $user->role]
        );
    }
}
