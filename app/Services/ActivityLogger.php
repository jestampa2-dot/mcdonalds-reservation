<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ActivityLogger
{
    public function record(
        string $action,
        ?Model $subject,
        string $description,
        ?string $branchCode = null,
        array $metadata = []
    ): void {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        try {
            $actor = auth()->user();

            ActivityLog::create([
                'actor_id' => $actor?->getAuthIdentifier(),
                'actor_name' => $actor?->name ?? 'System',
                'actor_role' => $actor?->role ?? 'system',
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'branch_code' => $branchCode,
                'description' => $description,
                'metadata' => $metadata ?: null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('An activity audit entry could not be recorded.', [
                'action' => $action,
                'error_type' => get_class($exception),
            ]);
        }
    }
}
