<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('payment_type')->default('full_payment')->after('payment_proof_path');
            $table->decimal('payment_amount_due', 12, 2)->nullable()->after('payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'payment_amount_due']);
        });
    }
};
