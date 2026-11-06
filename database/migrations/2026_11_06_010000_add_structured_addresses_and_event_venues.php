<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('barangay')->nullable()->after('address_line');
            $table->string('municipality')->nullable()->after('barangay');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('venue_type', 20)->default('branch')->after('branch_code');
            $table->string('venue_name')->nullable()->after('venue_type');
            $table->string('venue_address', 500)->nullable()->after('venue_name');
            $table->string('venue_barangay')->nullable()->after('venue_address');
            $table->string('venue_municipality')->nullable()->after('venue_barangay');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'venue_type',
                'venue_name',
                'venue_address',
                'venue_barangay',
                'venue_municipality',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['barangay', 'municipality']);
        });
    }
};
