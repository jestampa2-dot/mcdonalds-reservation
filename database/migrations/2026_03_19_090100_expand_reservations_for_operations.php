<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('booking_reference')->nullable()->unique()->after('phone');
            $table->string('branch_code')->nullable()->after('branch');
            $table->string('package_code')->nullable()->after('package_name');
            $table->json('menu_bundles')->nullable()->after('beverage_package');
            $table->json('add_ons')->nullable()->after('menu_bundles');
            $table->string('payment_proof_path')->nullable()->after('add_ons');
            $table->decimal('total_amount', 10, 2)->default(0)->after('guests');
            $table->string('check_in_code')->nullable()->after('total_amount');
            $table->timestamp('checked_in_at')->nullable()->after('check_in_code');
            $table->string('checked_in_by')->nullable()->after('checked_in_at');
            $table->string('service_status')->default('available')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'booking_reference',
                'branch_code',
                'package_code',
                'menu_bundles',
                'add_ons',
                'payment_proof_path',
                'total_amount',
                'check_in_code',
                'checked_in_at',
                'checked_in_by',
                'service_status',
            ]);
        });
    }
};
