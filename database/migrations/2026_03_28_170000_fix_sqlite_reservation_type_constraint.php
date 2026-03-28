<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite' || ! Schema::hasTable('reservations')) {
            return;
        }

        $createStatement = DB::table('sqlite_master')
            ->where('type', 'table')
            ->where('name', 'reservations')
            ->value('sql');

        if (is_string($createStatement) && str_contains($createStatement, "'table'") && Schema::hasTable('reservations_old')) {
            DB::statement('DROP INDEX IF EXISTS reservations_booking_reference_unique');
            Schema::drop('reservations_old');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS reservations_booking_reference_unique ON reservations (booking_reference)');
            return;
        }

        if (is_string($createStatement) && str_contains($createStatement, "'table'")) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS reservations_booking_reference_unique ON reservations (booking_reference)');
            return;
        }

        Schema::disableForeignKeyConstraints();
        DB::statement('DROP INDEX IF EXISTS reservations_booking_reference_unique');

        DB::statement('ALTER TABLE reservations RENAME TO reservations_old');

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('booking_reference')->nullable();
            $table->enum('reservation_type', ['birthday', 'business', 'table']);
            $table->string('package_name')->nullable();
            $table->string('package_code')->nullable();
            $table->string('room_choice')->nullable();
            $table->string('food_package')->nullable();
            $table->string('beverage_package')->nullable();
            $table->text('event_materials')->nullable();
            $table->json('menu_bundles')->nullable();
            $table->json('add_ons')->nullable();
            $table->json('service_adjustments')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->string('branch');
            $table->string('branch_code')->nullable();
            $table->date('event_date');
            $table->time('event_time');
            $table->unsignedTinyInteger('duration_hours')->default(4);
            $table->integer('guests');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('check_in_code')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->string('checked_in_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->string('service_status')->default('available');
            $table->timestamps();
        });

        DB::statement('
            INSERT INTO reservations (
                id, user_id, assigned_staff_id, name, email, phone, booking_reference, reservation_type,
                package_name, package_code, room_choice, food_package, beverage_package, event_materials,
                menu_bundles, add_ons, service_adjustments, payment_proof_path, branch, branch_code,
                event_date, event_time, duration_hours, guests, total_amount, check_in_code, checked_in_at,
                checked_in_by, notes, status, service_status, created_at, updated_at
            )
            SELECT
                id, user_id, assigned_staff_id, name, email, phone, booking_reference, reservation_type,
                package_name, package_code, room_choice, food_package, beverage_package, event_materials,
                menu_bundles, add_ons, service_adjustments, payment_proof_path, branch, branch_code,
                event_date, event_time, duration_hours, guests, total_amount, check_in_code, checked_in_at,
                checked_in_by, notes, status, service_status, created_at, updated_at
            FROM reservations_old
        ');

        Schema::drop('reservations_old');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS reservations_booking_reference_unique ON reservations (booking_reference)');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // No-op: this migration only fixes SQLite compatibility for existing schema.
    }
};
