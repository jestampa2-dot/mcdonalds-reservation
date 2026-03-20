<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('package_name')->nullable()->after('reservation_type');
            $table->string('room_choice')->nullable()->after('package_name');
            $table->string('food_package')->nullable()->after('room_choice');
            $table->string('beverage_package')->nullable()->after('food_package');
            $table->text('event_materials')->nullable()->after('beverage_package');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'package_name',
                'room_choice',
                'food_package',
                'beverage_package',
                'event_materials',
            ]);
        });
    }
};