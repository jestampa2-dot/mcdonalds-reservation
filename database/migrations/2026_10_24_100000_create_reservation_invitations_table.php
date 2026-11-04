<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invitee_name', 100);
            $table->string('invitee_phone', 20);
            $table->string('invitee_email')->nullable();
            $table->uuid('token')->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'invitee_phone']);
            $table->index(['invitee_user_id', 'status']);
            $table->index(['invitee_email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_invitations');
    }
};
