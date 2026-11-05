<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $email = Str::lower(trim((string) env('MASTER_ADMIN_EMAIL', 'jestampa2@ssct.edu.ph')));

        if ($email === '' || ! Schema::hasTable('users')) {
            return;
        }

        $user = DB::table('users')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if (! $user) {
            return;
        }

        $updates = [
            'email' => $email,
            'role' => 'master_admin',
            'branch_code' => null,
            'email_verified_at' => $user->email_verified_at ?: now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('users', 'google_link_pending')) {
            $updates['google_link_pending'] = blank($user->google_id ?? null);
        }

        DB::table('users')->where('id', $user->id)->update($updates);
    }

    public function down(): void
    {
        // Do not automatically revoke production Master Admin access on rollback.
    }
};
