<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('opening_hour')->default(7);
            $table->unsignedTinyInteger('closing_hour')->default(23);
            $table->unsignedTinyInteger('default_duration_hours')->default(4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('room_options', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->text('description');
            $table->string('preferred_event_type')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_options');
        Schema::dropIfExists('booking_settings');
    }
};
