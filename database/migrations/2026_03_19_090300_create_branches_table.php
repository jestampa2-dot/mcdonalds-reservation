<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('city');
            $table->json('supports');
            $table->unsignedInteger('concurrent_limit')->default(1);
            $table->unsignedInteger('max_guests')->default(20);
            $table->string('map_url')->nullable();
            $table->json('inventory')->nullable();
            $table->json('hosts')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
