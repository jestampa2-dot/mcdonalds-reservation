<?php

namespace App\Observers;

use App\Models\Branch;
use App\Services\ActivityLogger;

class BranchActivityObserver
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function created(Branch $branch): void
    {
        $this->logger->record(
            'branch.created',
            $branch,
            "Branch {$branch->name} was added to the reservation system.",
            $branch->code
        );
    }

    public function updated(Branch $branch): void
    {
        $this->logger->record(
            'branch.updated',
            $branch,
            "Branch {$branch->name} settings were updated.",
            $branch->code,
            ['is_active' => (bool) $branch->is_active]
        );
    }

    public function deleted(Branch $branch): void
    {
        $this->logger->record(
            'branch.deleted',
            $branch,
            "Branch {$branch->name} was removed from the reservation system.",
            $branch->code
        );
    }
}
